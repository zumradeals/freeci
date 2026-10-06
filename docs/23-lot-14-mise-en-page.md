# Lot 14 — audit et harmonisation des mises en page et des menus

## Causes identifiées
1. **Largeurs plafonnées incohérentes** : `.container` à 1 200 px (en-tête, pied de page, pages publiques) et `.main-inner` à 1 040 px dans les espaces connectés. Sur écran large, la zone principale laissait une bande vide à droite (≈ 600 px à 1 920 px).
2. **Décalages** : en-tête et pied de page centrés dans le conteneur public pendant que la barre latérale collait au bord gauche ; logo, icônes de la barre latérale, contenu et pied de page sur quatre alignements différents.
3. **Styles locaux** : 33 cartes de formulaires bridées en ligne (`max-width:640/680/720/760px`), colonne latérale du tableau de bord à 320 px fixes, champs de formulaire sans longueur de ligne maîtrisée.
4. **Menus** : navigation éclatée (liste plate, blocs « Compte / Aide » ajoutés à part, changement d'espace sous trois formes : segmenté dans l'en-tête de page, lien d'activation, lien d'administration), fichier dupliqué entre barre latérale et tiroir.
5. **Textes périmés** (cause vérifiée un par un : texte périmé, fonctions existantes) : « Bientôt » sur Aide, « Publier une mission (bientôt) » et « Créer mon profil (bientôt) » (accueil), « Bientôt : le paiement n'est pas encore ouvert » (tableau de bord), « démonstration (données fictives) » en pied de page et dans la description, page « bientôt disponible » pour 13 fonctions toutes livrées. **Aucune** fonctionnalité absente derrière ces mentions. Les étiquettes « de démonstration » liées à une donnée réelle (`is_demo`) sont conservées.

## Structure partagée
- Variables : `--container` (1 360 px, 1 560 px dès 1 760 px), `--shell-max` (1 680 px), `--sidebar-w` (264 px), `--gutter`.
- **Public** : `.container` unique ; pages d'information avec navigation latérale (`layouts/info`) et texte limité à 46 rem.
- **Espaces client/freelance et administration** : même coque (`.app`), en-tête et pied de page pleine largeur alignés sur la barre latérale (logo sur les icônes, pied sur le contenu), zone principale qui occupe tout l'espace restant ; colonne latérale des tableaux de bord `clamp(320px, 26vw, 420px)` ; listes de cartes en grille dès 1 280 px ; pages de réglages en deux colonnes ; cartes de formulaires à 56 rem alignées à gauche (`.form-card`). L'administration garde sa propre navigation et la mention « Administration ».
- Accueil : services publiés d'abord (4 colonnes dès 1 360 px), besoins, étapes condensées (une seule illustration de suivi, dans l'en-tête), appel freelance.
- Bandeau « Mode test » compact, aligné sur la coque.

## Menus
Composant unique `fc/sidebar` (barre latérale **et** tiroir mobile/tablette), groupes : **Activité**, **Échanges**, **Finances**, **Compte et aide** (client/freelance) ; **Pilotage**, **Assistance**, **Finances**, **Exploitation** (administration). Sélecteur d'espace unique en tête, reflétant les droits : Client | Freelance (| Admin) pour un freelance, « Activer l'espace freelance » sinon, « Administration » seulement pour le personnel. Distinction : *Assistance* (dossiers personnels) ≠ *Aide* (informations) ≠ administration. Page active signalée (`aria-current`). Toutes les fonctions existantes restent accessibles.

## Tableaux de bord
Première utilisation : « Par où commencer ? » avec trois actions (aucune carte vide ni compteur à zéro). Activité existante : actions attendues et échéances, commandes récentes, compteurs en second plan, raccourcis.

## Vérifications et limites
Rendus réels (Chromium) de 29 pages à 360, 768, 1 024, 1 440 et 1 920 px : aucun débordement horizontal ; statuts 200. 277 tests automatisés (tests modifiés : textes d'états vides). Captures avant/après : `docs/captures/lot-14/` (accueil, tableau client, espace freelance, commande, administration ; 360 et 1 440 px). **Non fait** : audit exhaustif de chaque page secondaire (messagerie, assistance, finances, missions) au-delà du contrôle de débordement ; pas de tests visuels automatisés ; fenêtres modales et formulaires touchés vérifiés par les tests fonctionnels existants, pas par essai manuel sur navigateurs mobiles réels. Aucun changement de règle métier, d'autorisation, de donnée ou de mode de paiement.
