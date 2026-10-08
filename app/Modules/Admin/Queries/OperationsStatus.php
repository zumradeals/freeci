<?php

namespace App\Modules\Admin\Queries;

use App\Integrations\FileScan\FileScanner;
use App\Integrations\Payments\GeniusPayConfig;
use App\Integrations\Payments\PaymentMode;
use App\Modules\Admin\Actions\InstallDemoData;
use App\Modules\Admin\Actions\PurgeDemoData;
use App\Modules\Admin\Legal\LegalDefaults;
use App\Modules\Admin\Legal\LegalPages;
use App\Modules\Admin\Settings\AppSettings;
use App\Modules\Admin\Settings\SettingDefinitions;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * État utile de l'exploitation, en LECTURE SEULE, pour l'administrateur : tâches planifiées, file et courriels, contrôle des fichiers, rapprochements,
 * sauvegardes. Ni secret, ni chemin de fichier privé, ni détail technique sensible : seulement des états et des âges.
 * Rien ici n'active ni ne bloque quoi que ce soit : la liste « préparation à l'ouverture » est INFORMATIVE.
 */
final class OperationsStatus
{
    /** tâche => [libellé, minutes attendues entre deux passages] */
    public const TASKS = [
        'orders:expire' => ['Expiration des demandes et paiements', 5], 'files:scan' => ['Contrôle des fichiers', 5], 'media:prune' => ['Nettoyage des images', 1440], 'photos:purge' => ['Effacement des photos retirées', 1440],
        'notifications:retry' => ['Reprise des courriels', 10], 'payments:reconcile' => ['Rapprochement des paiements', 5], 'finance:reconcile' => ['Rapprochement des remboursements', 5],
        'reviews:publish' => ['Publication des avis', 60], 'accounts:close' => ['Fermetures de comptes', 60],
    ];

    public const PAGES = ['fonctionnement' => 'Comment ça marche', 'aide' => 'Aide', 'contact' => 'Contact', 'conditions' => 'Conditions d’utilisation', 'confidentialite' => 'Confidentialité', 'mentions-legales' => 'Mentions légales'];

    public function __invoke(): array
    {
        $backup = $this->backup();

        return ['tasks' => $this->tasks(), 'queue' => $this->queue(), 'mail' => $this->mail(), 'files' => $this->files(), 'payments' => $this->payments(), 'backup' => $backup, 'demo' => PurgeDemoData::counts(), 'demoDiag' => InstallDemoData::diagnostic(), 'readiness' => $this->readiness($backup)];
    }

    private function tasks(): array
    {
        $rows = DB::table('system_task_runs')->get()->keyBy('task');
        $out = [];
        foreach (self::TASKS as $task => [$label, $every]) {
            $r = $rows[$task] ?? null;
            $ok = $r?->last_ok_at ? Carbon::parse($r->last_ok_at) : null;
            $ko = $r?->last_failed_at ? Carbon::parse($r->last_failed_at) : null;
            $state = match (true) {
                $ok === null && $ko === null => 'never', $ko !== null && ($ok === null || $ko > $ok) => 'failed',
                $ok->lt(now()->subMinutes($every * 3 + 5)) => 'late', default => 'ok',
            };
            $out[] = ['task' => $task, 'label' => $label, 'state' => $state, 'ok' => $ok?->diffForHumans(), 'failed' => $ko?->diffForHumans()];
        }

        return $out;
    }

    private function queue(): array
    {
        $oldest = DB::table('jobs')->min('available_at');
        $age = $oldest === null ? null : (int) max(0, now()->timestamp - (int) $oldest);

        return ['driver' => (string) config('queue.default'), 'pending' => DB::table('jobs')->count(), 'oldestSeconds' => $age, 'failed' => DB::table('failed_jobs')->count(),
            'stuck' => $age !== null && $age > 600, 'viaScheduler' => (bool) config('freeci.notifications.queue_via_scheduler')];
    }

