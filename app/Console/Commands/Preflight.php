<?php

namespace App\Console\Commands;

use App\Integrations\FileScan\FileScanner;
use App\Integrations\Payments\GeniusPayConfig;
use App\Modules\Catalog\Support\ImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Contrôle de configuration de production, sans rien modifier.
 * Code de sortie ≠ 0 si un contrôle bloquant échoue : à lancer avant d'ouvrir le site (docs/07 §5).
 */
class Preflight extends Command
{
    protected $signature = 'freeci:preflight {--allow-non-production : traite APP_ENV différent de production comme un avertissement}';

    protected $description = 'Contrôle la configuration de production (HTTPS, debug, base, courrier, données de démonstration).';

    /** @var list<array{string,string,string}> */
    private array $rows = [];

    private bool $failed = false;

    public function handle(): int
    {
        $this->rows = [];
        $this->failed = false;
        $lenient = (bool) $this->option('allow-non-production');

        $this->check('APP_ENV = production', app()->environment('production'), 'APP_ENV='.app()->environment(), ! $lenient);
        $this->check('APP_DEBUG désactivé', config('app.debug') === false, 'APP_DEBUG=true expose les traces et variables');
        $this->check('APP_KEY définie', filled(config('app.key')), 'php artisan key:generate (une seule fois, à conserver)');

        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $this->check('APP_URL en HTTPS', str_starts_with($url, 'https://'), $url ?: 'vide');
        $this->check('APP_URL n\'est pas local', $host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true) && ! str_ends_with($host, '.test'), $host ?: 'vide');
        $this->check('Cookie de session sécurisé', config('session.secure') === true, 'SESSION_SECURE_COOKIE=true');
        $this->check('Cookie de session HttpOnly', config('session.http_only') === true, 'SESSION_HTTP_ONLY=true');
        $this->check('Cookie SameSite lax ou strict', in_array(config('session.same_site'), ['lax', 'strict'], true), (string) config('session.same_site'));

        $this->check('Connexion PostgreSQL', $this->databaseOk($detail), $detail);
        $this->check('Aucune migration en attente', $this->noPendingMigrations($pending), $pending, false);

        $this->check('Données de démonstration non réinjectables', config('freeci.allow_demo_seed') === false, 'FREECI_ALLOW_DEMO_SEED=true : à laisser à false hors installation de démonstration voulue', false);
        $this->check('Aucun mot de passe de démonstration en configuration', blank(config('freeci.demo_client_password')), 'FREECI_DEMO_CLIENT_PASSWORD doit rester vide en production', false);

        $mailer = (string) config('mail.default');
        $this->check('Courrier réel (pas de pilote log/array)', ! in_array($mailer, ['log', 'array'], true), "MAIL_MAILER={$mailer} : le lien « mot de passe oublié » ne sera pas envoyé", false);

        $sandbox = (bool) config('freeci.payments.sandbox_enabled');
        $this->check('File d\'attente asynchrone (courriels avec reprises)', config('queue.default') !== 'sync', 'QUEUE_CONNECTION=sync : aucun traitement différé ni reprise ; utilisez « database » avec un processus queue:work ou FREECI_QUEUE_VIA_SCHEDULER=true', false);
        $this->check('Extension GD avec WebP (images de service)', ImageProcessor::available(), 'php8.3-gd absent : le dépôt d\'images de service est désactivé (les services restent publiables sans image)', false);
        $this->check('Simulateur de paiement désactivé', ! $sandbox, 'FREECI_PAYMENT_SANDBOX=true : réservé aux comptes et commandes de démonstration autorisés (comptes de recette) ; à remettre à false après la recette', false);
        $this->check('Simulateur : secret de notification défini', ! $sandbox || filled(config('freeci.payments.sandbox_webhook_secret')), 'FREECI_SANDBOX_WEBHOOK_SECRET vide alors que le simulateur est activé : les notifications seront refusées');
        $gp = GeniusPayConfig::sandboxSelected();
        $this->check('Genius Pay (bac à sable) : configuration conforme', ! $gp || GeniusPayConfig::problems() === [], 'FREECI_PAYMENT_PROVIDER=geniuspay_sandbox mais : '.implode(', ', GeniusPayConfig::problems()).' (voir docs/17 ; php artisan freeci:genius:status)');
        $this->check('Genius Pay : paiements sandbox autorisés si sélectionné', ! $gp || $sandbox, 'FREECI_PAYMENT_PROVIDER=geniuspay_sandbox exige FREECI_PAYMENT_SANDBOX=true (réservé aux comptes et commandes de démonstration)');
        $this->check('Service de contrôle des fichiers', config('freeci.files.scanner') === 'clamav' && app(FileScanner::class)->isOperational(), 'aucun service d\'analyse opérationnel : le dépôt de fichiers du brief est désactivé (voir docs/10)', false);
        $this->check('Disque privé des fichiers inscriptible', $this->privateDiskWritable(), storage_path('app/private/files'));

        $this->check('storage/ inscriptible', is_writable(storage_path()) && is_writable(storage_path('logs')), storage_path());
        $this->check('bootstrap/cache inscriptible', is_writable(base_path('bootstrap/cache')), base_path('bootstrap/cache'));
        $this->check('Ressources compilées présentes', is_file(public_path('build/manifest.json')), 'npm run build (ou copier public/build)');
        $this->check('Configuration en cache', app()->configurationIsCached(), 'php artisan config:cache', false);
        $this->check('HSTS activé', (int) config('freeci.hsts_max_age') > 0, 'FREECI_HSTS_MAX_AGE=0', false);

        $this->table(['Contrôle', 'Résultat', 'Détail'], $this->rows);
        $this->failed
            ? $this->error('Contrôle de production : ÉCHEC (voir les lignes « ÉCHEC »).')
            : $this->info('Contrôle de production : aucun point bloquant (vérifiez les AVERT.).');

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $label, bool $ok, string $detail = '', bool $blocking = true): void
    {
        if (! $ok && $blocking) {
            $this->failed = true;
        }
        $this->rows[] = [$label, $ok ? 'OK' : ($blocking ? 'ÉCHEC' : 'AVERT.'), $ok ? '' : $detail];
    }

    private function privateDiskWritable(): bool
    {
        try {
            $disk = Storage::disk('private_files');
            $probe = '.preflight-'.bin2hex(random_bytes(4));
            $disk->put($probe, 'x');
            $disk->delete($probe);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function databaseOk(?string &$detail): bool
    {
        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $detail = $driver === 'pgsql' ? 'connecté' : "pilote {$driver} (pgsql attendu)";

            return $driver === 'pgsql';
        } catch (Throwable $e) {
            $detail = 'connexion impossible : '.class_basename($e);

            return false;
        }
    }

    private function noPendingMigrations(?string &$detail): bool
    {
        try {
            if (! Schema::hasTable('migrations')) {
                $detail = 'table des migrations absente : première installation ?';

                return false;
            }
            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));
            $todo = $files->diff($ran);
            $detail = $todo->isEmpty() ? '' : 'à appliquer : '.$todo->implode(', ');

            return $todo->isEmpty();
        } catch (Throwable) {
            $detail = 'indéterminé';

            return false;
        }
    }
}
