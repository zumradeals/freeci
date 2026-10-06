# Éditeur de service — simplification après le lot 14

Le formulaire utilise la largeur de l’espace freelance, avec quatre étapes : présentation, offre, images, préparation avant publication. Un aperçu de carte accompagne la saisie sur ordinateur ; il passe sous le formulaire sur petit écran. Les étapes conservent les valeurs dans le même formulaire. Sans JavaScript, toutes les sections restent accessibles.

Les libellés expliquent la prestation, les retouches, les éléments livrés et les besoins du freelance. Les montants restent en FCFA. Le bouton final conduit à la confirmation de soumission existante : il ne publie pas sans modération.

## Images et conservation des saisies

- Un choix explicite désigne l’image principale. Elle est placée en tête de la liste du brouillon, déjà utilisée par la carte publique et la galerie. Le serveur refuse un identifiant extérieur à cette version.
- Ajouter ou retirer une image depuis le formulaire enregistre les textes dans la même transaction. En cas de refus, la transaction est annulée et les saisies textuelles sont renvoyées au formulaire. Un fichier refusé doit être sélectionné de nouveau.
- Enregistrer le brouillon ou préparer sa soumission ajoute également un fichier sélectionné, même sans clic préalable sur « Ajouter l’image ».
- La case « Le client doit envoyer un fichier » envoie explicitement zéro quand elle est décochée.
- Les contrôles de propriétaire, de version et d’état restent ceux des actions existantes. Une version déjà publiée reste inchangée jusqu’à approbation de la nouvelle.

## Déploiement

Aucune migration, variable ni dépendance applicative ajoutée. Utiliser `deploy/update.sh` avec le SHA communiqué. GD/WebP reste nécessaire pour ajouter des images, comme auparavant. Aucun compte, service ou fichier du VPS n’a été modifié pendant ce travail.

## Vérifications

Effectuées :

- `npm run build` et contrôle de syntaxe JavaScript ;
- syntaxe des fichiers PHP modifiés avec PHP WebAssembly ;
- `artisan view:cache` et rendu réel du formulaire Blade avec des données fictives, sans la coque de compte ;
- contrôles DOM sur ce rendu : navigation entre étapes, conservation des textes, aperçu, sélection de couverture, case décochée, disponibilité du formulaire sans JavaScript ;
- `git diff --check`.

Trois tests de régression HTTP ont été ajoutés dans `ServiceManagementTest` : sauvegarde des textes avec ajout/retrait et annulation sur image invalide ; choix de couverture appartenant au brouillon et projection après publication ; ajout d’une image lors de l’enregistrement direct.

**Non exécutés ici :** ces tests HTTP et la suite PostgreSQL (serveur/extension PostgreSQL absents). Aucun parcours authentifié sur le VPS. Le navigateur Chromium local n’a pas pu être installé (téléchargement invalide) : pas de capture ni de validation visuelle réelle des largeurs d’écran. Les contrôles DOM ne constituent pas un test de mise en page.

## Ajustement de la fiche et accès aux fichiers du brief

- Galerie de la fiche : hauteur plafonnée à 460 px sur ordinateur et 340 px sur téléphone, image entière (`object-fit: contain`), agrandissement dans un nouvel onglet et lien mis à jour au changement de miniature. Miniatures de hauteur uniforme.
- Description : conservation des sauts de ligne du texte échappé.
- Demande : suppression des anciens messages « fichiers indisponibles dans cette version » et « paiement non ouvert ». Les pièces jointes s’ajoutent dans le dossier créé après l’envoi de la demande, avant acceptation et paiement si souhaité.
- Dossier : accès explicite « Joindre mes fichiers » avant démarrage, avertissement d’indisponibilité du scanner pour le client et le freelance, erreurs de fichier visibles, retour à l’onglet Brief après ajout ou retrait.
- Un scanner indisponible ne peut toujours pas être contourné. Le déploiement de ce correctif ne l’installe pas et ne complète pas artificiellement le brief. Diagnostic VPS : `sudo -u freeci -H bash -lc 'cd /var/www/freeci && php8.3 artisan freeci:files:check'`. Suivre ensuite le diagnostic ; voir `docs/10` pour la configuration ClamAV existante.
- Aucune migration ni modification des accords, des paiements ou des états métier. Les fichiers et le service du client restent inchangés.
