<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use Illuminate\Support\Facades\DB;

/** Marquer un dossier de rapprochement comme EXAMINÉ (qui, quand, pourquoi). N'exécute, ne relance et ne corrige aucun paiement ni aucune commande. */
final class ReviewReconciliation
{
    public function __construct(private AdminAudit $audit) {}

    public function __invoke(User $admin, string $caseId, string $note): void
    {
        $this->audit->run($admin, 'payment.reconciliation_review', 'payment', $caseId, 'Rapprochement', $note, function () use ($admin, $caseId, $note) {
            $note = trim($note);
            if (mb_strlen($note) < 10 || mb_strlen($note) > 1000) {
                throw new \DomainException('Indiquez la conclusion de l’examen (10 à 1000 caractères).');
            }
            $n = DB::table('reconciliation_cases')->where('id', $caseId)->whereNull('resolved_at')->update(['resolved_at' => now(), 'resolved_by' => $admin->getKey(), 'resolution_note' => $note]);
            if ($n !== 1) {
                throw new \DomainException('Ce dossier est introuvable ou déjà examiné.');
            }
        });
    }
}
