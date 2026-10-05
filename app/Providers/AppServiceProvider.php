<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Production : migrate:fresh, migrate:refresh, migrate:reset et db:wipe sont refusés (préservation des données).
        DB::prohibitDestructiveCommands($this->app->isProduction());

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
