<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 4 — livraison, corrections, report d'échéance, validation. Aucune donnée existante n'est modifiée :
 * - `delivery_requires_files` : false pour les services et accords EXISTANTS (aucune exigence nouvelle), copié du service dans les nouveaux accords ;
 * - le déclencheur « départ unique » autorise désormais UN seul cas de changement d'échéance : un report ACCEPTÉ, enregistré.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', fn (Blueprint $t) => $t->boolean('delivery_requires_files')->default(false));
        Schema::table('order_agreements', fn (Blueprint $t) => $t->boolean('delivery_requires_files')->default(false));

        Schema::create('deliveries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $t->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $t->string('state', 12);                                     // draft | submitted (une version soumise ne change plus jamais)
            $t->unsignedSmallInteger('version')->nullable();             // attribuée à la soumission
            $t->text('message')->nullable();
            $t->unsignedBigInteger('correction_request_id')->nullable(); // la demande de correction à laquelle cette version répond
            $t->timestampTz('submitted_at')->nullable();
            $t->timestampTz('review_deadline_at')->nullable();           // fin du délai d'examen : sans effet automatique
            $t->timestampsTz();
            $t->unique(['order_id', 'version']);
        });
        DB::statement("ALTER TABLE deliveries ADD CONSTRAINT deliveries_state_chk CHECK (state IN ('draft','submitted'))");
        DB::statement("ALTER TABLE deliveries ADD CONSTRAINT deliveries_submitted_chk CHECK ((state = 'submitted') = (version IS NOT NULL AND submitted_at IS NOT NULL AND review_deadline_at IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX deliveries_one_draft_uq ON deliveries (order_id) WHERE state = 'draft'");
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_delivery_frozen() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.state = 'submitted' THEN RAISE EXCEPTION 'Une livraison soumise est conservée' USING ERRCODE = 'integrity_constraint_violation'; END IF;
                    RETURN OLD;
                END IF;
                IF OLD.state = 'submitted' THEN RAISE EXCEPTION 'Une livraison soumise ne se modifie pas : déposez une nouvelle version' USING ERRCODE = 'integrity_constraint_violation'; END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER deliveries_frozen BEFORE UPDATE OR DELETE ON deliveries FOR EACH ROW EXECUTE FUNCTION freeci_delivery_frozen()');

        Schema::create('correction_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $t->foreignUuid('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $t->unsignedSmallInteger('number');                          // 1..corrections incluses (contrôlé sous verrou)
            $t->text('reason');
            $t->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['order_id', 'number']);
            $t->unique('delivery_id');                                   // une seule demande par version : jamais deux corrections consommées
        });
        Schema::table('deliveries', fn (Blueprint $t) => $t->foreign('correction_request_id')->references('id')->on('correction_requests')->restrictOnDelete());
        DB::statement('CREATE UNIQUE INDEX deliveries_answers_once_uq ON deliveries (correction_request_id) WHERE correction_request_id IS NOT NULL');

        Schema::create('extension_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $t->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('previous_due_at');                          // échéance en vigueur au moment de la demande : conservée
            $t->timestampTz('proposed_due_at');
            $t->text('reason');
            $t->string('state', 12);                                     // pending | accepted | declined | withdrawn
            $t->foreignUuid('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('decided_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE extension_requests ADD CONSTRAINT extension_requests_state_chk CHECK (state IN ('pending','accepted','declined','withdrawn'))");
        DB::statement('ALTER TABLE extension_requests ADD CONSTRAINT extension_requests_later_chk CHECK (proposed_due_at > previous_due_at)');
        DB::statement("CREATE UNIQUE INDEX extension_requests_one_pending_uq ON extension_requests (order_id) WHERE state = 'pending'");
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_extension_decided_once() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Une demande de report est conservée' USING ERRCODE = 'integrity_constraint_violation'; END IF;
                IF OLD.state <> 'pending' OR NEW.order_id <> OLD.order_id OR NEW.previous_due_at <> OLD.previous_due_at
                   OR NEW.proposed_due_at <> OLD.proposed_due_at OR NEW.reason <> OLD.reason OR NEW.requested_by <> OLD.requested_by THEN
                    RAISE EXCEPTION 'Une demande de report décidée ne se modifie plus' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER extension_requests_decided_once BEFORE UPDATE OR DELETE ON extension_requests FOR EACH ROW EXECUTE FUNCTION freeci_extension_decided_once()');

        Schema::create('order_follow_ups', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $t->foreignUuid('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $t->string('kind', 40);                                      // review_silence : « besoin de suivi » ENREGISTRÉ, aucun contact n'est fait
            $t->timestampTz('recorded_at')->useCurrent();
            $t->unique(['delivery_id', 'kind']);
        });
        DB::statement('CREATE TRIGGER order_follow_ups_append_only BEFORE UPDATE OR DELETE ON order_follow_ups FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');
        DB::statement('CREATE TRIGGER correction_requests_append_only BEFORE UPDATE OR DELETE ON correction_requests FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        // Fichiers d'une livraison : même chaîne privée / quarantaine / contrôle que le brief ; gelés une fois la livraison soumise.
        Schema::table('file_assets', function (Blueprint $t) {
            $t->foreignUuid('delivery_id')->nullable()->constrained('deliveries')->restrictOnDelete();
            $t->index(['delivery_id', 'state']);
        });
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_delivery_files_frozen() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.delivery_id IS NOT NULL AND NEW.state = 'removed' AND OLD.state <> 'removed'
                   AND EXISTS (SELECT 1 FROM deliveries d WHERE d.id = OLD.delivery_id AND d.state = 'submitted') THEN
                    RAISE EXCEPTION 'Les fichiers d''une livraison soumise sont conservés' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER file_assets_delivery_frozen BEFORE UPDATE ON file_assets FOR EACH ROW EXECUTE FUNCTION freeci_delivery_files_frozen()');

        Schema::table('orders', function (Blueprint $t) {
            $t->foreignUuid('validated_delivery_id')->nullable()->constrained('deliveries')->restrictOnDelete();
            $t->timestampTz('validated_at')->nullable();
        });
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_closure_reason_chk');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_closure_reason_chk CHECK (closure_reason IS NULL OR closure_reason IN ('declined','withdrawn','cancelled_before_payment','expired_acceptance','expired_payment','validated'))");

        // Départ : jamais modifié. Échéance : modifiée UNIQUEMENT par un report accepté et enregistré (ancienne et nouvelle valeurs concordantes).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_order_start_once() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.started_at IS NOT NULL AND NEW.started_at IS DISTINCT FROM OLD.started_at THEN
                    RAISE EXCEPTION 'Le départ d''une commande ne s''enregistre qu''une seule fois' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF OLD.started_at IS NOT NULL AND NEW.due_at IS DISTINCT FROM OLD.due_at AND NOT EXISTS (
                    SELECT 1 FROM extension_requests e WHERE e.order_id = NEW.id AND e.state = 'accepted'
                      AND e.previous_due_at = OLD.due_at AND e.proposed_due_at = NEW.due_at) THEN
                    RAISE EXCEPTION 'L''échéance ne change que par un report accepté' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_order_start_once() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.started_at IS NOT NULL AND (NEW.started_at IS DISTINCT FROM OLD.started_at OR NEW.due_at IS DISTINCT FROM OLD.due_at) THEN
                    RAISE EXCEPTION 'Le départ et l''échéance d''une commande ne s''enregistrent qu''une seule fois' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_closure_reason_chk');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_closure_reason_chk CHECK (closure_reason IS NULL OR closure_reason IN ('declined','withdrawn','cancelled_before_payment','expired_acceptance','expired_payment'))");
        Schema::table('orders', fn (Blueprint $t) => $t->dropConstrainedForeignId('validated_delivery_id'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('validated_at'));
        DB::statement('DROP TRIGGER IF EXISTS file_assets_delivery_frozen ON file_assets');
        DB::statement('DROP FUNCTION IF EXISTS freeci_delivery_files_frozen()');
        Schema::table('file_assets', fn (Blueprint $t) => $t->dropConstrainedForeignId('delivery_id'));
        Schema::dropIfExists('order_follow_ups');
        Schema::dropIfExists('extension_requests');
        Schema::table('deliveries', fn (Blueprint $t) => $t->dropForeign(['correction_request_id']));
        Schema::dropIfExists('correction_requests');
        Schema::dropIfExists('deliveries');
        DB::statement('DROP FUNCTION IF EXISTS freeci_extension_decided_once()');
        DB::statement('DROP FUNCTION IF EXISTS freeci_delivery_frozen()');
        Schema::table('order_agreements', fn (Blueprint $t) => $t->dropColumn('delivery_requires_files'));
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn('delivery_requires_files'));
    }
};
