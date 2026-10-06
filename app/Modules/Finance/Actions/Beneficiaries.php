<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Actions\AdminAudit;
use App\Modules\Finance\Support\FinanceConflict;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Destination de reversement d'un freelance : DÉCLARÉE par lui (chiffrée, jamais journalisée, jamais réaffichée en clair), VÉRIFIÉE par un administrateur.
 * Aucune vérification automatique n'est inventée : la procédure de vérification (justificatif, appel, essai) relève de l'exploitation et n'est pas définie ici.
 * Modifier la destination crée une NOUVELLE déclaration à vérifier : l'ancienne est désactivée, aucun reversement ne peut s'appuyer sur une destination non vérifiée.
 */
final class Beneficiaries
{
    public function __construct(private AdminAudit $audit) {}

    public function declare(User $freelancer, string $method, string $holder, string $destination): void
    {
        $holder = trim($holder);
        $destination = trim($destination);
        if (! in_array($method, ['mobile_money', 'bank_transfer', 'other'], true) || mb_strlen($holder) < 2 || mb_strlen($holder) > 120 || mb_strlen($destination) < 6 || mb_strlen($destination) > 120) {
            throw new FinanceConflict('Renseignez le moyen, le titulaire (2 à 120 caractères) et la destination (6 à 120 caractères).');
        }
        DB::transaction(function () use ($freelancer, $method, $holder, $destination) {
            DB::table('payout_beneficiaries')->where('user_id', $freelancer->getKey())->whereIn('status', ['pending', 'verified'])->update(['status' => 'disabled', 'disabled_at' => now(), 'updated_at' => now()]);
            DB::table('payout_beneficiaries')->insert(['id' => (string) Str::uuid(), 'user_id' => $freelancer->getKey(), 'method' => $method, 'holder_name' => $holder,
                'destination' => Crypt::encryptString($destination), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function verify(User $admin, string $id, string $note): void
    {
        $this->audit->run($admin, 'finance.beneficiary.verify', 'beneficiary', $id, null, $note, function () use ($admin, $id, $note) {
            if (mb_strlen(trim($note)) < 10) {
                throw new FinanceConflict('Indiquez comment la destination a été vérifiée (10 caractères minimum).');
            }
            $b = DB::table('payout_beneficiaries')->where('id', $id)->first();
            if ($b === null || $b->status !== 'pending') {
                throw new FinanceConflict('Déclaration introuvable ou déjà traitée.');
            }
            if ($b->user_id === $admin->getKey()) {
                // Conflit d'intérêts : refusé dès que l'administrateur est freelance d'une commande RÉELLE ; toléré (audit) pour tester en sandbox avec son propre compte.
                if (DB::table('orders')->where('freelancer_id', $admin->getKey())->where('environment', 'live')->exists()) {
                    throw new FinanceConflict('Conflit d’intérêts : vous ne pouvez pas vérifier votre propre destination de reversement, car vous êtes freelance de commandes réelles.');
                }
                $this->audit->record($admin, 'finance.sandbox_party', 'beneficiary', $id, null, null, 'done', 'Administrateur vérifiant sa propre destination : toléré (aucune commande réelle), à usage de test.');
            }
            DB::table('payout_beneficiaries')->where('id', $id)->where('status', 'pending')->update(['status' => 'verified', 'verified_by' => $admin->getKey(), 'verified_at' => now(), 'updated_at' => now()]);
        });
    }

    public function disable(User $admin, string $id, string $note): void
    {
        $this->audit->run($admin, 'finance.beneficiary.disable', 'beneficiary', $id, null, $note, function () use ($id, $note) {
            if (mb_strlen(trim($note)) < 10) {
                throw new FinanceConflict('Indiquez le motif (10 caractères minimum).');
            }
            $n = DB::table('payout_beneficiaries')->where('id', $id)->whereIn('status', ['pending', 'verified'])->update(['status' => 'disabled', 'disabled_at' => now(), 'updated_at' => now()]);
            if ($n !== 1) {
                throw new FinanceConflict('Déclaration introuvable ou déjà désactivée.');
            }
        });
    }
}
