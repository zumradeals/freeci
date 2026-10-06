<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Anciennes adresses « bientôt » : toutes les fonctions annoncées existent désormais. L'adresse est conservée (anciens liens, favoris du navigateur)
 * et renvoie vers la vraie page ; un identifiant inconnu reste une 404.
 */
class ComingSoonController extends Controller
{
    private const TARGETS = [
        'missions' => ['missions.index'], 'freelances' => ['freelances.index'], 'publier-une-mission' => ['client.missions.new'], 'creer-un-profil' => ['freelance.activate'],
        'commandes' => ['orders.index'], 'messages' => ['messages.index'], 'paiements' => ['client.finances'], 'favoris' => ['favorites.index'], 'compte' => ['account.settings'],
        'aide' => ['info', ['page' => 'aide']], 'conditions' => ['info', ['page' => 'conditions']], 'confidentialite' => ['info', ['page' => 'confidentialite']],
        'mentions-legales' => ['info', ['page' => 'mentions-legales']],
    ];

    public function __invoke(string $feature): RedirectResponse
    {
        abort_unless(isset(self::TARGETS[$feature]), 404);

        return redirect()->route(self::TARGETS[$feature][0], self::TARGETS[$feature][1] ?? [], 301);
    }
}
