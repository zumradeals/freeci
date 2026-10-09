<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 72 — confirmation d'un remboursement total SUR PREUVE du prestataire (décision du porteur après la recette en bac à sable : Genius Pay ne renvoie pas
 * de référence de remboursement, mais la lecture du paiement indique « remboursé »). La preuve (statut, montant lu, transaction, environnement, heure de lecture)
 * est CONSERVÉE sur l'opération. Une confirmation par API exige désormais : la référence de remboursement, OU un rapprochement manuel complet, OU cette preuve.
 * Aucune donnée existante n'est modifiée ; la contrainte reste posée NOT VALID (elle s'applique aux nouvelles écritures).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_operations', function (Blueprint $t) {
            $t->timestampTz('provider_proof_at')->nullable();
            $t->jsonb('provider_proof')->nullable();
        });
        DB::statement('ALTER TABLE financial_operations DROP CONSTRAINT IF EXISTS financial_operations_api_confirmed_chk');
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_api_confirmed_chk CHECK (state <> 'confirmed' OR execution_mode <> 'api' OR provider_refund_reference IS NOT NULL OR (reconciled_by IS NOT NULL AND reconciliation_reference IS NOT NULL AND reconciliation_proof IS NOT NULL) OR (provider_proof_at IS NOT NULL AND provider_proof IS NOT NULL)) NOT VALID");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE financial_operations DROP CONSTRAINT IF EXISTS financial_operations_api_confirmed_chk');
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_api_confirmed_chk CHECK (state <> 'confirmed' OR execution_mode <> 'api' OR provider_refund_reference IS NOT NULL OR (reconciled_by IS NOT NULL AND reconciliation_reference IS NOT NULL AND reconciliation_proof IS NOT NULL)) NOT VALID");
        Schema::table('financial_operations', fn (Blueprint $t) => $t->dropColumn(['provider_proof_at', 'provider_proof']));
    }
};
