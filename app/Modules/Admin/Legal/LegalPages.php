<?php

namespace App\Modules\Admin\Legal;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Actions\AdminAudit;
use App\Modules\Admin\Settings\SettingsConflict;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pages d'information et textes légaux éditables. Trois états : texte de départ (brouillon du système) → brouillon de l'administrateur (jamais public)
 * → ADOPTÉ (publié par l'administrateur, version numérotée, historique en ajout seul). Seul un texte adopté perd le bandeau « brouillon ».
 * Format : paragraphes, `## Titre`, listes `- `, `**gras**`, `*italique*`, liens ; aucun HTML (neutralisé).
 */
final class LegalPages
{
    public const MAX = 30000;

    public function __construct(private AdminAudit $audit) {}

    public static function row(string $slug): ?object
    {
        return DB::table('legal_pages')->where('slug', $slug)->first();
    }

    public static function adopted(string $slug): bool
    {
        $r = self::row($slug);

        return $r !== null && $r->published_body !== null && $r->published_at !== null;
    }

    /** Texte affiché au public : la version adoptée, sinon le texte de départ. */
    public static function publicBody(string $slug): string
    {
        $r = self::row($slug);

        return $r !== null && $r->published_body !== null && $r->published_at !== null ? $r->published_body : LegalDefaults::body($slug);
    }

    /** Texte proposé à l'édition : brouillon de l'administrateur, sinon version adoptée, sinon texte de départ. */
    public static function editableBody(string $slug): string
    {
        $r = self::row($slug);

        return $r?->draft_body ?? $r?->published_body ?? LegalDefaults::body($slug);
    }

    /** @return 'default'|'draft'|'adopted'|'withdrawn' */
    public static function state(string $slug): string
    {
        $r = self::row($slug);

        return match (true) {
            $r === null => 'default',
            $r->published_body !== null && $r->published_at !== null => 'adopted',
            $r->published_body !== null => 'withdrawn',
            $r->draft_body !== null => 'draft',
            default => 'default',
        };
    }

    public static function render(string $markdown): string
    {
        $tokens = ['exploitant' => config('freeci.legal.operator_name'), 'adresse' => config('freeci.legal.operator_address'), 'immatriculation' => config('freeci.legal.operator_registration'),
            'directeur' => config('freeci.legal.publication_director'), 'hebergeur' => config('freeci.legal.host'), 'contact' => config('freeci.legal.contact_email')];
        foreach ($tokens as $k => $v) {
            $safe = filled($v) ? preg_replace('/([\\\\`*_{}\[\]<>#])/', '\\\\$1', (string) $v) : '*à renseigner*';
            $markdown = str_replace('{'.$k.'}', $safe, $markdown);
        }

        return Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function saveDraft(User $admin, string $slug, string $body): void
    {
        $this->audit->run($admin, 'legal.draft', 'legal_page', $slug, LegalDefaults::PAGES[$slug] ?? $slug, null, function () use ($admin, $slug, $body) {
            $body = $this->check($slug, $body);
            DB::table('legal_pages')->upsert([['slug' => $slug, 'draft_body' => $body, 'draft_updated_by' => $admin->getKey(), 'draft_updated_at' => now()]], ['slug'], ['draft_body', 'draft_updated_by', 'draft_updated_at']);
        });
    }

    /** Publie le brouillon courant comme texte ADOPTÉ (décision de l'administrateur, motif et version conservés). */
    public function publish(User $admin, string $slug, string $reason): int
    {
        return $this->audit->run($admin, 'legal.publish', 'legal_page', $slug, LegalDefaults::PAGES[$slug] ?? $slug, $reason, function () use ($admin, $slug, $reason) {
            $reason = trim($reason);
            if (mb_strlen($reason) < 10) {
                throw new SettingsConflict('Indiquez le motif de la publication (10 caractères minimum).');
            }

            return DB::transaction(function () use ($admin, $slug, $reason) {
                $row = DB::table('legal_pages')->where('slug', $slug)->lockForUpdate()->first();
                $body = $this->check($slug, $row?->draft_body ?? $row?->published_body ?? LegalDefaults::body($slug));
                $version = (int) ($row->published_version ?? 0) + 1;
                DB::table('legal_pages')->upsert([['slug' => $slug, 'draft_body' => $body, 'published_body' => $body, 'published_version' => $version, 'published_by' => $admin->getKey(), 'published_at' => now()]],
                    ['slug'], ['draft_body', 'published_body', 'published_version', 'published_by', 'published_at']);
                DB::table('legal_page_history')->insert(['slug' => $slug, 'version' => $version, 'action' => 'published', 'body' => $body, 'reason' => $reason, 'actor_id' => $admin->getKey(), 'created_at' => now()]);

                return $version;
            });
        });
    }

    /** Retire l'adoption : la page redevient un brouillon public (bandeau) ; le texte et l'historique sont conservés. */
    public function withdraw(User $admin, string $slug, string $reason): void
    {
        $this->audit->run($admin, 'legal.withdraw', 'legal_page', $slug, LegalDefaults::PAGES[$slug] ?? $slug, $reason, function () use ($admin, $slug, $reason) {
            $reason = trim($reason);
            if (mb_strlen($reason) < 10) {
                throw new SettingsConflict('Indiquez le motif du retrait (10 caractères minimum).');
            }
            DB::transaction(function () use ($admin, $slug, $reason) {
                $row = DB::table('legal_pages')->where('slug', $slug)->lockForUpdate()->first();
                if ($row === null || $row->published_at === null) {
                    throw new SettingsConflict('Cette page n’est pas adoptée.');
                }
                DB::table('legal_pages')->where('slug', $slug)->update(['published_at' => null]);
                DB::table('legal_page_history')->insert(['slug' => $slug, 'version' => $row->published_version, 'action' => 'withdrawn', 'body' => $row->published_body, 'reason' => $reason, 'actor_id' => $admin->getKey(), 'created_at' => now()]);
            });
        });
    }

    private function check(string $slug, string $body): string
    {
        if (! isset(LegalDefaults::PAGES[$slug])) {
            throw new SettingsConflict('Page inconnue.');
        }
        $body = trim(str_replace("\r\n", "\n", $body));
        if ($body === '' || mb_strlen($body) > self::MAX) {
            throw new SettingsConflict('Le texte doit contenir entre 1 et '.self::MAX.' caractères.');
        }

        return $body;
    }
}
