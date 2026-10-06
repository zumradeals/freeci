<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 10 — Genius Pay (bac à sable). Colonnes AJOUTÉES avec valeurs par défaut : les tentatives existantes restent des tentatives du
 * simulateur interne (`environment = 'simulator'`), sans réinterprétation. Trois environnements distincts : simulator | sandbox | live.
 * Le mode réel n'est pas activé par ce lot : la base impose seulement qu'un paiement non simulé soit explicitement « live ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->string('environment', 10)->default('simulator');                  // simulator | sandbox | live — conservé pour chaque tentative
            $t->string('provider_transaction_reference', 80)->nullable();          // référence attribuée par le prestataire (SANDBOX-…/MTX-…)
            $t->string('provider_transaction_id', 40)->nullable();
            $t->text('checkout_url')->nullable();                                  // checkout hébergé (hôte contrôlé avant stockage)
            $t->timestampTz('provider_expires_at')->nullable();
            $t->timestampTz('binding_verified_at')->nullable();                    // le prestataire a renvoyé NOTRE référence (external_reference) pour cette transaction
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_environment_chk CHECK (environment IN ('simulator','sandbox','live'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_provider_env_chk CHECK ((provider = 'sandbox' AND environment = 'simulator') OR (provider = 'genius_pay' AND environment IN ('sandbox','live')))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_live_real_chk CHECK ((environment = 'live') = (NOT is_simulated))");        // jamais d'argent « réel » hors mode réel, jamais l'inverse
        DB::statement('CREATE UNIQUE INDEX payments_provider_tx_uq ON payments (provider, provider_transaction_reference) WHERE provider_transaction_reference IS NOT NULL');

        // Dossiers de rapprochement : examen tracé (qui, quand, pourquoi). Aucun effet financier : un dossier examiné n'exécute rien.
        Schema::table('reconciliation_cases', function (Blueprint $t) {
            $t->foreignUuid('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('resolution_note')->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX reconciliation_cases_once_uq ON reconciliation_cases (order_id, payment_id, reason) WHERE payment_id IS NOT NULL AND resolved_at IS NULL');

        // Événements : enregistrés durablement AVANT traitement ; le traitement asynchrone met à jour `processing` / `outcome`.
        Schema::table('payment_events', function (Blueprint $t) {
            $t->string('processing', 14)->default('processed');                   // received | processed | needs_review (les lignes existantes sont traitées)
            $t->string('environment', 10)->nullable();
            $t->bigInteger('signature_timestamp')->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestampTz('processed_at')->nullable();
            $t->string('review_reason', 60)->nullable();
            $t->index(['processing', 'received_at']);
        });
        DB::statement("ALTER TABLE payment_events ADD CONSTRAINT payment_events_processing_chk CHECK (processing IN ('received','processed','needs_review'))");
        DB::statement("UPDATE payment_events SET processed_at = received_at WHERE processing = 'processed' AND processed_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reconciliation_cases_once_uq');
        Schema::table('reconciliation_cases', fn (Blueprint $t) => $t->dropConstrainedForeignId('resolved_by'));
        Schema::table('reconciliation_cases', fn (Blueprint $t) => $t->dropColumn('resolution_note'));
        DB::statement('ALTER TABLE payment_events DROP CONSTRAINT IF EXISTS payment_events_processing_chk');
        Schema::table('payment_events', fn (Blueprint $t) => $t->dropColumn(['processing', 'environment', 'signature_timestamp', 'attempts', 'processed_at', 'review_reason']));
        DB::statement('DROP INDEX IF EXISTS payments_provider_tx_uq');
        foreach (['payments_live_real_chk', 'payments_provider_env_chk', 'payments_environment_chk'] as $c) {
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS {$c}");
        }
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['environment', 'provider_transaction_reference', 'provider_transaction_id', 'checkout_url', 'provider_expires_at', 'binding_verified_at']));
    }
};
