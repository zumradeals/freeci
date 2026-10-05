<?php

namespace App\Providers;

use App\Integrations\FileScan\ClamAvScanner;
use App\Integrations\FileScan\FileScanner;
use App\Integrations\FileScan\UnavailableScanner;
use App\Integrations\Payments\PaymentProvider;
use App\Integrations\Payments\SandboxPaymentProvider;
use App\Modules\Catalog\Models\ServiceEvent;
use App\Modules\Missions\Models\MissionEvent;
use App\Modules\Notifications\Actions\NotificationRouter;
use App\Modules\Orders\Models\OrderEvent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Un seul adaptateur de paiement : le simulateur. Aucun prestataire réel n'est choisi ni intégré.
        $this->app->bind(PaymentProvider::class, SandboxPaymentProvider::class);
        $this->app->bind(FileScanner::class, fn () => config('freeci.files.scanner') === 'clamav'
            ? new ClamAvScanner((string) config('freeci.files.clamscan_binary'))
            : new UnavailableScanner);
    }

    public function boot(): void
    {
        // Production : migrate:fresh, migrate:refresh, migrate:reset et db:wipe sont refusés (préservation des données).
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Notifications : chaque ligne d'historique métier (ajout seul) notifie ses destinataires, une seule fois.
        OrderEvent::created(fn ($e) => app(NotificationRouter::class)->order($e));
        ServiceEvent::created(fn ($e) => app(NotificationRouter::class)->service($e));
        MissionEvent::created(fn ($e) => app(NotificationRouter::class)->mission($e));

        // Domaine configurable par APP_URL : en HTTPS, tous les liens générés (dont celui de réinitialisation) le sont aussi.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('FreeCI — réinitialisation de votre mot de passe')
                ->greeting('Bonjour,')
                ->line('Vous avez demandé à réinitialiser le mot de passe de votre compte FreeCI.')
                ->action('Choisir un nouveau mot de passe', $url)
                ->line("Ce lien expire dans {$minutes} minutes et ne peut servir qu’une fois.")
                ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message : votre mot de passe ne change pas.')
                ->salutation('FreeCI');
        });
    }
}
