<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\AccountRole;
use App\Modules\Accounts\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Désignation d'un administrateur, uniquement depuis le serveur (console). L'adresse n'étant pas encore vérifiée
 * par courriel, on ne promeut jamais un compte « trouvé » sans geste explicite : --create ou --existing.
 */
class AdminGrant extends Command
{
    protected $signature = 'freeci:admin:grant {email : adresse du compte}
        {--create : crée le compte (mot de passe aléatoire affiché une seule fois)}
        {--existing : confirme qu\'un compte déjà inscrit est bien celui du porteur}
        {--reset-password : avec --existing, remplace le mot de passe par un mot de passe aléatoire affiché une fois}
        {--name=Administrateur : nom affiché (avec --create)}
        {--reason=Désignation par le porteur (console) : motif enregistré}
        {--expires= : date d\'expiration (AAAA-MM-JJ), facultatif}
        {--yes : ne pas demander de confirmation}';

    protected $description = 'Accorde l\'habilitation administrateur (et les rôles client et freelance) à un compte.';

    public function handle(GrantAdministrator $grant): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (Validator::make(['e' => $email], ['e' => 'required|email:rfc|max:254'])->fails()) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        $password = null;

        if ($user === null) {
            if (! $this->option('create')) {
                $this->error("Aucun compte « {$email} ». Relancez avec --create pour le créer (mot de passe aléatoire affiché une seule fois).");

                return self::FAILURE;
            }
        } elseif (! $this->option('existing')) {
            $this->error("Le compte « {$email} » existe déjà (créé le {$user->created_at->format('Y-m-d H:i')} UTC). Vérifiez que c'est bien le vôtre, puis relancez avec --existing (ajoutez --reset-password si vous n'en êtes pas certain).");

            return self::FAILURE;
        }

        $expires = null;
        if ($this->option('expires')) {
            try {
                $expires = now()->parse((string) $this->option('expires'))->endOfDay();
            } catch (\Throwable) {
                $this->error('Date d\'expiration invalide (AAAA-MM-JJ).');

                return self::FAILURE;
            }
        }

        if (! $this->option('yes') && ! $this->confirm("Accorder l'habilitation administrateur à « {$email} » ?")) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }

        if ($user === null) {
            $password = Str::password(20, symbols: false);
            $user = User::forceCreate(['name' => (string) $this->option('name'), 'email' => $email, 'password' => Hash::make($password)]);
        } elseif ($this->option('reset-password')) {
            $password = Str::password(20, symbols: false);
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
        }
        $user->roles()->firstOrCreate(['role' => AccountRole::CLIENT]);

        $g = $grant($user, (string) $this->option('reason'), $expires);

        $this->info("Administrateur : {$email} (habilitation {$g->id}".($expires ? ", expire le {$expires->toDateString()}" : ', sans expiration').'). Rôles : client, freelance.');
        if ($password !== null) {
            $this->warn('Mot de passe généré (affiché UNE seule fois, à ranger dans un gestionnaire de mots de passe) : '.$password);
        }
        $this->line('Accès : https://… /connexion puis /admin (l\'espace d\'administration reste « bientôt » : aucune fonction d\'administration n\'existe encore).');

        return self::SUCCESS;
    }
}
