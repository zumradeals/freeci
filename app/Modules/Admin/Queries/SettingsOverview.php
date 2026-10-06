<?php

namespace App\Modules\Admin\Queries;

use App\Modules\Admin\Legal\LegalDefaults;
use App\Modules\Admin\Legal\LegalPages;
use App\Modules\Admin\Settings\AppSettings;
use App\Modules\Admin\Settings\SettingDefinitions;
use App\Shared\Dates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Données des écrans « Paramètres » et « Pages légales » (lecture seule). */
final class SettingsOverview
{
    public static function show(string $type, mixed $v): string
    {
        return match (true) {
            $type === 'bool' => $v ? 'Oui' : 'Non',
            $v === null || $v === '' => '—',
            $type === 'percent' => rtrim(rtrim(number_format($v / 100, 2, ',', ''), '0'), ','),
            default => (string) $v,
        };
    }

    /** Valeur proposée dans un champ de saisie. */
    public static function input(string $type, mixed $v): string
    {
        return $type === 'percent' ? rtrim(rtrim(number_format(((int) $v) / 100, 2, ',', ''), '0'), ',') : (string) ($v ?? '');
    }

    public function settings(): array
    {
        $rows = AppSettings::rows();
        $approvers = DB::table('app_settings')->join('users', 'users.id', '=', 'app_settings.approved_by')->pluck('users.name', 'app_settings.key');
        $groups = [];
        foreach (SettingDefinitions::groups() as $g => $d) {
            $fields = [];
            foreach ($d['keys'] as $key) {
                $def = SettingDefinitions::all()[$key];
                $row = $rows[$key] ?? null;
                $value = $row ? $row->value : AppSettings::default($key);
                $fields[] = $def + ['key' => $key, 'input' => self::input($def['type'], $value), 'value' => $value, 'shown' => self::show($def['type'], $value), 'default' => self::show($def['type'], AppSettings::default($key)),
                    'custom' => $row !== null, 'status' => $d['approvable'] ? ($row->status ?? 'provisional') : null,
                    'approvedBy' => $approvers[$key] ?? null, 'approvedAt' => $row && $row->approved_at ? Dates::short(Carbon::parse($row->approved_at)) : null];
            }
            $groups[$g] = $d + ['id' => $g, 'fields' => $fields, 'allApproved' => $d['approvable'] && collect($fields)->every(fn ($f) => $f['status'] === 'approved')];
        }

        return $groups;
    }

    public function changes(int $limit = 15): array
    {
        return DB::table('app_setting_changes')->join('users', 'users.id', '=', 'app_setting_changes.actor_id')->orderByDesc('app_setting_changes.id')->limit($limit)
            ->get(['app_setting_changes.*', 'users.name as actor'])->map(function ($c) {
                $def = SettingDefinitions::all()[$c->key] ?? null;
                $type = $def['type'] ?? 'text';

                return ['label' => $def['label'] ?? $c->key, 'old' => self::show($type, json_decode($c->old_value, true)), 'new' => self::show($type, json_decode($c->new_value, true)), 'status' => $c->status_after,
                    'reason' => $c->reason, 'actor' => $c->actor, 'when' => Dates::format(Carbon::parse($c->created_at))];
            })->all();
    }

    public function legalPages(): array
    {
        $out = [];
        foreach (LegalDefaults::PAGES as $slug => $title) {
            $r = LegalPages::row($slug);
            $out[] = ['slug' => $slug, 'title' => $title, 'state' => LegalPages::state($slug), 'version' => (int) ($r->published_version ?? 0),
                'publishedAt' => $r && $r->published_at ? Dates::short(Carbon::parse($r->published_at)) : null, 'draftAt' => $r && $r->draft_updated_at ? Dates::short(Carbon::parse($r->draft_updated_at)) : null];
        }

        return $out;
    }

    public function legalHistory(string $slug): array
    {
        return DB::table('legal_page_history')->join('users', 'users.id', '=', 'legal_page_history.actor_id')->where('slug', $slug)->orderByDesc('legal_page_history.id')->limit(10)
            ->get(['legal_page_history.*', 'users.name as actor'])->map(fn ($h) => ['version' => $h->version, 'action' => $h->action === 'published' ? 'Publiée' : 'Retirée', 'reason' => $h->reason, 'actor' => $h->actor, 'when' => Dates::format(Carbon::parse($h->created_at))])->all();
    }
}
