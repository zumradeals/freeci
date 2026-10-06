<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Settings\AppSettings;
use App\Modules\Admin\Settings\SettingDefinitions;
use App\Modules\Admin\Settings\SettingsConflict;
use Illuminate\Support\Facades\DB;

/**
 * Enregistrement des paramètres d'un groupe : valeurs contrôlées (bornes), motif obligatoire, historique en ajout seul, journal d'audit.
 * Une valeur modifiée redevient « provisoire » ; elle n'est « approuvée » que si l'administrateur le demande explicitement.
 * Un seul administrateur suffit (règle validée) : aucune seconde approbation. Les paramètres financiers exigent en plus une confirmation explicite.
 * Ne touche jamais une commande, un accord ou un montant existants : les valeurs figées à la création ne changent pas.
 */
final class UpdateSettings
{
    public function __construct(private AdminAudit $audit) {}

    /** @param array<string, mixed> $input clé => saisie brute (clés du groupe) */
    public function __invoke(User $admin, string $group, array $input, bool $approve, bool $confirm, string $reason): int
    {
        $def = SettingDefinitions::groups()[$group] ?? throw new SettingsConflict('Groupe de paramètres inconnu.');

        return $this->audit->run($admin, 'settings.update', 'settings', $group, $def['title'], $reason, function () use ($admin, $def, $input, $approve, $confirm, $reason) {
            $reason = trim($reason);
            if (mb_strlen($reason) < 10) {
                throw new SettingsConflict('Indiquez le motif du changement (10 caractères minimum).');
            }
            if ($approve && ! $def['approvable']) {
                $approve = false;
            }
            $rows = AppSettings::rows();
            $new = [];
            foreach ($def['keys'] as $key) {
                $new[$key] = $this->cast($key, $input[$key] ?? null);
            }
            $this->crossChecks($new);

            $changes = [];
            foreach ($new as $key => $value) {
                $current = array_key_exists($key, $rows) ? $rows[$key]->value : AppSettings::default($key);
                $changed = $current !== $value;
                $wasApproved = ($rows[$key]->status ?? null) === 'approved';
                if ($changed || ($approve && ! $wasApproved)) {
                    $changes[$key] = [$current, $value, $changed];
                }
            }
            if ($changes === []) {
                throw new SettingsConflict('Aucun changement à enregistrer.');
            }
            if (($def['financial'] ?? false) && ! $confirm) {
                throw new SettingsConflict('Confirmez que vous comprenez la portée de ce changement financier.');
            }

            DB::transaction(function () use ($admin, $changes, $approve, $reason) {
                foreach ($changes as $key => [$old, $value, $changed]) {
                    $status = $approve ? 'approved' : 'provisional';
                    DB::table('app_settings')->upsert([[
                        'key' => $key, 'value' => json_encode($value), 'status' => $status, 'updated_by' => $admin->getKey(), 'updated_at' => now(),
                        'approved_by' => $approve ? $admin->getKey() : null, 'approved_at' => $approve ? now() : null,
                    ]], ['key'], ['value', 'status', 'updated_by', 'updated_at', 'approved_by', 'approved_at']);
                    DB::table('app_setting_changes')->insert(['key' => $key, 'old_value' => json_encode($old), 'new_value' => json_encode($value), 'status_after' => $status,
                        'reason' => $changed ? $reason : 'Approbation sans changement de valeur : '.$reason, 'actor_id' => $admin->getKey(), 'created_at' => now()]);
                }
            });
            AppSettings::forget();
            AppSettings::apply();

            return count($changes);
        });
    }

    private function cast(string $key, mixed $raw): mixed
    {
        $d = SettingDefinitions::all()[$key];
        $label = $d['label'];
        switch ($d['type']) {
            case 'bool':
                return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
            case 'percent':
                $s = str_replace(',', '.', trim((string) $raw));
                if ($s === '' || ! is_numeric($s)) {
                    throw new SettingsConflict("{$label} : saisissez un pourcentage (ex. 10 ou 7,5).");
                }
                $v = (int) round(((float) $s) * 100);
                break;
            case 'int':
            case 'xof':
                $s = trim((string) $raw);
                if ($s === '' || ! preg_match('/^\d+$/', preg_replace('/\s+/', '', $s) ?? '')) {
                    throw new SettingsConflict("{$label} : saisissez un nombre entier.");
                }
                $v = (int) preg_replace('/\s+/', '', $s);
                break;
            default:
                $v = trim(preg_replace('/\s+/u', ' ', (string) $raw) ?? '');
                if (mb_strlen($v) > ($d['length'] ?? 200)) {
                    throw new SettingsConflict("{$label} : {$d['length']} caractères au maximum.");
                }
                if ($d['type'] === 'email' && $v !== '' && ! filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    throw new SettingsConflict("{$label} : adresse e-mail invalide.");
                }

                return $v === '' ? null : $v;
        }
        if ($v < $d['min'] || $v > $d['max']) {
            $fmt = fn (int $n) => $d['type'] === 'percent' ? rtrim(rtrim(number_format($n / 100, 2, ',', ''), '0'), ',') : (string) $n;

            throw new SettingsConflict("{$label} : entre {$fmt($d['min'])} et {$fmt($d['max'])} {$d['unit']}.");
        }

        return $v;
    }

    /** @param array<string, mixed> $new */
    private function crossChecks(array $new): void
    {
        if (isset($new['catalog.price_xof.0'], $new['catalog.price_xof.1']) && $new['catalog.price_xof.0'] >= $new['catalog.price_xof.1']) {
            throw new SettingsConflict('Le prix minimum doit être inférieur au prix maximum.');
        }
    }
}
