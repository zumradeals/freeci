# Éditeur de mission — harmonisation

Le formulaire `/espace/missions/{mission}/modifier` reprend les composants partagés du système de page et la navigation progressive de l’éditeur de service.

- Trois étapes : besoin public, budget et date limite de candidature, éléments à fournir au freelance retenu.
- Grille large : formulaire et résumé de saisie à droite sur ordinateur, empilés sur petit écran. Suppression de la limite de 720 px.
- Français simplifié, exemples de projet, distinction explicite entre date de candidature et date de livraison, intitulés publics et réponses privées.
- Fichier requis : conséquence expliquée avant le choix, sans ajout de dépôt de fichier à la mission publique.
- Un seul formulaire conserve tous les champs. Les étapes ne soumettent rien. Sans JavaScript, tous les champs et les deux actions restent disponibles.
- Enregistrement du brouillon à tout moment ; l’action finale conserve le parcours de confirmation avant modération. L’aperçu enregistré s’ouvre à part pour préserver la saisie.
- L’étape contenant une erreur est ouverte en priorité ; les autres étapes restent accessibles au clavier. Les modifications non enregistrées déclenchent l’avertissement du navigateur.
- Conservation de l’ancienne révision lors d’un retour de validation ; valeur cachée `0` pour conserver un choix de fichier décoché après erreur.

Aucun changement des règles métier, paiements, autorisations, données ou migrations. Les changements de Claude jusqu’à `395113bf63d96ebc86832e90bd08a569c20855cf` sont conservés.

## Vérification

Compilation Vite et compilation Blade réussies ; syntaxe JavaScript et diff vérifiés. Contrôle DOM sous jsdom : navigation, étape en erreur prioritaire sur le fragment URL, conservation des valeurs des étapes masquées, aperçu en texte échappé, format de date, option fichier et affichage du bouton final. Pas de navigateur graphique ni de suite PostgreSQL exécutés localement ; rendu mobile et bureau à confirmer sur le VPS.

Déploiement par le script habituel, aucune nouvelle variable et aucune migration propre à cette correction. Le 504 intermittent reste un diagnostic séparé.
