<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Actions\Portfolio;
use App\Modules\Accounts\Models\User;
use App\Modules\Notifications\Actions\Notify;
use Illuminate\Support\Facades\DB;

/** Retrait d'une réalisation par l'administration : motif obligatoire, journal d'audit, la personne en est informée avec le motif (mêmes règles que la photo de profil). */
final class RemovePortfolioItem
{
    public function __construct(private AdminAudit $audit, private Portfolio $portfolio, private Notify $notify) {}

    public function __invoke(User $admin, string $userId, string $itemId, string $reason): void
    {
        $label = DB::table('portfolio_items')->where('id', $itemId)->value('title');
        $this->audit->run($admin, 'user.portfolio_remove', 'user', $userId, $label, $reason, function () use ($admin, $userId, $itemId, $reason) {
            $reason = trim($reason);
            if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
                throw new \DomainException('Le motif est obligatoire (10 à 1000 caractères) : il est conservé dans le journal et communiqué à la personne.');
            }
            DB::transaction(function () use ($admin, $userId, $itemId, $reason) {
                $title = $this->portfolio->remove($userId, $itemId, $admin->getKey(), $reason);
                ($this->notify)($userId, 'portfolio_removed', 'portfolio_removed:'.$itemId, 'Une de vos réalisations a été retirée : '.$title, $reason, 'freelance.profile');
            });
        });
    }
}
