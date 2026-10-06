<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 9 — assistance, signalements, litiges. Tables NOUVELLES ; modifications d'existant limitées à des CHECK élargis
 * (capacité « support », motif de clôture « annulée après paiement ») et à une colonne nullable de `file_assets` (pièces d'un dossier).
 * Aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE staff_grants DROP CONSTRAINT staff_grants_capability_chk');
        DB::statement("ALTER TABLE staff_grants ADD CONSTRAINT staff_grants_capability_chk CHECK (capability IN ('administrator','support'))");
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_closure_reason_chk');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_closure_reason_chk CHECK (closure_reason IS NULL OR closure_reason IN ('declined','withdrawn','cancelled_before_payment','expired_acceptance','expired_payment','validated','cancelled_after_payment'))");

        DB::statement('CREATE SEQUENCE IF NOT EXISTS support_case_reference_seq START 1');
        Schema::create('support_cases', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('reference', 20)->unique();                              // SU-AAMM-NNNNN
            $t->string('kind', 12);                                              // support | report | dispute | cancellation | claim | follow_up
            $t->string('status', 20);
            $t->foreignUuid('requester_id')->nullable()->constrained('users')->restrictOnDelete();       // null : dossier ouvert par l'équipe (besoin de suivi)
            $t->foreignUuid('counterparty_id')->nullable()->constrained('users')->restrictOnDelete();    // litige / annulation / réclamation : l'autre partie
            $t->foreignUuid('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $t->string('target_type', 10)->nullable();                           // profile | service | mission | message | order
            $t->string('target_id', 64)->nullable();
            $t->string('target_label', 200)->nullable();
            $t->text('target_snapshot')->nullable();                             // signalement d'un message : copie du SEUL message signalé
            $t->string('category', 30)->nullable();
            $t->string('subject', 160);
            $t->foreignUuid('assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('access_reason')->nullable();                               // « Ouvrir le dossier » : motif obligatoire, valable tant que l'affectation dure
            $t->timestampTz('access_opened_at')->nullable();
            $t->string('priority', 8)->default('normal');
            $t->string('origin', 12)->default('user');                           // user | follow_up
            $t->string('origin_ref', 40)->nullable();                            // identifiant du besoin de suivi d'origine
            $t->string('order_state_before', 30)->nullable();                    // état de la commande avant litige : pour la poursuite
            $t->unsignedInteger('row_version')->default(1);
            $t->timestampTz('opened_at')->useCurrent();
            $t->timestampTz('decided_at')->nullable();
            $t->timestampTz('closed_at')->nullable();
            $t->timestampsTz();
            $t->index(['status', 'kind', 'opened_at']);
            $t->index(['requester_id', 'opened_at']);
            $t->index(['counterparty_id', 'opened_at']);
            $t->index('assignee_id');
            $t->index('order_id');
        });
        DB::statement("ALTER TABLE support_cases ADD CONSTRAINT support_cases_kind_chk CHECK (kind IN ('support','report','dispute','cancellation','claim','follow_up'))");
        DB::statement("ALTER TABLE support_cases ADD CONSTRAINT support_cases_status_chk CHECK (status IN ('open','in_review','awaiting_requester','awaiting_party','decided','closed'))");
        DB::statement("ALTER TABLE support_cases ADD CONSTRAINT support_cases_priority_chk CHECK (priority IN ('normal','high'))");
        DB::statement('ALTER TABLE support_cases ADD CONSTRAINT support_cases_parties_chk CHECK (requester_id IS NULL OR counterparty_id IS NULL OR requester_id <> counterparty_id)');
        DB::statement('ALTER TABLE support_cases ADD CONSTRAINT support_cases_no_self_handling_chk CHECK (assignee_id IS NULL OR (assignee_id IS DISTINCT FROM requester_id AND assignee_id IS DISTINCT FROM counterparty_id))');
        DB::statement("ALTER TABLE support_cases ADD CONSTRAINT support_cases_order_chk CHECK (kind NOT IN ('dispute','cancellation','claim','follow_up') OR order_id IS NOT NULL)");
        DB::statement("CREATE UNIQUE INDEX support_cases_one_live_dispute_uq ON support_cases (order_id) WHERE kind IN ('dispute','cancellation') AND status NOT IN ('decided','closed')");
        DB::statement("CREATE UNIQUE INDEX support_cases_follow_up_once_uq ON support_cases (origin_ref) WHERE origin = 'follow_up'");

        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('case_id')->constrained('support_cases')->restrictOnDelete();
            $t->foreignUuid('author_id')->nullable()->constrained('users')->restrictOnDelete();   // null : système
            $t->string('visibility', 10);                                        // requester (demandeur + équipe) | parties (les deux parties + équipe) | internal (équipe seule)
            $t->text('body');
            $t->string('client_key', 40)->nullable();                            // double envoi : même clé = même message
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['case_id', 'id']);
            $t->unique(['case_id', 'author_id', 'client_key']);
        });
        DB::statement("ALTER TABLE support_messages ADD CONSTRAINT support_messages_visibility_chk CHECK (visibility IN ('requester','parties','internal'))");
        DB::statement('CREATE TRIGGER support_messages_append_only BEFORE UPDATE OR DELETE ON support_messages FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        Schema::create('support_events', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('case_id')->constrained('support_cases')->restrictOnDelete();
            $t->string('type', 30);
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('visibility', 10)->default('internal');                  // ce que le demandeur / les parties peuvent voir de l'historique
            $t->string('note', 300)->nullable();
            $t->jsonb('meta')->nullable();
            $t->timestampTz('occurred_at')->useCurrent();
            $t->index(['case_id', 'id']);
        });
        DB::statement("ALTER TABLE support_events ADD CONSTRAINT support_events_visibility_chk CHECK (visibility IN ('requester','parties','internal'))");
        DB::statement('CREATE TRIGGER support_events_append_only BEFORE UPDATE OR DELETE ON support_events FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        // Décision : UNE par dossier (index unique). Trois choses distinctes : la décision, son effet sur la commande, l'éventuelle suite financière.
        Schema::create('support_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('case_id')->unique()->constrained('support_cases')->restrictOnDelete();
            $t->string('outcome', 20);                                           // continue | validate_delivery | cancel | answered
            $t->text('reason');                                                  // motif communiqué aux parties
            $t->string('order_state_from', 30)->nullable();                      // effet sur la commande (appliqué dans la même transaction)
            $t->string('order_state_to', 30)->nullable();
            $t->string('financial_need', 12)->default('none');                   // none | refund | release | partial : BESOIN constaté, jamais une exécution
            $t->string('financial_status', 12)->default('none');                 // none | to_process : « à traiter financièrement » ; aucun statut « exécuté » n'existe ici
            $t->string('financial_note', 500)->nullable();
            $t->foreignUuid('decided_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE support_decisions ADD CONSTRAINT support_decisions_outcome_chk CHECK (outcome IN ('continue','validate_delivery','cancel','answered'))");
        DB::statement("ALTER TABLE support_decisions ADD CONSTRAINT support_decisions_need_chk CHECK (financial_need IN ('none','refund','release','partial'))");
        DB::statement("ALTER TABLE support_decisions ADD CONSTRAINT support_decisions_fin_chk CHECK (financial_status IN ('none','to_process') AND ((financial_need = 'none') = (financial_status = 'none')))");
        DB::statement('CREATE TRIGGER support_decisions_append_only BEFORE UPDATE OR DELETE ON support_decisions FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        // Blocage INTERNE des futurs reversements d'une commande en dossier. N'est pas un blocage chez un prestataire de paiement :
        // le futur module financier DOIT refuser tout reversement tant qu'un blocage est actif (`released_at IS NULL`).
        Schema::create('payout_holds', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $t->foreignUuid('case_id')->unique()->constrained('support_cases')->restrictOnDelete();
            $t->string('reason', 200);
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('released_at')->nullable();
            $t->foreignUuid('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('release_reason', 200)->nullable();
            $t->index(['order_id', 'released_at']);
        });

        // Pièces d'un dossier : même table et même chaîne de contrôle que le brief, les livraisons et les messages.
        Schema::table('file_assets', function (Blueprint $t) {
            $t->unsignedBigInteger('support_message_id')->nullable();
            $t->foreign('support_message_id')->references('id')->on('support_messages')->restrictOnDelete();
            $t->index('support_message_id');
        });
        DB::statement('ALTER TABLE file_assets DROP CONSTRAINT file_assets_owner_chk');
        DB::statement('ALTER TABLE file_assets DROP CONSTRAINT file_assets_message_exclusive_chk');
        DB::statement('ALTER TABLE file_assets ADD CONSTRAINT file_assets_owner_chk CHECK (order_id IS NOT NULL OR message_id IS NOT NULL OR support_message_id IS NOT NULL)');
        DB::statement('ALTER TABLE file_assets ADD CONSTRAINT file_assets_message_exclusive_chk CHECK ((message_id IS NULL AND support_message_id IS NULL) OR (order_id IS NULL AND delivery_id IS NULL AND (message_id IS NULL OR support_message_id IS NULL)))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE file_assets DROP CONSTRAINT file_assets_message_exclusive_chk');
        DB::statement('ALTER TABLE file_assets DROP CONSTRAINT file_assets_owner_chk');
        Schema::table('file_assets', fn (Blueprint $t) => $t->dropConstrainedForeignId('support_message_id'));
        DB::statement('ALTER TABLE file_assets ADD CONSTRAINT file_assets_owner_chk CHECK (order_id IS NOT NULL OR message_id IS NOT NULL)');
        DB::statement('ALTER TABLE file_assets ADD CONSTRAINT file_assets_message_exclusive_chk CHECK (message_id IS NULL OR (order_id IS NULL AND delivery_id IS NULL))');
        Schema::dropIfExists('payout_holds');
        foreach (['support_decisions', 'support_events', 'support_messages'] as $t) {
            DB::statement("DROP TRIGGER IF EXISTS {$t}_append_only ON {$t}");
            Schema::dropIfExists($t);
        }
        Schema::dropIfExists('support_cases');
        DB::statement('DROP SEQUENCE IF EXISTS support_case_reference_seq');
        DB::statement("UPDATE orders SET closure_reason = 'cancelled_before_payment' WHERE closure_reason = 'cancelled_after_payment'");
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_closure_reason_chk');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_closure_reason_chk CHECK (closure_reason IS NULL OR closure_reason IN ('declined','withdrawn','cancelled_before_payment','expired_acceptance','expired_payment','validated'))");
        DB::statement("DELETE FROM staff_grants WHERE capability = 'support'");
        DB::statement('ALTER TABLE staff_grants DROP CONSTRAINT staff_grants_capability_chk');
        DB::statement("ALTER TABLE staff_grants ADD CONSTRAINT staff_grants_capability_chk CHECK (capability IN ('administrator'))");
    }
};