    private function mail(): array
    {
        $by = DB::table('app_notifications')->selectRaw('email_state, count(*) c')->groupBy('email_state')->pluck('c', 'email_state');

        return ['configured' => MailStatus::configured(), 'deliverable' => MailStatus::deliverable(), 'failed' => (int) ($by['failed'] ?? 0), 'pending' => (int) ($by['pending'] ?? 0),
            'stale' => DB::table('app_notifications')->where('email_state', 'pending')->where('updated_at', '<', now()->subMinutes(15))->count()];
    }

    private function files(): array
    {
        $scanner = app(FileScanner::class);
        $by = DB::table('file_assets')->selectRaw('state, count(*) c')->groupBy('state')->pluck('c', 'state');

        return ['scanner' => $scanner->name(), 'operational' => config('freeci.files.scanner') === 'clamav' && $scanner->isOperational(), 'waiting' => (int) ($by['quarantined'] ?? 0) + (int) ($by['scanning'] ?? 0),
            'stale' => DB::table('file_assets')->whereIn('state', ['quarantined', 'scanning'])->where('created_at', '<', now()->subMinutes(15))->count(),
            'errors' => DB::table('file_assets')->whereIn('state', ['quarantined', 'scanning'])->whereNotNull('last_scan_error')->count(), 'rejected' => (int) ($by['rejected'] ?? 0)];
    }

    private function payments(): array
    {
        return ['mode' => PaymentMode::environment(), 'live' => PaymentMode::isLive(), 'creationOpen' => PaymentMode::creationOpen(), 'cases' => DB::table('reconciliation_cases')->whereNull('resolved_at')->count(),
            'toVerify' => DB::table('financial_operations')->where('state', 'to_verify')->count(),
            'pendingOld' => DB::table('payments')->whereIn('state', ['created', 'pending'])->where('created_at', '<', now()->subHours(2))->count()];
    }

    /** Lit le fichier d'état écrit par deploy/backup.sh (« clé=valeur »). Illisible ou absent = INCONNU, jamais « actif ». */
    private function backup(): array
    {
        $file = rtrim((string) config('freeci.ops.backup_dir'), '/').'/STATUS';
        $kv = [];
        try {
            if (is_readable($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    if (preg_match('/^([a-z_]+)=(.*)$/', $line, $m)) {
                        $kv[$m[1]] = trim($m[2]);
                    }
                }
            }
        } catch (Throwable) {
            $kv = [];
        }
        $at = fn (string $k) => isset($kv[$k]) && strtotime($kv[$k]) ? Carbon::createFromTimestamp(strtotime($kv[$k])) : null;
        $local = $at('local_ok_at');

        return ['known' => $kv !== [], 'local' => $local, 'localFresh' => $local !== null && $local->gt(now()->subHours((int) config('freeci.ops.backup_max_age_hours'))),
            'offsite' => $kv['offsite'] ?? 'unknown', 'offsiteAt' => $at('offsite_ok_at'), 'restoreAt' => $at('restore_test_ok_at')];
    }

