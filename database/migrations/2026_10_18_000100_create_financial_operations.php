<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 11 — remboursements, commissions, reversements. COMPLÈTE le registre existant (aucune comptabilité parallèle).
 *  - `order_agreements.commission_bp / commission_policy` : conditions financières FIGÉES à l'accord. Les accords existants restent NULS : leurs conditions
 *    financières sont « insuffisantes » (aucun reversement éligible), jamais inventées.
 *  - `payments.provider_fee_xof` : frais lus chez le prestataire (information ; leur répartition n'est pas décidée).
 *  - `ledger_batches` : lien de rectification (`reverses_batch_id`) et opération d'origine ; `ledger_lines` : un compte « escrow » d'une commande ne peut jamais être négatif.
 *  - `financial_operations` (+ approbations, historique) : demandé / approuvé / en cours / confirmé / échoué / à vérifier ; caractéristiques immuables ;
 *    un seul reversement actif ou confirmé par commande ; une seule opération de remboursement ouverte par paiement.
 *  - `payout_beneficiaries` : destination de reversement déclarée par le freelance, à vérifier par un administrateur.
 * Aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_agreements', function (Blueprint $t) {
            $t->unsignedSmallInteger('commission_bp')->nullable();                 // points de base : 1 000 = 10 % ; NULL = accord antérieur au lot 11
            $t->string('commission_policy', 40)->nullable();
        });
        DB::statement('ALTER TABLE order_agreements ADD CONSTRAINT order_agreements_commission_chk CHECK (commission_bp IS NULL OR commission_bp BETWEEN 0 AND 10000)');
        Schema::table('payments', fn (Blueprint $t) => $t->bigInteger('provider_fee_xof')->nullable());

        DB::statement('CREATE SEQUENCE IF NOT EXISTS financial_operation_reference_seq START 1');

        Schema::create('payout_beneficiaries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $t->string('method', 20);                                              // mobile_money | bank_transfer | other
            $t->string('holder_name', 120);
            $t->text('destination');                                               // chiffré par l'application (jamais en clair, jamais journalisé)
            $t->string('status', 10)->default('pending');                          // pending | verified | disabled
            $t->foreignUuid('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('verified_at')->nullable();
            $t->timestampTz('disabled_at')->nullable();
            $t->timestampsTz();
        });
        DB::statement("ALTER TABLE payout_beneficiaries ADD CONSTRAINT payout_beneficiaries_method_chk CHECK (method IN ('mobile_money','bank_transfer','other'))");
        DB::statement("ALTER TABLE payout_beneficiaries ADD CONSTRAINT payout_beneficiaries_status_chk CHECK (status IN ('pending','verified','disabled'))");
        DB::statement("CREATE UNIQUE INDEX payout_beneficiaries_one_live_uq ON payout_beneficiaries (user_id) WHERE status IN ('pending','verified')");

        Schema::create('financial_operations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('reference', 24)->unique();
            $t->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $t->string('kind', 10);                                                // refund | payout
            $t->string('scope', 10);                                               // total | partial (remboursement) ; full (reversement)
            $t->unsignedBigInteger('amount_xof');
            $t->char('currency', 3)->default('XOF');
            $t->string('environment', 10);                                         // sandbox | live : celui du paiement / de la commande, jamais le mode courant
            $t->boolean('is_simulated');
            $t->foreignUuid('payment_id')->constrained('payments')->restrictOnDelete();
            $t->foreignUuid('beneficiary_id')->nullable()->constrained('payout_beneficiaries')->restrictOnDelete();
            $t->unsignedBigInteger('support_decision_id')->nullable();
            $t->foreign('support_decision_id')->references('id')->on('support_decisions')->restrictOnDelete();
            // Décomposition du reversement (figée à la demande) : base = encaissé − remboursements ; commission au taux de l'accord ; part freelance = montant.
            $t->unsignedBigInteger('base_xof')->nullable();
            $t->unsignedSmallInteger('commission_bp')->nullable();
            $t->unsignedBigInteger('commission_xof')->nullable();
            $t->string('state', 12);                                               // requested | approved | in_progress | confirmed | failed | to_verify | rejected | cancelled
            $t->string('execution_mode', 8)->nullable();                           // api | manual
            $t->char('fingerprint', 64);                                           // empreinte de l'action exacte approuvée
            $t->string('operation_key', 80)->unique();
            $t->string('provider', 20)->nullable();
            $t->string('provider_reference', 80)->nullable()->unique();            // NOTRE référence sortante, enregistrée AVANT l'appel
            $t->string('provider_refund_reference', 80)->nullable();               // référence du remboursement attribuée par le prestataire
            $t->string('external_reference', 80)->nullable();                      // action manuelle : référence du transfert effectué
            $t->text('proof_note')->nullable();                                    // action manuelle : justificatif (description)
            $t->text('requested_reason');
            $t->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('executed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->string('failure_code', 60)->nullable();
            $t->string('uncertain_reason', 60)->nullable();
            $t->timestampTz('approved_at')->nullable();
            $t->timestampTz('executed_at')->nullable();
            $t->timestampTz('confirmed_at')->nullable();
            $t->timestampTz('failed_at')->nullable();
            $t->timestampTz('last_checked_at')->nullable();
            $t->unsignedInteger('row_version')->default(1);
            $t->timestampsTz();
            $t->index(['order_id', 'state']);
            $t->index(['state', 'kind']);
        });
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_kind_chk CHECK ((kind = 'refund' AND scope IN ('total','partial')) OR (kind = 'payout' AND scope = 'full'))");
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_state_chk CHECK (state IN ('requested','approved','in_progress','confirmed','failed','to_verify','rejected','cancelled'))");
        DB::statement('ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_amount_chk CHECK (amount_xof > 0)');
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_env_chk CHECK (environment IN ('sandbox','live') AND is_simulated = (environment <> 'live'))");
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_mode_chk CHECK (execution_mode IS NULL OR execution_mode IN ('api','manual'))");
        // Aucune API de reversement n'est documentée par Genius Pay : un reversement ne peut être que manuel (à lever explicitement le jour où une API est documentée).
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_payout_manual_chk CHECK (kind <> 'payout' OR execution_mode IS NULL OR execution_mode = 'manual')");
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_payout_fields_chk CHECK (kind <> 'payout' OR (beneficiary_id IS NOT NULL AND commission_bp IS NOT NULL AND commission_xof IS NOT NULL AND base_xof IS NOT NULL AND base_xof = amount_xof + commission_xof))");
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_refund_decision_chk CHECK (kind <> 'refund' OR support_decision_id IS NOT NULL)");
        // « Confirmé » n'existe jamais sans mode d'exécution et horodatage ; une confirmation manuelle exige référence externe, justificatif et auteur.
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_confirmed_chk CHECK (state <> 'confirmed' OR (execution_mode IS NOT NULL AND confirmed_at IS NOT NULL AND executed_by IS NOT NULL AND (execution_mode <> 'manual' OR (external_reference IS NOT NULL AND proof_note IS NOT NULL))))");
        // Un seul reversement actif ou confirmé par commande ; une seule opération de remboursement OUVERTE par paiement (aucun envoi concurrent) ; un seul remboursement total vivant par paiement.
        DB::statement("CREATE UNIQUE INDEX financial_operations_one_payout_uq ON financial_operations (order_id) WHERE kind = 'payout' AND state IN ('requested','approved','in_progress','to_verify','confirmed')");
        DB::statement("CREATE UNIQUE INDEX financial_operations_one_open_refund_uq ON financial_operations (payment_id) WHERE kind = 'refund' AND state IN ('requested','approved','in_progress','to_verify')");
        DB::statement("CREATE UNIQUE INDEX financial_operations_one_total_refund_uq ON financial_operations (payment_id) WHERE kind = 'refund' AND scope = 'total' AND state IN ('requested','approved','in_progress','to_verify','confirmed')");
        DB::statement("CREATE UNIQUE INDEX financial_operations_external_ref_uq ON financial_operations (kind, external_reference) WHERE external_reference IS NOT NULL AND state = 'confirmed'");
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_financial_operation_guard() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Une opération financière ne se supprime pas' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF NEW.order_id IS DISTINCT FROM OLD.order_id OR NEW.kind IS DISTINCT FROM OLD.kind OR NEW.scope IS DISTINCT FROM OLD.scope
                   OR NEW.amount_xof IS DISTINCT FROM OLD.amount_xof OR NEW.payment_id IS DISTINCT FROM OLD.payment_id OR NEW.beneficiary_id IS DISTINCT FROM OLD.beneficiary_id
                   OR NEW.environment IS DISTINCT FROM OLD.environment OR NEW.fingerprint IS DISTINCT FROM OLD.fingerprint OR NEW.operation_key IS DISTINCT FROM OLD.operation_key
                   OR NEW.base_xof IS DISTINCT FROM OLD.base_xof OR NEW.commission_xof IS DISTINCT FROM OLD.commission_xof OR NEW.commission_bp IS DISTINCT FROM OLD.commission_bp
                   OR NEW.reference IS DISTINCT FROM OLD.reference OR NEW.requested_by IS DISTINCT FROM OLD.requested_by THEN
                    RAISE EXCEPTION 'Les caractéristiques d''une opération financière ne peuvent pas être remplacées' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF OLD.state IN ('confirmed','failed','rejected','cancelled') THEN
                    RAISE EXCEPTION 'Une opération financière terminée ne se modifie plus' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER financial_operations_guard BEFORE UPDATE OR DELETE ON financial_operations FOR EACH ROW EXECUTE FUNCTION freeci_financial_operation_guard()');

        Schema::create('financial_operation_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('operation_id')->constrained('financial_operations')->restrictOnDelete();
            $t->foreignUuid('approver_id')->constrained('users')->restrictOnDelete();
            $t->string('decision', 10);                                            // approved | rejected
            $t->char('fingerprint', 64);                                           // empreinte de l'action exacte vue par l'approbateur
            $t->text('note');
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['operation_id', 'approver_id']);
        });
        DB::statement("ALTER TABLE financial_operation_approvals ADD CONSTRAINT financial_operation_approvals_decision_chk CHECK (decision IN ('approved','rejected'))");
        DB::statement('CREATE TRIGGER financial_operation_approvals_append_only BEFORE UPDATE OR DELETE ON financial_operation_approvals FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        Schema::create('financial_operation_events', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('operation_id')->constrained('financial_operations')->restrictOnDelete();
            $t->string('type', 40);
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();   // null = système
            $t->string('from_state', 12)->nullable();
            $t->string('to_state', 12)->nullable();
            $t->text('note')->nullable();
            $t->jsonb('meta')->nullable();                                         // jamais de secret ni de corps de réponse du prestataire
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['operation_id', 'id']);
        });
        DB::statement('CREATE TRIGGER financial_operation_events_append_only BEFORE UPDATE OR DELETE ON financial_operation_events FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        // Registre : rectification liée et opération d'origine.
        Schema::table('ledger_batches', function (Blueprint $t) {
            $t->uuid('reverses_batch_id')->nullable();
            $t->foreign('reverses_batch_id')->references('id')->on('ledger_batches')->restrictOnDelete();
            $t->uuid('operation_id')->nullable();
            $t->foreign('operation_id')->references('id')->on('financial_operations')->restrictOnDelete();
            $t->string('memo', 200)->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX ledger_batches_one_correction_uq ON ledger_batches (reverses_batch_id) WHERE reverses_batch_id IS NOT NULL');
        // Les fonds d'une commande ne peuvent jamais être engagés deux fois : le solde « escrow » (réel ou simulé) d'une commande ne devient jamais négatif.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_escrow_not_negative() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE oid uuid; bal bigint;
            BEGIN
                IF NEW.account NOT IN ('escrow', 'escrow_simulated') THEN RETURN NULL; END IF;
                SELECT order_id INTO oid FROM ledger_batches WHERE id = NEW.batch_id;
                SELECT COALESCE(SUM(l.amount_xof), 0) INTO bal FROM ledger_lines l JOIN ledger_batches b ON b.id = l.batch_id WHERE b.order_id = oid AND l.account = NEW.account;
                IF bal < 0 THEN
                    RAISE EXCEPTION 'Fonds de la commande insuffisants : solde négatif refusé' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NULL;
            END $$
        SQL);
        DB::statement('CREATE CONSTRAINT TRIGGER ledger_lines_escrow_not_negative AFTER INSERT ON ledger_lines DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION freeci_escrow_not_negative()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS ledger_lines_escrow_not_negative ON ledger_lines');
        DB::statement('DROP FUNCTION IF EXISTS freeci_escrow_not_negative()');
        DB::statement('DROP INDEX IF EXISTS ledger_batches_one_correction_uq');
        Schema::table('ledger_batches', function (Blueprint $t) {
            $t->dropForeign(['reverses_batch_id']);
            $t->dropForeign(['operation_id']);
            $t->dropColumn(['reverses_batch_id', 'operation_id', 'memo']);
        });
        foreach (['financial_operation_events', 'financial_operation_approvals'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_append_only ON {$table}");
            Schema::dropIfExists($table);
        }
        DB::statement('DROP TRIGGER IF EXISTS financial_operations_guard ON financial_operations');
        DB::statement('DROP FUNCTION IF EXISTS freeci_financial_operation_guard()');
        Schema::dropIfExists('financial_operations');
        Schema::dropIfExists('payout_beneficiaries');
        DB::statement('DROP SEQUENCE IF EXISTS financial_operation_reference_seq');
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('provider_fee_xof'));
        DB::statement('ALTER TABLE order_agreements DROP CONSTRAINT IF EXISTS order_agreements_commission_chk');
        Schema::table('order_agreements', fn (Blueprint $t) => $t->dropColumn(['commission_bp', 'commission_policy']));
    }
};
