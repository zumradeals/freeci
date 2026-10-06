# Revenus freelance — harmonisation 14.3

## Périmètre

Présentation de `/freelance/revenus` et message de confirmation du formulaire uniquement. Aucune migration, variable, modification des calculs, habilitations ou transitions financières.

- Synthèse réelle et synthèse de test séparées, cartes de montants sur cinq, trois ou deux colonnes selon la largeur.
- Coordonnées de réception avec les composants de formulaire existants, noms et moyens conservés après validation ; destination sensible jamais préremplie ni réaffichée.
- États distincts : coordonnées absentes, en attente, vérifiées. Remplacer les coordonnées avertit de la nouvelle vérification requise.
- Guide court : validation de la prestation, vérification des coordonnées, traitement par l’administrateur. Aucun bouton ne prétend déclencher un versement.
- Commandes avec montant payé, remboursement éventuel, commission et part du freelance. Blocages existants conservés ; absence de coordonnées expliquée en termes simples.
- Pas de détails d’API dans le parcours utilisateur. Les informations techniques restent dans la documentation financière.

## Vérifications

- `npm run build` réussi.
- Compilation des vues Blade réussie sous PHP WASM avec cache et sessions en mémoire.
- Syntaxe du contrôleur et `git diff --check` vérifiés.
- Aucun test navigateur ni test PostgreSQL exécuté dans cet environnement ; contrôle visuel sur le VPS à faire après mise à jour, notamment sur mobile.

## Exploitation

Déployer le commit via `deploy/update.sh`. Aucun réglage serveur supplémentaire. Cette livraison ne corrige pas le 504 intermittent : son diagnostic par le journal PHP-FPM des requêtes lentes reste distinct.
