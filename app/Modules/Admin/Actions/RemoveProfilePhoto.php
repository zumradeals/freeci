<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Actions\ProfilePhotos;
use App\Modules\Accounts\Models\User;
use App\Modules\Notifications\Actions\Notify;
use Illuminate\Support\Facades\DB;

/**
 * Retrait d'une photo de profil par l'administration (DEC-F01) : motif obligatoire, inscrit au journal d'audit, la personne en est informée avec le motif.
 * La photo cesse d'être servie aussitôt ; le fichier est conservé le temps prévu (config) puis effacé. Jamais sur son propre compte.
 */
final class RemoveProfilePhoto
{
    public function __construct(private AdminAudit $audit, private ProfilePhotos $photos, private Notify $notify) {}

    public function __invoke(User $admin, string $userId, string $reason): void
    {
        $label = DB::table('users')->where('id', $userId)->value('name');
        $this->audit->run($admin, 'user.photo_remove', 'user', $userId, $label, $reason, function () use ($admin, $userId, $reason) {
            $reason = trim($reason);
            if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
                throw new \DomainException('Le motif est obligatoire (10 à 1000 caractères) : il est conservé dans le journal et communiqué à la personne.');
            }
            if ($userId === $admin->getKey()) {
                throw new \DomainException('Vous ne pouvez pas retirer votre propre photo ici : supprimez-la depuis votre compte.');
            }
            DB::transaction(function () use ($admin, $userId, $reason) {
                $this->photos->remove($userId, $admin->getKey(), $reason);
                $id = DB::table('profile_photos')->where('user_id', $userId)->where('state', 'removed')->orderByDesc('ended_at')->value('id');
                ($this->notify)($userId, 'photo_removed', 'photo_removed:'.$id, 'Votre photo de profil a été retirée', $reason, 'account.settings');
            });
        });
    }
}
