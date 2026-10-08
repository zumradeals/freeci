<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Support\ImageProcessor;
use App\Modules\Support\Support\CaseRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo de profil : une photo active par personne, publique dès le dépôt (décision DEC-F01). Le dépôt est validé et RÉENCODÉ
 * (carré, WebP, métadonnées retirées) ; l'original n'est jamais conservé. Remplacée ou supprimée par la personne, la photo est effacée
 * aussitôt ; retirée par l'administration, elle est conservée jusqu'à `purge_after` (litige éventuel) puis effacée ; effacée à la fermeture du compte.
 */
final class ProfilePhotos
{
    public function __construct(private ImageProcessor $processor) {}

    public function set(User $user, UploadedFile $file): string
    {
        $out = $this->processor->square($file);
        $id = (string) Str::uuid();
        $dir = 'p/'.substr($id, 0, 2);
        $disk = Storage::disk('private_files');
        $disk->put("{$dir}/{$id}-l.webp", $out['large']);
        $disk->put("{$dir}/{$id}-s.webp", $out['small']);
        try {
            DB::transaction(function () use ($user, $id, $dir, $out) {
                DB::table('users')->where('id', $user->getKey())->lockForUpdate()->first();
                $this->endActive($user->getKey(), 'replaced', deleteFiles: true);
                DB::table('profile_photos')->insert(['id' => $id, 'user_id' => $user->getKey(), 'state' => 'active', 'key_large' => "{$dir}/{$id}-l.webp", 'key_small' => "{$dir}/{$id}-s.webp",
                    'mime' => $out['mime'], 'sha256' => $out['sha256'], 'created_at' => now()]);
            });
        } catch (\Throwable $e) {
            $disk->delete(["{$dir}/{$id}-l.webp", "{$dir}/{$id}-s.webp"]);
            throw $e;
        }

        return $id;
    }

    /** Suppression par la personne : la photo disparaît, les initiales reprennent. */
    public function delete(User $user): bool
    {
        return DB::transaction(fn () => $this->endActive($user->getKey(), 'deleted', deleteFiles: true));
    }

    /** Retrait par l'administration (l'audit, le motif et la notification sont gérés par l'action d'administration). */
    public function remove(string $userId, string $adminId, string $reason): void
    {
        $days = (int) config('freeci.account.removed_photo_days');
        $n = DB::table('profile_photos')->where('user_id', $userId)->where('state', 'active')
            ->update(['state' => 'removed', 'ended_at' => now(), 'removed_by' => $adminId, 'removal_reason' => $reason, 'purge_after' => now()->addDays($days)]);
        if ($n === 0) {
            throw new \DomainException('Cette personne n’a pas de photo de profil à retirer.');
        }
    }

    /** Efface les fichiers des photos retirées dont le délai de conservation est écoulé. */
    public function purgeExpired(): int
    {
        $rows = DB::table('profile_photos')->where('state', 'removed')->whereNotNull('key_large')->where('purge_after', '<', now())->get(['id', 'user_id', 'key_large', 'key_small'])
            ->reject(fn ($r) => $this->underReview($r->user_id));       // dossier d'assistance ouvert sur ce profil : la photo reste conservée
        foreach ($rows as $r) {
            Storage::disk('private_files')->delete(array_filter([$r->key_large, $r->key_small]));
            DB::table('profile_photos')->where('id', $r->id)->update(['key_large' => null, 'key_small' => null]);
        }

        return $rows->count();
    }

    /** Fermeture du compte : toutes les photos (fichiers compris) sont effacées. */
    public function purgeAll(string $userId): void
    {
        $rows = DB::table('profile_photos')->where('user_id', $userId)->get(['id', 'key_large', 'key_small', 'state']);
        foreach ($rows as $r) {
            Storage::disk('private_files')->delete(array_filter([$r->key_large, $r->key_small]));
        }
        DB::table('profile_photos')->where('user_id', $userId)->update(['key_large' => null, 'key_small' => null, 'state' => 'closed', 'ended_at' => DB::raw('coalesce(ended_at, now())'), 'removal_reason' => null, 'purge_after' => null]);
    }

    private function underReview(string $userId): bool
    {
        $profile = DB::table('freelance_profiles')->where('user_id', $userId)->value('id');

        return $profile !== null && DB::table('support_cases')->where('target_type', 'profile')->where('target_id', $profile)->whereIn('status', CaseRules::LIVE)->exists();
    }

    private function endActive(string $userId, string $state, bool $deleteFiles): bool
    {
        $row = DB::table('profile_photos')->where('user_id', $userId)->where('state', 'active')->first();
        if ($row === null) {
            return false;
        }
        if ($deleteFiles) {
            Storage::disk('private_files')->delete(array_filter([$row->key_large, $row->key_small]));
        }
        DB::table('profile_photos')->where('id', $row->id)->update(['state' => $state, 'ended_at' => now(), 'key_large' => $deleteFiles ? null : $row->key_large, 'key_small' => $deleteFiles ? null : $row->key_small]);

        return true;
    }
}