    /** @return list<array{key: string, label: string, state: 'ok'|'todo'|'info', detail: string}> */
    private function readiness(array $b): array
    {
        $items = [];
        $add = function (string $key, string $label, bool $ok, string $okText, string $todoText, string $kind = 'todo') use (&$items) {
            $items[] = ['key' => $key, 'label' => $label, 'state' => $ok ? 'ok' : $kind, 'detail' => $ok ? $okText : $todoText];
        };
        $add('smtp', 'Courrier (SMTP)', MailStatus::configured(), 'Un pilote de courrier réel est configuré (« envoyé » = accepté par le serveur ; la remise en boîte n’est pas garantie).', 'Non configuré : aucun courriel réel (réinitialisation de mot de passe, vérification d’adresse, changement d’adresse).');
        $add('scan', 'Contrôle des fichiers', config('freeci.files.scanner') === 'clamav' && app(FileScanner::class)->isOperational(), 'Service d’analyse opérationnel.', 'Aucun service d’analyse opérationnel : le dépôt de fichiers est désactivé.');
        $add('backup', 'Sauvegarde locale récente', $b['localFresh'], 'Dernière sauvegarde : '.($b['local']?->diffForHumans() ?? ''), $b['known'] ? 'Aucune sauvegarde récente enregistrée.' : 'État des sauvegardes inconnu (fichier d’état absent ou illisible pour l’application).');
        $add('offsite', 'Copie hors VPS', $b['offsite'] === 'ok', 'Dernière copie hors VPS confirmée : '.($b['offsiteAt']?->diffForHumans() ?? ''), match ($b['offsite']) {
            'disabled' => 'Non activée (aucune destination configurée).', 'unknown' => 'Inconnue.', default => 'En échec ('.$b['offsite'].').'
        });
        $add('restore', 'Restauration testée', $b['restoreAt'] !== null, 'Dernier test de restauration réussi : '.($b['restoreAt']?->diffForHumans() ?? ''), 'Aucun test de restauration enregistré (deploy/restore-test.sh).');
        $sandbox = GeniusPayConfig::ready('sandbox');
        $items[] = ['key' => 'genius_sandbox', 'label' => 'Genius Pay — bac à sable', 'state' => $sandbox ? 'ok' : 'todo', 'detail' => $sandbox ? 'Clés et secret de webhook du bac à sable cohérents.' : 'Configuration du bac à sable incomplète.'];
        $items[] = ['key' => 'genius_live', 'label' => 'Genius Pay — paiements réels', 'state' => 'info', 'detail' => PaymentMode::isLive() ? 'Mode LIVE actif.' : 'Non activés (mode '.PaymentMode::environment().') : décision du porteur, indépendante de cette liste. Configuration live '.(GeniusPayConfig::ready('live') ? 'cohérente' : 'incomplète').', autorisation explicite '.(GeniusPayConfig::liveAuthorized() ? 'donnée' : 'non donnée').'.'];
        $rows = AppSettings::rows();
        $keys = array_merge(...array_values(array_map(fn ($g) => $g['approvable'] ? $g['keys'] : [], SettingDefinitions::groups())));
        $pending = array_values(array_filter($keys, fn ($k) => ($rows[$k]->status ?? 'provisional') !== 'approved'));
        $items[] = ['key' => 'commercial', 'label' => 'Paramètres commerciaux', 'state' => $pending === [] ? 'ok' : 'todo', 'link' => route('admin.settings'),
            'detail' => $pending === [] ? 'Commission, délais et prix approuvés dans l’administration.' : count($pending).' paramètre(s) encore provisoire(s) ou jamais validés (commission '.(config('freeci.finance.commission_bp') / 100).' %, délais, prix) : à valider dans Paramètres.'];
        $missing = array_filter(array_keys(LegalDefaults::PAGES), fn ($k) => ! LegalPages::adopted($k));
        $items[] = ['key' => 'legal', 'label' => 'Pages d’information et légales', 'state' => $missing === [] ? 'ok' : 'todo', 'link' => route('admin.legal'),
            'detail' => $missing === [] ? 'Toutes adoptées.' : 'Non adoptées (brouillon public) : '.implode(', ', array_map(fn ($k) => LegalDefaults::PAGES[$k], $missing)).'.'];
        $operator = filled(config('freeci.legal.operator_name')) && filled(config('freeci.legal.operator_address')) && filled(config('freeci.legal.contact_email'));
        $items[] = ['key' => 'operator', 'label' => 'Identité et contact de l’exploitant', 'state' => $operator ? 'ok' : 'todo', 'link' => route('admin.settings'), 'detail' => $operator ? 'Renseignés dans Paramètres.' : 'À renseigner dans Paramètres (nom, adresse, contact) : rien n’est inventé.'];

        $demo = PurgeDemoData::counts();
        $left = $demo['services'] + $demo['missions'] + $demo['profiles'] + $demo['users'];
        $items[] = ['key' => 'demo', 'label' => 'Données de démonstration', 'state' => $left === 0 ? 'ok' : 'todo', 'detail' => $left === 0 ? 'Aucune donnée de démonstration.' : $left.' élément(s) de démonstration présents (services, missions, profils, comptes) : à retirer depuis cette page.'];

        return $items;
    }
}
