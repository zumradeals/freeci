<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 6 — missions, propositions versionnées, sélection, origine de la commande.
 *
 * Données existantes : rien n'est supprimé ni réécrit. Les commandes et accords existants reçoivent l'origine « service » (valeur par défaut) ;
 * les colonnes `service_id` / `service_row_version` deviennent NULLABLES, car une commande issue d'une mission n'a pas de service.
 * Un service fictif n'est jamais créé pour contourner le modèle.
 */
return new class extends Migration
{
    private const MISSION_CONTENT = ['category_id', 'title', 'description', 'budget_xof', 'application_deadline', 'client_inputs', 'brief_requires_files'];

    public function up(): void
    {
        Schema::create('missions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('client_id')->constrained('users')->restrictOnDelete();
            $t->string('slug', 160)->unique();                           // « brouillon-… » jusqu'à la première publication, puis stable
            $t->string('status', 20);                                    // draft in_review open reserved awarded selection_ended closed cancelled expired
            $t->uuid('published_version_id')->nullable();
            $t->timestampTz('published_at')->nullable();
            $t->timestampTz('closed_at')->nullable();
            $t->string('closure_note', 500)->nullable();
            $t->boolean('is_demo')->default(false);
            $t->unsignedInteger('row_version')->default(1);
            $t->timestampsTz();
            $t->index(['client_id', 'status']);
            $t->index(['status', 'published_at']);
        });
        DB::statement("ALTER TABLE missions ADD CONSTRAINT missions_status_chk CHECK (status IN ('draft','in_review','open','reserved','awarded','selection_ended','closed','cancelled','expired'))");

        Schema::create('mission_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('mission_id')->constrained('missions')->restrictOnDelete();
            $t->unsignedSmallInteger('number');
            $t->string('state', 20);                                     // draft in_review changes_requested published superseded
            $t->foreignUuid('category_id')->constrained('categories');
            $t->string('title', 160)->default('');
            $t->text('description')->default('');
            $t->unsignedBigInteger('budget_xof')->nullable();
            $t->timestampTz('application_deadline')->nullable();
            $t->jsonb('client_inputs')->default('[]');                   // libellés du brief : les RÉPONSES restent privées (donnée de la commande)
            $t->boolean('brief_requires_files')->default(false);
            $t->unsignedInteger('revision_no')->default(1);
            $t->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('submitted_at')->nullable();
            $t->timestampTz('decided_at')->nullable();
            $t->foreignUuid('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('decision_note')->nullable();
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();
            $t->unique(['mission_id', 'number']);
        });
        DB::statement("ALTER TABLE mission_versions ADD CONSTRAINT mission_versions_state_chk CHECK (state IN ('draft','in_review','changes_requested','published','superseded'))");
        DB::statement("CREATE UNIQUE INDEX mission_versions_one_open_uq ON mission_versions (mission_id) WHERE state IN ('draft','in_review','changes_requested')");
        DB::statement("CREATE UNIQUE INDEX mission_versions_one_published_uq ON mission_versions (mission_id) WHERE state = 'published'");
        DB::statement("ALTER TABLE mission_versions ADD CONSTRAINT mission_versions_complete_chk CHECK (state IN ('draft','changes_requested') OR (title <> '' AND budget_xof > 0 AND application_deadline IS NOT NULL))");
        Schema::table('missions', fn (Blueprint $t) => $t->foreign('published_version_id')->references('id')->on('mission_versions')->restrictOnDelete());
        $cols = implode(' OR ', array_map(fn ($c) => "NEW.{$c} IS DISTINCT FROM OLD.{$c}", self::MISSION_CONTENT));
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION freeci_mission_version_frozen() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.state <> 'draft' THEN RAISE EXCEPTION 'Une version de mission soumise ou publiée est conservée' USING ERRCODE = 'integrity_constraint_violation'; END IF;
                    RETURN OLD;
                END IF;
                IF OLD.state IN ('in_review','published','superseded') AND ({$cols}) THEN
                    RAISE EXCEPTION 'Une version de mission soumise, publiée ou remplacée ne se modifie pas : créez une nouvelle version' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF NEW.state <> OLD.state AND NOT (
                    (OLD.state IN ('draft','changes_requested') AND NEW.state = 'in_review')
                    OR (OLD.state = 'in_review' AND NEW.state IN ('published','changes_requested','draft'))
                    OR (OLD.state = 'published' AND NEW.state = 'superseded')) THEN
                    RAISE EXCEPTION 'Transition de version de mission impossible : % vers %', OLD.state, NEW.state USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END \$\$
        SQL);
        DB::statement('CREATE TRIGGER mission_versions_frozen BEFORE UPDATE OR DELETE ON mission_versions FOR EACH ROW EXECUTE FUNCTION freeci_mission_version_frozen()');

        Schema::create('proposals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('mission_id')->constrained('missions')->restrictOnDelete();
            $t->foreignUuid('freelancer_id')->constrained('users')->restrictOnDelete();
            $t->string('state', 12);                                     // active withdrawn selected released closed
            $t->unsignedInteger('row_version')->default(1);
            $t->timestampsTz();
        });
        DB::statement("ALTER TABLE proposals ADD CONSTRAINT proposals_state_chk CHECK (state IN ('active','withdrawn','selected','released','closed'))");
        // Une proposition active ou retenue par mission et par freelance (docs/02 §4.3 : proposal_active_uq).
        DB::statement("CREATE UNIQUE INDEX proposal_active_uq ON proposals (mission_id, freelancer_id) WHERE state IN ('active','selected')");
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_proposal_not_own() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF EXISTS (SELECT 1 FROM missions m WHERE m.id = NEW.mission_id AND m.client_id = NEW.freelancer_id) THEN
                    RAISE EXCEPTION 'On ne candidate pas à sa propre mission' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER proposals_not_own BEFORE INSERT ON proposals FOR EACH ROW EXECUTE FUNCTION freeci_proposal_not_own()');

        Schema::create('proposal_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('proposal_id')->constrained('proposals')->restrictOnDelete();
            $t->unsignedSmallInteger('number');
            $t->foreignUuid('mission_version_id')->constrained('mission_versions')->restrictOnDelete();   // la version du besoin à laquelle cette offre répond
            $t->unsignedBigInteger('price_xof');
            $t->unsignedSmallInteger('delivery_days');
            $t->unsignedSmallInteger('revisions_included');
            $t->text('scope');
            $t->jsonb('deliverables');
            $t->string('delivery_mode', 8);                              // files | message
            $t->timestampTz('valid_until');
            $t->text('message')->nullable();
            $t->timestampTz('submitted_at')->useCurrent();
            $t->unique(['proposal_id', 'number']);
        });
        DB::statement('ALTER TABLE proposal_versions ADD CONSTRAINT proposal_versions_amount_chk CHECK (price_xof > 0 AND delivery_days > 0)');
        DB::statement("ALTER TABLE proposal_versions ADD CONSTRAINT proposal_versions_mode_chk CHECK (delivery_mode IN ('files','message'))");
        DB::statement('CREATE TRIGGER proposal_versions_append_only BEFORE UPDATE OR DELETE ON proposal_versions FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');
        Schema::table('missions', function (Blueprint $t) {
            $t->uuid('selected_proposal_version_id')->nullable();
            $t->foreign('selected_proposal_version_id')->references('id')->on('proposal_versions')->restrictOnDelete();
        });

        Schema::create('mission_events', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('mission_id')->constrained('missions')->restrictOnDelete();
            $t->uuid('version_id')->nullable();
            $t->uuid('proposal_id')->nullable();
            $t->string('type', 40);
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('actor_label', 60)->nullable();
            $t->text('note')->nullable();
            $t->jsonb('meta')->nullable();
            $t->timestampTz('occurred_at')->useCurrent();
            $t->index(['mission_id', 'id']);
        });
        DB::statement('CREATE TRIGGER mission_events_append_only BEFORE UPDATE OR DELETE ON mission_events FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        // ---- Origine de la commande : service (existant) ou mission ----
        Schema::table('orders', function (Blueprint $t) {
            $t->string('origin', 10)->default('service');
            $t->foreignUuid('mission_id')->nullable()->constrained('missions')->restrictOnDelete();
            $t->foreignUuid('proposal_version_id')->nullable()->constrained('proposal_versions')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE orders ALTER COLUMN service_id DROP NOT NULL');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_origin_chk CHECK (
            (origin = 'service' AND service_id IS NOT NULL AND mission_id IS NULL AND proposal_version_id IS NULL)
            OR (origin = 'mission' AND service_id IS NULL AND mission_id IS NOT NULL AND proposal_version_id IS NOT NULL))");
        // Une seule commande « vivante » par mission : rempart contre deux sélections simultanées (docs/02 : order_mission_live_uq).
        DB::statement("CREATE UNIQUE INDEX order_mission_live_uq ON orders (mission_id) WHERE mission_id IS NOT NULL AND state NOT IN ('cancelled','expired')");
        DB::statement('CREATE UNIQUE INDEX order_proposal_version_uq ON orders (proposal_version_id) WHERE proposal_version_id IS NOT NULL');

        Schema::table('order_agreements', function (Blueprint $t) {
            $t->string('origin', 10)->default('service');
            $t->foreignUuid('mission_id')->nullable()->constrained('missions')->restrictOnDelete();
            $t->foreignUuid('mission_version_id')->nullable()->constrained('mission_versions')->restrictOnDelete();
            $t->foreignUuid('proposal_version_id')->nullable()->constrained('proposal_versions')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE order_agreements ALTER COLUMN service_id DROP NOT NULL');
        DB::statement('ALTER TABLE order_agreements ALTER COLUMN service_row_version DROP NOT NULL');
        DB::statement("ALTER TABLE order_agreements ADD CONSTRAINT order_agreements_origin_chk CHECK (
            (origin = 'service' AND service_id IS NOT NULL AND service_row_version IS NOT NULL AND proposal_version_id IS NULL)
            OR (origin = 'mission' AND service_id IS NULL AND mission_id IS NOT NULL AND mission_version_id IS NOT NULL AND proposal_version_id IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE order_agreements DROP CONSTRAINT order_agreements_origin_chk');
        DB::statement('ALTER TABLE order_agreements ALTER COLUMN service_row_version SET NOT NULL');
        DB::statement('ALTER TABLE order_agreements ALTER COLUMN service_id SET NOT NULL');
        Schema::table('order_agreements', function (Blueprint $t) {
            $t->dropConstrainedForeignId('proposal_version_id');
            $t->dropConstrainedForeignId('mission_version_id');
            $t->dropConstrainedForeignId('mission_id');
            $t->dropColumn('origin');
        });
        DB::statement('DROP INDEX IF EXISTS order_proposal_version_uq');
        DB::statement('DROP INDEX IF EXISTS order_mission_live_uq');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_origin_chk');
        DB::statement('ALTER TABLE orders ALTER COLUMN service_id SET NOT NULL');
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('proposal_version_id');
            $t->dropConstrainedForeignId('mission_id');
            $t->dropColumn('origin');
        });
        Schema::dropIfExists('mission_events');
        Schema::table('missions', fn (Blueprint $t) => $t->dropConstrainedForeignId('selected_proposal_version_id'));
        Schema::dropIfExists('proposal_versions');
        DB::statement('DROP TRIGGER IF EXISTS proposals_not_own ON proposals');
        Schema::dropIfExists('proposals');
        DB::statement('DROP FUNCTION IF EXISTS freeci_proposal_not_own()');
        Schema::table('missions', fn (Blueprint $t) => $t->dropConstrainedForeignId('published_version_id'));
        Schema::dropIfExists('mission_versions');
        DB::statement('DROP FUNCTION IF EXISTS freeci_mission_version_frozen()');
        Schema::dropIfExists('missions');
    }
};
