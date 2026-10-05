<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lot 5 — profil freelance, services versionnés, modération, médias ; correctifs du lot 4.
 *
 * Données existantes : rien n'est supprimé ni réécrit.
 * - `services` reste la version PUBLIÉE (ce que voit le public) ; les modifications vivent dans `service_versions` et ne la remplacent
 *   qu'à l'approbation. Chaque service existant reçoit une version initiale (copie de son contenu actuel).
 * - Les accords existants ne sont PAS modifiés : `order_agreements.delivery_mode` vaut « legacy » (ancien indicateur ambigu) par défaut.
 * - Les profils qui portent déjà un service publié restent publics (`published_at` posé).
 */
return new class extends Migration
{
    private const CONTENT = ['category_id', 'title', 'summary', 'scope', 'price_xof', 'delivery_days', 'revisions_included', 'deliverables', 'exclusions', 'client_inputs', 'images', 'brief_requires_files', 'delivery_requires_files'];

    public function up(): void
    {
        // ---- Correctifs du lot 4 : mode de livraison des accords ----
        Schema::table('order_agreements', fn (Blueprint $t) => $t->string('delivery_mode', 12)->default('legacy'));       // legacy | files | message
        DB::statement("ALTER TABLE order_agreements ADD CONSTRAINT order_agreements_delivery_mode_chk CHECK (delivery_mode IN ('legacy','files','message'))");
        Schema::table('order_follow_ups', function (Blueprint $t) {
            $t->text('note')->nullable();
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        // Services : le défaut devient « au moins un fichier ». Le lot 4 avait posé « faux » partout sans que l'auteur l'ait choisi.
        DB::statement('ALTER TABLE services ALTER COLUMN delivery_requires_files SET DEFAULT true');
        DB::statement("UPDATE services SET delivery_requires_files = true WHERE slug <> 'service-de-recette-mise-en-plan'");

        // ---- Profil freelance ----
        Schema::table('freelance_profiles', function (Blueprint $t) {
            $t->string('slug', 140)->nullable()->unique();
            $t->text('bio')->nullable();
            $t->jsonb('skills')->default('[]');
            $t->timestampTz('published_at')->nullable();
        });
        foreach (DB::table('freelance_profiles')->orderBy('created_at')->get() as $p) {
            $slug = Str::slug($p->display_name) ?: 'freelance';
            DB::table('freelance_profiles')->where('id', $p->id)->update(['slug' => Str::limit($slug, 120, '').'-'.substr(str_replace('-', '', $p->id), -6)]);
        }
        DB::statement("UPDATE freelance_profiles SET published_at = now() WHERE id IN (SELECT freelance_profile_id FROM services WHERE status IN ('published','suspended','archived'))");

        // ---- Versions de service ----
        Schema::create('service_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $t->unsignedSmallInteger('number');
            $t->string('state', 20);                                    // draft | in_review | changes_requested | published | superseded
            $t->foreignUuid('category_id')->constrained('categories');
            $t->string('title', 160)->default('');
            $t->text('summary')->default('');
            $t->text('scope')->default('');
            $t->unsignedBigInteger('price_xof')->nullable();
            $t->unsignedSmallInteger('delivery_days')->nullable();
            $t->unsignedSmallInteger('revisions_included')->default(1);
            $t->jsonb('deliverables')->default('[]');
            $t->jsonb('exclusions')->default('[]');
            $t->jsonb('client_inputs')->default('[]');
            $t->jsonb('images')->default('[]');                         // [{id, alt, caption}] ou, pour l'existant, [{src, card, alt, caption}]
            $t->boolean('brief_requires_files')->default(false);
            $t->boolean('delivery_requires_files')->default(true);
            $t->unsignedInteger('revision_no')->default(1);             // refuse une sauvegarde faite depuis un écran périmé
            $t->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('submitted_at')->nullable();
            $t->timestampTz('decided_at')->nullable();
            $t->foreignUuid('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('decision_note')->nullable();
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();
            $t->unique(['service_id', 'number']);
        });
        DB::statement("ALTER TABLE service_versions ADD CONSTRAINT service_versions_state_chk CHECK (state IN ('draft','in_review','changes_requested','published','superseded'))");
        DB::statement("CREATE UNIQUE INDEX service_versions_one_open_uq ON service_versions (service_id) WHERE state IN ('draft','in_review','changes_requested')");
        DB::statement("CREATE UNIQUE INDEX service_versions_one_published_uq ON service_versions (service_id) WHERE state = 'published'");
        DB::statement("ALTER TABLE service_versions ADD CONSTRAINT service_versions_complete_chk CHECK (state IN ('draft','changes_requested') OR (price_xof > 0 AND delivery_days > 0 AND title <> ''))");
        $cols = implode(' OR ', array_map(fn ($c) => "NEW.{$c} IS DISTINCT FROM OLD.{$c}", self::CONTENT));
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION freeci_service_version_frozen() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.state <> 'draft' THEN RAISE EXCEPTION 'Une version soumise ou publiée est conservée' USING ERRCODE = 'integrity_constraint_violation'; END IF;
                    RETURN OLD;
                END IF;
                IF OLD.state IN ('in_review','published','superseded') AND ({$cols}) THEN
                    RAISE EXCEPTION 'Une version soumise, publiée ou remplacée ne se modifie pas : créez une nouvelle version' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF NEW.state <> OLD.state AND NOT (
                    (OLD.state = 'draft' AND NEW.state = 'in_review') OR (OLD.state = 'changes_requested' AND NEW.state = 'in_review')
                    OR (OLD.state = 'in_review' AND NEW.state IN ('published','changes_requested','draft'))
                    OR (OLD.state = 'published' AND NEW.state = 'superseded')) THEN
                    RAISE EXCEPTION 'Transition de version impossible : % vers %', OLD.state, NEW.state USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END \$\$
        SQL);
        DB::statement('CREATE TRIGGER service_versions_frozen BEFORE UPDATE OR DELETE ON service_versions FOR EACH ROW EXECUTE FUNCTION freeci_service_version_frozen()');

        Schema::create('service_events', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $t->foreignUuid('version_id')->nullable()->constrained('service_versions')->restrictOnDelete();
            $t->string('type', 40);
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('actor_label', 60)->nullable();                  // « propriétaire », « modération (console) »
            $t->text('note')->nullable();
            $t->jsonb('meta')->nullable();
            $t->timestampTz('occurred_at')->useCurrent();
            $t->index(['service_id', 'id']);
        });
        DB::statement('CREATE TRIGGER service_events_append_only BEFORE UPDATE OR DELETE ON service_events FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        Schema::create('service_media', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $t->foreignUuid('uploader_id')->constrained('users')->restrictOnDelete();
            $t->string('mime', 40);
            $t->unsignedSmallInteger('width');
            $t->unsignedSmallInteger('height');
            $t->unsignedInteger('size_bytes');
            $t->char('sha256', 64);
            $t->string('key_large', 80)->unique();
            $t->string('key_card', 80)->unique();
            $t->timestampTz('created_at')->useCurrent();
        });

        // Un service jamais publié porte un contenu « vide » côté public : seuls les services en ligne ou retirés exigent prix et délai.
        DB::statement('ALTER TABLE services DROP CONSTRAINT services_price_chk');
        DB::statement("ALTER TABLE services ADD CONSTRAINT services_price_chk CHECK (status IN ('draft','in_review') OR (price_xof > 0 AND delivery_days > 0))");

        // Version initiale de chaque service existant (copie de son contenu actuel).
        foreach (DB::table('services')->get() as $s) {
            $state = match ($s->status) {
                'published', 'suspended', 'archived' => 'published', 'in_review' => 'in_review', default => 'draft'
            };
            DB::table('service_versions')->insert([
                'id' => (string) Str::uuid(), 'service_id' => $s->id, 'number' => 1, 'state' => $state, 'category_id' => $s->category_id, 'title' => $s->title,
                'summary' => $s->summary, 'scope' => $s->scope, 'price_xof' => $s->price_xof, 'delivery_days' => $s->delivery_days, 'revisions_included' => $s->revisions_included,
                'deliverables' => $s->deliverables, 'exclusions' => $s->exclusions, 'client_inputs' => $s->client_inputs, 'images' => $s->images,
                'brief_requires_files' => $s->brief_requires_files, 'delivery_requires_files' => $s->delivery_requires_files,
                'published_at' => $state === 'published' ? ($s->published_at ?? $s->created_at) : null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE services DROP CONSTRAINT services_price_chk');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_chk CHECK (price_xof > 0 AND delivery_days > 0)');
        Schema::dropIfExists('service_media');
        Schema::dropIfExists('service_events');
        Schema::dropIfExists('service_versions');
        DB::statement('DROP FUNCTION IF EXISTS freeci_service_version_frozen()');
        Schema::table('freelance_profiles', fn (Blueprint $t) => $t->dropColumn(['slug', 'bio', 'skills', 'published_at']));
        DB::statement('ALTER TABLE services ALTER COLUMN delivery_requires_files SET DEFAULT false');
        Schema::table('order_follow_ups', fn (Blueprint $t) => $t->dropConstrainedForeignId('actor_id'));
        Schema::table('order_follow_ups', fn (Blueprint $t) => $t->dropColumn('note'));
        DB::statement('ALTER TABLE order_agreements DROP CONSTRAINT order_agreements_delivery_mode_chk');
        Schema::table('order_agreements', fn (Blueprint $t) => $t->dropColumn('delivery_mode'));
    }
};
