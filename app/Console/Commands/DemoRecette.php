<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Models\AccountRole;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Jeu de RECETTE volontaire : un freelance, un client et un service de démonstration pour essayer le parcours
 * demande → acceptation avec deux comptes distincts. Ne modifie JAMAIS un compte existant ; aucun mot de passe
 * n'est écrit dans le dépôt : ils sont générés et affichés une seule fois.
 */
class DemoRecette extends Command
{
    protected $signature = 'freeci:demo:recette {--yes : ne pas demander de confirmation}';

    protected $description = 'Crée (volontairement) un client, un freelance et un service de démonstration pour la recette du parcours de commande.';

    private const DOMAIN = 'demo.freeci.invalid';

    public function handle(): int
    {
        if (app()->isProduction() && ! config('freeci.allow_demo_seed')) {
            $this->error('Refusé en production : définissez FREECI_ALLOW_DEMO_SEED=true le temps de l\'opération (puis remettez false).');

            return self::FAILURE;
        }
        if (! $this->option('yes') && ! $this->confirm('Créer des comptes et un service de DÉMONSTRATION pour la recette ?')) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }

        $created = [];
        DB::transaction(function () use (&$created) {
            $freelance = $this->account('recette.freelance@'.self::DOMAIN, 'Awa Recette (démo freelance)', $created);
            $client = $this->account('recette.client@'.self::DOMAIN, 'Koffi Recette (démo client)', $created);
            $freelance->roles()->firstOrCreate(['role' => AccountRole::FREELANCE]);
            $client->roles()->firstOrCreate(['role' => AccountRole::CLIENT]);
            // Compte de recette autorisé à utiliser le paiement simulé (si le simulateur est activé : FREECI_PAYMENT_SANDBOX).
            if (! $client->sandbox_payments) {
                $client->forceFill(['sandbox_payments' => true])->save();
            }

            $profile = FreelanceProfile::firstOrCreate(['user_id' => $freelance->getKey()], [
                'display_name' => 'Awa Recette', 'headline' => 'Dessinatrice DAO (démonstration)', 'city' => 'Abidjan', 'is_demo' => true,
            ]);
            $category = Category::firstOrCreate(['slug' => 'btp-et-architecture'], ['name' => 'BTP et Architecture', 'icon' => 'building', 'position' => 1]);

            Service::firstOrCreate(['slug' => 'service-de-recette-mise-en-plan'], [
                'category_id' => $category->getKey(), 'freelance_profile_id' => $profile->getKey(),
                'title' => 'Mise en plan 2D d’un appartement (service de recette)',
                'summary' => 'Service de DÉMONSTRATION pour essayer le parcours de demande : aucun travail réel.',
                'scope' => 'Un logement jusqu’à 120 m², une reprise comprise. Démonstration uniquement.',
                'price_xof' => 40000, 'delivery_days' => 5, 'revisions_included' => 1,
                'deliverables' => ['Un plan 2D coté au format PDF.'], 'exclusions' => ['Tout travail réel : ceci est une démonstration.'],
                'client_inputs' => ['Surface approximative du logement', 'Nombre de pièces', 'Votre besoin en une phrase'],
                'images' => [['src' => '/img/demo/plan-dwg-wide.svg', 'card' => '/img/demo/plan-dwg.svg', 'alt' => 'Exemple : plan d’étage', 'caption' => 'Exemple : plan d’étage']],
                'status' => ServiceStatus::Published->value, 'published_at' => now()->subDay(), 'is_demo' => true, 'accepts_requests' => true,
            ]);

            // Second service : le brief exige au moins un fichier contrôlé (nécessite le service de contrôle de sécurité).
            Service::firstOrCreate(['slug' => 'service-de-recette-avec-fichiers'], [
                'category_id' => $category->getKey(), 'freelance_profile_id' => $profile->getKey(),
                'title' => 'Mise en plan à partir de plans joints (service de recette)',
                'summary' => 'Service de DÉMONSTRATION dont le brief exige un fichier contrôlé : aucun travail réel.',
                'scope' => 'Un plan joint par le client, une reprise comprise. Démonstration uniquement.',
                'price_xof' => 55000, 'delivery_days' => 7, 'revisions_included' => 1,
                'deliverables' => ['Un plan 2D coté au format PDF.'], 'exclusions' => ['Tout travail réel : ceci est une démonstration.'],
                'client_inputs' => ['Votre besoin en une phrase'],
                'images' => [['src' => '/img/demo/plan-dwg-wide.svg', 'card' => '/img/demo/plan-dwg.svg', 'alt' => 'Exemple : plan d’étage', 'caption' => 'Exemple : plan d’étage']],
                'status' => ServiceStatus::Published->value, 'published_at' => now()->subDay(), 'is_demo' => true, 'accepts_requests' => true, 'brief_requires_files' => true,
            ]);
        });

        $this->info('Recette prête. Services : /services/service-de-recette-mise-en-plan et /services/service-de-recette-avec-fichiers');
        foreach ($created as $email => $password) {
            $this->warn("{$email} — mot de passe généré, affiché UNE seule fois : {$password}");
        }
        if ($created === []) {
            $this->line('Comptes déjà présents : laissés tels quels (aucun mot de passe modifié ni affiché).');
        }

        return self::SUCCESS;
    }

    private function account(string $email, string $name, array &$created): User
    {
        $user = User::where('email', $email)->first();
        if ($user !== null) {
            return $user;
        }
        $password = Str::password(18, symbols: false);
        $user = User::forceCreate(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'email_verified_at' => now(), 'is_demo' => true]);
        $created[$email] = $password;

        return $user;
    }
}
