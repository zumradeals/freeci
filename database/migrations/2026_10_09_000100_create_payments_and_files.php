<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 3 — paiement SIMULÉ et pièces jointes privées du brief.
 * Trois couches séparées : commande (`orders`), tentative de paiement (`payments`), état financier (`ledger_*`).
 * Aucun prestataire réel : le seul fournisseur est « sandbox », étiqueté simulé, activable seulement pour des comptes
 * et des commandes de démonstration autorisés (voir `App\Modules\Finance\SandboxGate`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('sandbox_payments')->default(false));
        Schema::table('services', fn (Blueprint $t) => $t->boolean('brief_requires_files')->default(false));
        // Colonne ajoutée avec une valeur par défaut : ne déclenche pas l'interdiction de modification (accords existants = false).
        Schema::table('order_agreements', fn (Blueprint $t) => $t->boolean('brief_requires_files')->default(false));

        // Démarrage : enregistré UNE fois (immuable), seulement après paiement confirmé côté serveur et brief complet.
        Schema::table('orders', function (Blueprint $t) {
            $t->timestampTz('started_at')->nullable();
            $t->timestampTz('due_at')->nullable();
        });
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_start_pair_chk CHECK ((started_at IS NULL) = (due_at IS NULL))');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_work_requires_start_chk CHECK (state NOT IN ('in_progress','delivered','revision_requested','validated','closed') OR started_at IS NOT NULL)");
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_order_start_once() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.started_at IS NOT NULL AND (NEW.started_at IS DISTINCT FROM OLD.started_at OR NEW.due_at IS DISTINCT FROM OLD.due_at) THEN
                    RAISE EXCEPTION 'Le départ et l''échéance d''une commande ne s''enregistrent qu''une seule fois' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER orders_start_once BEFORE UPDATE ON orders FOR EACH ROW EXECUTE FUNCTION freeci_order_start_once()');

        Schema::create('payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $t->unsignedBigInteger('amount_xof');                  // lu dans l'accord, jamais dans la requête
            $t->char('currency', 3)->default('XOF');
            $t->string('provider', 30);
            $t->boolean('is_simulated')->default(true);
            $t->string('provider_reference', 80)->unique();
            $t->string('state', 20);
            $t->string('failure_code', 40)->nullable();
            $t->timestampTz('pending_at')->nullable();
            $t->timestampTz('confirmed_at')->nullable();
            $t->timestampTz('failed_at')->nullable();
            $t->timestampTz('last_checked_at')->nullable();
            $t->unsignedInteger('row_version')->default(1);
            $t->timestampsTz();
            $t->index(['order_id', 'state']);
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_state_chk CHECK (state IN ('created','pending','confirmed','failed','expired','unknown'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_chk CHECK (amount_xof > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_sandbox_chk CHECK (provider <> 'sandbox' OR is_simulated)");
        // Au plus un paiement confirmé par commande ; au plus une tentative « ouverte » (créée, en attente ou incertaine).
        DB::statement("CREATE UNIQUE INDEX payments_one_confirmed_uq ON payments (order_id) WHERE state = 'confirmed'");
        DB::statement("CREATE UNIQUE INDEX payments_one_open_uq ON payments (order_id) WHERE state IN ('created','pending','unknown')");

        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 30);
            $t->string('provider_event_id', 120);
            $t->foreignUuid('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $t->string('provider_reference', 80)->nullable();
            $t->string('type', 40);
            $t->jsonb('payload');
            $t->string('outcome', 30)->nullable();               // applied | ignored | rejected | reconciliation
            $t->unsignedInteger('duplicate_count')->default(0);
            $t->timestampTz('received_at')->useCurrent();
            $t->unique(['provider', 'provider_event_id']);
        });

        Schema::create('reconciliation_cases', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $t->foreignUuid('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $t->string('reason', 60);
            $t->jsonb('details')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('resolved_at')->nullable();
        });

        // Côté « prestataire » simulé : la vérité que `verify()` interroge. Aucune interface publique ne la modifie.
        Schema::create('sandbox_transactions', function (Blueprint $t) {
            $t->string('reference', 80)->primary();
            $t->unsignedBigInteger('amount_xof');
            $t->char('currency', 3);
            $t->string('status', 20);                              // pending | succeeded | failed | indeterminate
            $t->string('order_reference', 24);
            $t->timestampsTz();
        });

        // État financier : lots équilibrés (somme nulle), en ajout seul, un seul lot par événement métier.
        Schema::create('ledger_batches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('event_key', 120)->unique();
            $t->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $t->foreignUuid('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $t->string('kind', 40);
            $t->boolean('is_simulated')->default(true);
            $t->timestampTz('occurred_at')->useCurrent();
        });
        Schema::create('ledger_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('batch_id')->constrained('ledger_batches')->restrictOnDelete();
            $t->string('account', 60);
            $t->bigInteger('amount_xof');                         // signé : débit négatif, crédit positif
            $t->index('batch_id');
        });
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_ledger_balanced() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF (SELECT COALESCE(SUM(amount_xof), 0) FROM ledger_lines WHERE batch_id = NEW.batch_id) <> 0
                   OR (SELECT COUNT(*) FROM ledger_lines WHERE batch_id = NEW.batch_id) < 2 THEN
                    RAISE EXCEPTION 'Lot du registre non équilibré' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NULL;
            END $$
        SQL);
        DB::statement('CREATE CONSTRAINT TRIGGER ledger_lines_balanced AFTER INSERT ON ledger_lines DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION freeci_ledger_balanced()');
        foreach (['ledger_batches', 'ledger_lines'] as $table) {
            DB::statement("CREATE TRIGGER {$table}_append_only BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()");
        }

        // Pièces jointes privées : clé de stockage opaque ; téléchargeable UNIQUEMENT à l'état « clean ».
        Schema::create('file_assets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $t->foreignUuid('uploader_id')->constrained('users')->restrictOnDelete();
            $t->string('original_name', 255);
            $t->string('extension', 10);
            $t->string('detected_mime', 100);
            $t->unsignedBigInteger('size_bytes');
            $t->char('sha256', 64);
            $t->string('storage_key', 80)->unique();
            $t->string('state', 20);
            $t->string('rejection_reason', 120)->nullable();
            $t->unsignedSmallInteger('scan_attempts')->default(0);
            $t->string('last_scan_error', 200)->nullable();
            $t->timestampTz('scanned_at')->nullable();
            $t->timestampTz('removed_at')->nullable();
            $t->timestampsTz();
            $t->index(['order_id', 'state']);
        });
        DB::statement("ALTER TABLE file_assets ADD CONSTRAINT file_assets_state_chk CHECK (state IN ('quarantined','scanning','clean','rejected','removed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('file_assets');
        Schema::dropIfExists('ledger_lines');
        Schema::dropIfExists('ledger_batches');
        Schema::dropIfExists('sandbox_transactions');
        Schema::dropIfExists('reconciliation_cases');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        DB::statement('DROP TRIGGER IF EXISTS orders_start_once ON orders');
        DB::statement('DROP FUNCTION IF EXISTS freeci_order_start_once()');
        DB::statement('DROP FUNCTION IF EXISTS freeci_ledger_balanced()');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_work_requires_start_chk');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_start_pair_chk');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['started_at', 'due_at']));
        Schema::table('order_agreements', fn (Blueprint $t) => $t->dropColumn('brief_requires_files'));
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn('brief_requires_files'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('sandbox_payments'));
    }
};
