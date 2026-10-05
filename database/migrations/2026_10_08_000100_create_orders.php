<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 2 — demande de prestation et commande (sans paiement).
 * Séparation structurelle : aucune colonne de paiement dans `orders` (le paiement aura sa propre table).
 * Accord, brief initial et historique : lignes append-only protégées par déclencheurs (docs/02 §4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Les services de démonstration du catalogue n'ont pas de vendeur connectable : demandes fermées.
            $table->boolean('accepts_requests')->default(true);
        });
        DB::statement('UPDATE services SET accepts_requests = false WHERE is_demo AND slug <> \'service-de-recette-mise-en-plan\'');

        DB::statement('CREATE SEQUENCE IF NOT EXISTS order_reference_seq START 1');

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 24)->unique();
            $table->foreignUuid('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('freelancer_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->string('state', 30);
            $table->string('closure_reason', 40)->nullable();
            $table->text('closure_note')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('response_deadline_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('payment_deadline_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->unsignedInteger('row_version')->default(1);
            $table->timestampsTz();

            $table->index(['client_id', 'state']);
            $table->index(['freelancer_id', 'state']);
        });
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_state_chk CHECK (state IN ('awaiting_acceptance','awaiting_payment','awaiting_brief','in_progress','delivered','revision_requested','validated','closed','disputed','cancelled','expired'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_closure_reason_chk CHECK (closure_reason IS NULL OR closure_reason IN ('declined','withdrawn','cancelled_before_payment','expired_acceptance','expired_payment'))");
        // Interdiction de commander son propre service, y compris au plus bas niveau.
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_not_self_chk CHECK (client_id <> freelancer_id)');
        // Une seule demande en attente de réponse par client et service (rempart contre la double soumission).
        DB::statement("CREATE UNIQUE INDEX orders_pending_request_uq ON orders (client_id, service_id) WHERE state = 'awaiting_acceptance'");

        // Accord figé : copie des conditions au moment de la demande, jamais recalculée depuis le service.
        Schema::create('order_agreements', function (Blueprint $table) {
            $table->foreignUuid('order_id')->primary()->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedInteger('service_row_version');
            $table->string('service_title', 160);
            $table->text('service_summary');
            $table->string('category_name', 120);
            $table->string('seller_name', 120);
            $table->text('scope');
            $table->unsignedBigInteger('price_xof');
            $table->unsignedSmallInteger('delivery_days');
            $table->unsignedSmallInteger('revisions_included');
            $table->jsonb('deliverables');
            $table->jsonb('exclusions');
            $table->jsonb('client_inputs');
            $table->unsignedSmallInteger('response_hours');
            $table->unsignedSmallInteger('payment_hours');
            $table->string('conditions_version', 40);
            $table->timestampTz('conditions_accepted_at');
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('order_briefs', function (Blueprint $table) {
            $table->foreignUuid('order_id')->primary()->constrained('orders')->restrictOnDelete();
            $table->jsonb('answers');                 // [{label, answer}] : libellés copiés de l'accord
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('type', 40);
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete(); // null = système
            $table->string('from_state', 30)->nullable();
            $table->string('to_state', 30)->nullable();
            $table->text('note')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->index(['order_id', 'id']);
        });

        // Idempotence : une même clé d'opération ne produit qu'un seul effet (docs/02 §8.2, command_receipt).
        Schema::create('command_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 60);
            $table->string('operation_key', 80);
            $table->char('request_hash', 64);
            $table->uuid('order_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['actor_id', 'action', 'operation_key']);
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_forbid_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'La table % est en ajout seul : % interdit', TG_TABLE_NAME, TG_OP USING ERRCODE = 'integrity_constraint_violation';
            END $$
        SQL);
        foreach (['order_agreements', 'order_events'] as $t) {
            DB::statement("CREATE TRIGGER {$t}_append_only BEFORE UPDATE OR DELETE ON {$t} FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('command_receipts');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_briefs');
        Schema::dropIfExists('order_agreements');
        Schema::dropIfExists('orders');
        DB::statement('DROP SEQUENCE IF EXISTS order_reference_seq');
        DB::statement('DROP FUNCTION IF EXISTS freeci_forbid_change()');
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn('accepts_requests'));
    }
};
