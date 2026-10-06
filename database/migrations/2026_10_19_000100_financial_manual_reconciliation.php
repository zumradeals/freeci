<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 11 (correctif) — rapprochement MANUEL documenté d'un remboursement incertain.
 * Le statut « refunded » ou « completed » lu chez le prestataire ne prouve ni le montant remboursé, ni son rattachement à notre demande, ni un échec :
 * seule une constatation humaine, avec référence et justificatif, confirme ou libère une opération « à vérifier ».
 * Aucune donnée existante n'est modifiée ; la contrainte est posée NOT VALID (elle s'applique aux nouvelles écritures).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_operations', function (Blueprint $t) {
            $t->foreignUuid('reconciled_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('reconciled_at')->nullable();
            $t->string('reconciliation_reference', 80)->nullable();               // référence constatée (remboursement, ou contrôle du tableau de bord)
            $t->text('reconciliation_proof')->nullable();                          // justificatif décrit par l'administrateur
        });
        // Un remboursement confirmé « par API » exige la référence de remboursement renvoyée par le prestataire OU un rapprochement manuel complet.
        DB::statement("ALTER TABLE financial_operations ADD CONSTRAINT financial_operations_api_confirmed_chk CHECK (state <> 'confirmed' OR execution_mode <> 'api' OR provider_refund_reference IS NOT NULL OR (reconciled_by IS NOT NULL AND reconciliation_reference IS NOT NULL AND reconciliation_proof IS NOT NULL)) NOT VALID");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE financial_operations DROP CONSTRAINT IF EXISTS financial_operations_api_confirmed_chk');
        Schema::table('financial_operations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reconciled_by');
            $t->dropColumn(['reconciled_at', 'reconciliation_reference', 'reconciliation_proof']);
        });
    }
};
