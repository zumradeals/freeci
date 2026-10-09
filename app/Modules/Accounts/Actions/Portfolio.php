<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Support\ImageProcessor;
use App\Modules\Catalog\Support\PrivateContact;
use App\Modules\Files\Exceptions\FileRejected;
use App\Modules\Support\Support\CaseRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Réalisations d'un freelance (portfolio) : au plus `portfolio_max`, images contrôlées et réencodées (jamais d'original), textes publics sans
 * coordonnées privées ni lien. Publiques dès l'enregistrement une fois le profil publié ; signalables avec le profil ; retirables par l'administration
 * (motif, journal d'audit). Mêmes règles de conservation que la photo de profil.
 */
final class Portfolio
{
    public function __construct(private ImageProcessor $processor) {}

    public function add(User $user, UploadedFile $file, string $title, ?string $description, ?string $year): string
    {
        $this->ownsProfile($user);
        [$title, $description, $year] = $this->texts($title, $description, $year);
        if ($this->count($user->getKey()) >= (int) config('freeci.catalog.portfolio_max')) {
            throw new FileRejected('Au plus '.config('freeci.catalog.portfolio_max').' réalisations : supprimez-en une pour en ajouter.');
        }
        $out = $this->processor->process($file);
        $id = (string) Str::uuid();
        $dir = 'r/'.substr($id, 0, 2);
        $disk = Storage::disk('private_files');
        $disk->put("{$dir}/{$id}-l.webp", $out['large']);
        $disk->put("{$dir}/{$id}-c.webp", $out['card']);
        try {
            DB::transaction(function () use ($user, $id, $dir, $out, $title, $description, $year) {
                DB::table('users')->where('id', $user->getKey())->lockForUpdate()->first();
                if ($this->count($user->getKey()) >= (int) config('freeci.catalog.portfolio_max')) {
                    throw new FileRejected('Au plus '.config('freeci.catalog.portfolio_max').' réalisations : supprimez-en une pour en ajouter.');
                }
                DB::table('portfolio_items')->insert(['id' => $id, 'user_id' => $user->getKey(), 'state' => 'active', 'title' => $title, 'description' => $description, 'year' => $year,
                    'key_large' => "{$dir}/{$id}-l.webp", 'key_card' => "{$dir}/{$id}-c.webp", 'mime' => $out['mime'], 'sha256' => $out['sha256'], 'created_at' => now(), 'updated_at' => now()]);
            });
        } catch (\Throwable $e) {
            $disk->delete(["{$dir}/{$id}-l.webp", "{$dir}/{$id}-c.webp"]);
            throw $e;
        }

        return $id;
    }

    public function update(User $user, string $id, string $title, ?string $description, ?string $year): void
    {
        [$title, $description, $year] = $this->texts($title, $description, $year);
        $n = DB::table('portfolio_items')->where('id', $id)->where('user_id', $user->getKey())->where('state', 'active')->update(['title' => $title, 'description' => $description, 'year' => $year, 'updated_at' => now()]);
        if ($n === 0) {
            throw new FileRejected('Cette réalisation est introuvable.');
        }
    }

    public function delete(User $user, string $id): bool
    {
        $row = DB::table('portfolio_items')->where('id', $id)->where('user_id', $user->getKey())->where('state', 'active')->first();
        if ($row === null) {
            return false;
        }
        Storage::disk('private_files')->delete(array_filter([$row->key_large, $row->key_card]));
        DB::table('portfolio_items')->where('id', $id)->update(['state' => 'deleted', 'ended_at' => now(), 'key_large' => null, 'key_card' => null]);

        return true;
    }

    /** Retrait par l'administration (audit, motif et notification gérés par l'action d'administration). */
    public function remove(string $userId, string $itemId, string $adminId, string $reason): string
    {
        $row = DB::table('portfolio_items')->where('id', $itemId)->where('user_id', $userId)->where('state', 'active')->first();
        if ($row === null) {
            throw new \DomainException('Cette réalisation n’est pas en ligne.');
        }
        DB::table('portfolio_items')->where('id', $itemId)->update(['state' => 'removed', 'ended_at' => now(), 'removed_by' => $adminId, 'removal_reason' => $reason,
            'purge_after' => now()->addDays((int) config('freeci.account.removed_photo_days'))]);

        return (string) $row->title;
    }

    public function purgeExpired(): int
    {
        $rows = DB::table('portfolio_items')->where('state', 'removed')->whereNotNull('key_large')->where('purge_after', '<', now())->get(['id', 'user_id', 'key_large', 'key_card'])
            ->reject(fn ($r) => $this->underReview($r->user_id));
        foreach ($rows as $r) {
            Storage::disk('private_files')->delete(array_filter([$r->key_large, $r->key_card]));
            DB::table('portfolio_items')->where('id', $r->id)->update(['key_large' => null, 'key_card' => null]);
        }

        return $rows->count();
    }

    public function purgeAll(string $userId): void
    {
        foreach (DB::table('portfolio_items')->where('user_id', $userId)->get(['key_large', 'key_card']) as $r) {
            Storage::disk('private_files')->delete(array_filter([$r->key_large, $r->key_card]));
        }
        DB::table('portfolio_items')->where('user_id', $userId)->update(['key_large' => null, 'key_card' => null, 'state' => 'closed', 'title' => '', 'description' => null, 'removal_reason' => null, 'purge_after' => null, 'ended_at' => DB::raw('coalesce(ended_at, now())')]);
    }

    private function count(string $userId): int
    {
        return DB::table('portfolio_items')->where('user_id', $userId)->where('state', 'active')->count();
    }

    private function ownsProfile(User $user): void
    {
        if (! DB::table('freelance_profiles')->where('user_id', $user->getKey())->exists()) {
            throw new FileRejected('Activez d’abord votre espace freelance.');
        }
    }

    /** @return array{0: string, 1: ?string, 2: ?int} */
    private function texts(string $title, ?string $description, ?string $year): array
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));
        $description = $description === null ? null : trim((string) preg_replace('/[ \t]+/u', ' ', $description));
        $description = $description === '' ? null : $description;
        $errors = [];
        if (mb_strlen($title) < 3 || mb_strlen($title) > 80) {
            $errors['title'] = 'Le titre fait de 3 à 80 caractères.';
        }
        if ($description !== null && mb_strlen($description) > 300) {
            $errors['description'] = 'La description fait au plus 300 caractères.';
        }
        $y = null;
        if ($year !== null && trim($year) !== '') {
            if (! preg_match('/^\d{4}$/', trim($year)) || (int) $year < 1990 || (int) $year > (int) now()->year) {
                $errors['year'] = 'Indiquez une année valide (par exemple '.now()->year.') ou laissez vide.';
            } else {
                $y = (int) $year;
            }
        }
        foreach (['title' => $title, 'description' => $description ?? ''] as $field => $text) {
            if (! isset($errors[$field]) && (PrivateContact::found($text) || preg_match('~(https?://|www\.|\b[\w-]+\.(com|ci|net|org|fr|io|app|me)\b)~iu', $text))) {
                $errors[$field] = 'Retirez les coordonnées privées et les liens (adresse e-mail, téléphone, site) : ce texte est public.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$title, $description, $y];
    }

    private function underReview(string $userId): bool
    {
        $profile = DB::table('freelance_profiles')->where('user_id', $userId)->value('id');

        return $profile !== null && DB::table('support_cases')->where('target_type', 'profile')->where('target_id', $profile)->whereIn('status', CaseRules::LIVE)->exists();
    }
}
