<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Settings\AppSettings;
use App\Modules\Admin\Settings\SettingDefinitions;
use App\Modules\Admin\Settings\SettingsConflict;
use Illuminate\Support\Facades\Crypt;
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
    /** @param list<string> $clear secrets à retirer (retour à la valeur du serveur) */
    public function __invoke(User $admin, string $group, array $input, bool $approve, bool $confirm, string $reason, array $clear = [], string $livePhrase = ''): int
    {
        $def = SettingDefinitions::groups()[$group] ?? throw new SettingsConflict('Groupe de paramètres inconnu.');

        return $this->audit->run($admin, 'settings.update', 'settings', $group, $def['title'], $reason, function () use ($admin, $def, $input, $approve, $confirm, $reason, $clear, $livePhrase) {
            $reason = trim($reason);
            if (mb_strlen($reason) < 10) {
                throw new SettingsConflict('Indiquez le motif du changement (10 caractères minimum).');
            }
            if ($approve && ! $def['approvable']) {
                $approve = false;
            }
            $rows = AppSettings::rows();
            $new = [];
            $secrets = [];
            foreach ($def['keys'] as $key) {
                $d = SettingDefinitions::all()[$key];
                if ($d['secret']) {
                    $raw = trim((string) ($input[$key] ?? ''));
                    if ($raw !== '') {
                        if (mb_strlen($raw) > $d['length']) {
                            throw new SettingsConflict("{$d['label']} : {$d['length']} caractères au maximum.");
                        }
                        $secrets[$key] = $raw;
                    }

                    continue;
                }
                $new[$key] = $this->cast($key, $input[$key] ?? null);
            }
            $this->crossChecks($new);

            $changes = [];
            foreach ($new as $key => $value) {
                $current = SettingDefinitions::normalize($key, array_key_exists($key, $rows) ? $rows[$key]->value : AppSettings::default($key));
                $changed = $current !== $value;
                $wasApproved = ($rows[$key]->status ?? null) === 'approved';
                if ($changed || ($approve && ! $wasApproved)) {
                    $changes[$key] = [$current, $value, $changed];
                }
            }
            foreach ($secrets as $key => $plain) {
                $changes[$key] = ['[secret]', '[secret]', true];
            }
            $removals = array_values(array_filter($clear, fn ($k) => isset($rows[$k]) && (SettingDefinitions::all()[$k]['secret'] ?? false) && in_array($k, $def['keys'], true)));
            if ($changes === [] && $removals === []) {
                throw new SettingsConflict('Aucun changement à enregistrer.');
            }
            if (($def['financial'] ?? false) && ! $confirm) {
                throw new SettingsConflict('Confirmez que vous comprenez la portée de ce changement financier.');
            }
            if (($def['danger'] ?? false) && $this->opensRealMoney($new, $rows) && mb_strtoupper(trim($livePhrase)) !== 'PAIEMENT REEL') {
                throw new SettingsConflict('Pour activer le paiement réel, saisissez exactement la phrase : PAIEMENT REEL');
            }

            DB::transaction(function () use ($admin, $changes, $approve, $reason, $secrets, $removals) {
                foreach ($changes as $key => [$old, $value, $changed]) {
                    $status = $approve ? 'approved' : 'provisional';
                    $stored = isset($secrets[$key]) ? Crypt::encryptString($secrets[$key]) : $value;
                    DB::table('app_settings')->upsert([[
                        'key' => $key, 'value' => json_encode($stored), 'status' => $status, 'updated_by' => $admin->getKey(), 'updated_at' => now(),
                        'approved_by' => $approve ? $admin->getKey() : null, 'approved_at' => $approve ? now() : null,
                    ]], ['key'], ['value', 'status', 'updated_by', 'updated_at', 'approved_by', 'approved_at']);
                    DB::table('app_setting_changes')->insert(['key' => $key, 'old_value' => json_encode($old), 'new_value' => json_encode($value), 'status_after' => $status,
                        'reason' => $changed ? $reason : 'Approbation sans changement de valeur : '.$reason, 'actor_id' => $admin->getKey(), 'created_at' => now()]);
                }
                foreach ($removals as $key) {
                    DB::table('app_settings')->where('key', $key)->delete();
                    DB::table('app_setting_changes')->insert(['key' => $key, 'old_value' => json_encode('[secret]'), 'new_value' => json_encode('[retiré : valeur du serveur]'), 'status_after' => 'provisional',
                        'reason' => 'Retrait : '.$reason, 'actor_id' => $admin->getKey(), 'created_at' => now()]);
                }
            });
            AppSettings::forget();
            AppSettings::apply();

            return count($changes) + count($removals);
        });
    }

    private function cast(string $key, mixed $raw): mixed
    {
        $d = SettingDefinitions::all()[$key];
        $label = $d['label'];
        switch ($d['type']) {
            case 'bool':
                return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
            case 'select':
                $v = (string) $raw;
                if (! array_key_exists($v, $d['options'])) {
                    throw new SettingsConflict("{$label} : choisissez une des valeurs proposées.");
                }

                return $v;
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
                if ($key === 'payments.genius.base_url' && ! str_starts_with(strtolower($v), 'https://')) {
                    throw new SettingsConflict("{$label} : l’adresse doit commencer par https://.");
                }
                if ($d['type'] === 'email' && $v !== '' && ! filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    throw new SettingsConflict("{$label} : adresse e-mail invalide.");
                }

                return $v === '' ? null : $v;
        }
        if ($v < $d['min'] || $v > $d['max']) {
            $fmt = fn (int $n) => $d['type'] === 'percent' ? rtrim(rtrim(number_format($n / 100, 2, ',', ''), '0'), ',') : (string) $n;

            $unit = $d['unit'] ?? '';

            throw new SettingsConflict("{$label} : entre {$fmt($d['min'])} et {$fmt($d['max'])} {$unit}.");
        }

        return $v;
    }

    /** @param array<string, mixed> $new */
    private function crossChecks(array $new): void
    {
        foreach ($new as $key => $v) {
            if (str_ends_with($key, '.0') && isset($new[substr($key, 0, -1).'1']) && $v > $new[substr($key, 0, -1).'1']) {
                throw new SettingsConflict(SettingDefinitions::all()[$key]['label'].' : doit être inférieur ou égal au maximum.');
            }
        }
        if (isset($new['files.max_mb'], $new['files.max_total_mb']) && $new['files.max_mb'] > $new['files.max_total_mb']) {
            throw new SettingsConflict('La taille d’un fichier ne peut pas dépasser la taille totale.');
        }
    }

    /** Le changement ouvre-t-il le paiement réel (mode live, ou autorisation du live nouvellement donnée) ? */
    private function opensRealMoney(array $new, array $rows): bool
    {
        $was = fn (string $k) => SettingDefinitions::normalize($k, array_key_exists($k, $rows) ? $rows[$k]->value : AppSettings::default($k));

        return (($new['payments.mode'] ?? null) === 'live' && $was('payments.mode') !== 'live') || (($new['payments.live_authorized'] ?? false) === true && $was('payments.live_authorized') !== true);
    }
}
