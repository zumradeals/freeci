<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Catalog\Exceptions\ServiceStateConflict;
use App\Modules\Catalog\Models\ServiceVersion;
use App\Modules\Catalog\Moderation\ServiceModeration;
use Illuminate\Console\Command;

/**
 * Base des commandes de modération provisoires (aucune interface web). Chaque commande s'exécute sur le serveur, identifie
 * l'administrateur par `--by=<courriel>` (habilitation « administrateur » EN VIGUEUR exigée, vérifiée par l'action) et agit via
 * `ServiceModeration` : verrous, état revérifié, historique avec acteur. Une confirmation est demandée (ou `--yes`).
 */
abstract class ModerationCommands extends Command
{
    protected function moderator(): ?User
    {
        $email = mb_strtolower(trim((string) $this->option('by')));
        $user = $email === '' ? null : User::query()->where('email', $email)->first();
        if ($user === null || ! $user->isAdministrator()) {
            $this->error('Refusé : --by doit désigner un compte administrateur (habilitation en vigueur).');

            return null;
        }

        return $user;
    }

    protected function run1(callable $do, string $success): int
    {
        try {
            $do();
        } catch (ModerationDenied|ServiceStateConflict $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info($success);

        return self::SUCCESS;
    }

    protected function confirmed(string $question): bool
    {
        return (bool) $this->option('yes') || $this->confirm($question, false);
    }

    protected function describe(ServiceVersion $v): void
    {
        $s = $v->service()->with('freelanceProfile.user')->first();
        $this->line("Version  {$v->id}  (service {$s->id}, v{$v->number}, état : {$v->state})");
        $this->line('Auteur   '.$s->freelanceProfile->display_name.' <'.$s->freelanceProfile->user->email.'>'.($s->is_demo ? ' [démonstration]' : ''));
        $this->line("Titre    {$v->title}");
        $this->line('Prix     '.number_format((int) $v->price_xof, 0, ',', ' ')." FCFA · délai {$v->delivery_days} j · corrections {$v->revisions_included}");
        $this->line('Livraison '.($v->delivery_requires_files ? 'au moins un fichier' : 'par message seul').' · brief : '.($v->brief_requires_files ? 'fichier exigé' : 'texte'));
        $this->line("Résumé   {$v->summary}");
        $this->line("Périmètre\n{$v->scope}");
        $this->line('Livrables : '.implode(' | ', $v->deliverables));
        $this->line('Non inclus : '.implode(' | ', $v->exclusions));
        $this->line('À fournir : '.implode(' | ', $v->client_inputs));
        $this->line('Images   '.count($v->images).' ('.implode(' ; ', array_map(fn ($i) => (string) ($i['alt'] ?? ''), $v->images)).')');
        $live = ServiceVersion::query()->where('service_id', $v->service_id)->where('state', 'published')->first();
        $this->line('Version publiée actuelle : '.($live ? 'v'.$live->number.' (« '.$live->title.' », '.number_format((int) $live->price_xof, 0, ',', ' ').' FCFA)' : 'aucune (premier envoi)'));
    }
}
