# FreeCI — prototype statique V01.1

> **Prototype de conception, non destiné à la mise en ligne.** Il sert à **voir et évaluer l'apparence et les interactions locales** de FreeCI sur téléphone et ordinateur. Ce **n'est pas** le socle applicatif Laravel : aucun backend, aucun compte, aucune base de données, aucun paiement, aucune règle métier exécutable.
> Toutes les données (noms, prix, dates, références, fichiers) sont **fictives**. Une barre « Prototype V01.1 · simulé » est présente sur chaque écran.

**V01.1 = révision ciblée de V01** (retenu comme base de travail), sans refonte : mobile allégé, examen de livraison revu, espace privé simplifié, confiance plus sobre. Le dossier garde le nom `prototype-v01`.

Statut des choix : les décisions du porteur (D01–D25, `docs/01-cadrage.md`) sont validées ; le reste est une **proposition visuelle à examiner**.

## Ouvrir le prototype

Aucune installation, aucun serveur : **ouvrir `index.html` dans un navigateur** (double-clic). Contrôlé en ouverture directe de fichier (`file://`). Option équivalente :

```bash
python3 -m http.server 8080 --directory design/prototype-v01   # puis http://localhost:8080/
```

La barre du haut (« **Écrans** ») liste tous les écrans ; sur téléphone, « Menu » ouvre le tiroir de navigation.

## Écrans

| Écran | Fichier | Rôle |
|---|---|---|
| 1. Accueil | `index.html` | Identité, recherche, catégories, six prestations **en exemple**, deux parcours, suivi |
| 2. Détail d'un service | `service.html` | Offre (prix, délai, corrections, livrables), périmètre, vendeur ; fenêtre de brief |
| 3. Tableau de bord client | `tableau-de-bord.html` | Actions, commandes, autres informations, chiffres **en dernier** |
| 4. Commande livrée (client) | `commande.html` | Examen de la livraison, accord, brief, finances **distinctes**, historique |
| Variante : état vide | `tableau-de-bord-vide.html` | Nouveau compte : orientation vers une action utile |
| Variante : paiement en vérification | `commande-paiement-en-verification.html` | Même commande à l'étape du paiement ; **pas de bouton « Payer »** |

Seuls ces six écrans existent ; les autres liens affichent « Simulation : écran non inclus dans ce prototype ». La commande présente 5 des 7 onglets prévus (Livraisons, Accord, Brief, Finances, Historique).

## Scénario de démonstration (identique d'un écran à l'autre)

| Élément | Valeur fictive |
|---|---|
| Client / freelance | **Fanta Bamba** / **Kader Soro**, dessinateur DAO, Abidjan |
| Prestation | *Convertir vos plans PDF en fichiers AutoCAD (DWG)* — **35 000 FCFA**, 5 jours, 2 corrections |
| Référence | **DEMO-26018** (paiement simulé SIM-PAY-0007421) |
| Instants simulés | Tableau de bord et commande livrée : **jeudi 8 oct. 2026, 14:00** (heure d'Abidjan). Variante paiement : **vendredi 2 oct. 2026, 10:02** |
| Historique | demande 1 oct. → paiement confirmé 2 oct. → report de 2 jours accepté 5 oct. → livraison v1 6 oct. → correction 1/2 7 oct. → livraison v2 8 oct. (**à décider avant le 15 oct. 11:15**) |
| Autres éléments | DEMO-26021 (logo, 45 000 FCFA, à payer avant le 9 oct. 09:00) ; DEMO-26009 (fiches produit, 60 000 FCFA) ; mission DEMO-M-0412 (3 propositions) |

Aucun témoignage, aucune note, aucun faux volume d'activité, aucun badge de qualité.

## Ce qui est simulé

- **Aucun bouton n'effectue d'opération réelle** : message « Simulation » (bandeau bas d'écran) ou état « Simulation : … » dans les fenêtres de confirmation. L'état affiché ne change jamais.
- Fichiers : téléchargements simulés ; illustrations = SVG dessinés pour la démonstration (aucune photographie, aucune personne).
- Interactions réellement locales (JavaScript de présentation, 159 lignes, sans bibliothèque) : tiroir, panneau « Écrans », onglets, fenêtres modales, dépliants, galerie, barre d'achat, lien « Consulter les fichiers ».

## Ce que V01.1 change (comparaison : `captures/avant-apres/`)

Hauteur des pages à 360 px : accueil **−29 %**, service **−22 %**, tableau de bord **−32 %**, commande livrée **−28 %**, état vide **−41 %**, paiement en vérification **−42 %**.

| Zone | V01.1 |
|---|---|
| **Accueil** | **Six** illustrations de prestations variées (plans DWG, identité visuelle, site web, traduction, montage vidéo, réseaux sociaux) ; **liste compacte** sur téléphone ; « Deux façons de démarrer » et « ce que vous retrouvez » **dépliables** ; étiquette unique « Exemples fictifs » |
| **Service** | **Résumé d'offre sous le titre** (prix, délai, corrections, livrables, bouton, effet) ; « Ce qui n'est pas inclus » et « Ce que vous devez fournir » dépliables ; **e-mail confirmé en texte sobre**, sans pastille ; barre d'achat fixe seulement après le résumé |
| **Tableau de bord** | Ordre **actions → commandes → autres informations → chiffres** ; **échéance d'action ≠ fin des candidatures** (« Pas d'échéance de votre part. Candidatures ouvertes jusqu'au… ») ; **une seule action pleine** ; commandes en cartes cliquables ; **en-tête et pied allégés** (Catalogue, messages, compte) ; menu : Commandes, Missions, Messages, puis Compte et Aide |
| **Commande** | **Action principale = « Consulter les fichiers de la livraison v2 »** ; ensuite **deux blocs de même style** (« Demander une correction » / « Valider la livraison ») avec leurs conséquences ; **aucune obligation de télécharger** ; **contrôle de sécurité ≠ qualité du travail** (note unique) ; **v1 repliée** ; **onglets en grille 3 + 2** à 360 px (actif plein + soulignement orange) ; carte « Contact et aide » unique |
| **Démonstration** | Barre courte, une étiquette par page, messages de simulation courts |

## Choix conservés (D23) et ajustements justifiés

**Conservés** : Inter, palette marine et claire, accent orange, **trame de points** de l'accueil, **carte de suivi**, monogramme + « FreeCI » comme **identité de travail** (pas un logo définitivement validé par le client).

| Ajustement | Justification |
|---|---|
| Orange `#F26B1D` pour le point décoratif | Présence sur fond clair ; jamais porteur de texte ni de statut |
| Catégories en tuiles verticales sur téléphone | Les libellés longs se coupaient mal à côté de l'icône |
| Fil d'Ariane réduit à un lien de retour sur téléphone | Le fil complet passait sur plusieurs lignes |
| Galerie 3:2 | Plans rendus sans rognage |
| Colonne latérale droite de l'espace connecté **à partir de 1280 px** | Défaut relevé à 1024 px : la colonne principale devenait trop étroite |
| Cibles de 44 px y compris dans le pied de page | Exigence D10 |
| Inter embarquée en fichier (latin, 48 Ko, SIL OFL 1.1 jointe) | Rendu fiable hors ligne ; ressource, pas dépendance |

## Contenu du dossier

```text
design/prototype-v01/
├── index.html · service.html · tableau-de-bord.html · tableau-de-bord-vide.html
├── commande.html · commande-paiement-en-verification.html
├── assets/css/freeci.css · assets/js/prototype.js · assets/fonts/ (Inter + licence)
├── assets/img/*.svg               Illustrations de démonstration + monogramme
├── captures/v01/                  Captures de V01 (avant)
├── captures/v01-1/                24 captures de V01.1 (360, 390, 768, 1024, 1440)
├── captures/avant-apres/          10 comparaisons V01 / V01.1
└── verification/                  Script, résultats bruts, rapport
```

Pages en HTML statique ; en-tête, pied et tiroir répétés dans chaque fichier (prototype, pas de modèle partagé).

## Captures V01.1 (Chromium, pleine page)

Téléphone **360 px** (rendu ×2) et ordinateur **1440 px** pour les six écrans ; **390, 768 et 1024 px** pour les quatre écrans principaux.

| Écran | 360 | 390 | 768 | 1024 | 1440 |
|---|---|---|---|---|---|
| Accueil | [png](captures/v01-1/accueil-360.png) | [png](captures/v01-1/accueil-390.png) | [png](captures/v01-1/accueil-768.png) | [png](captures/v01-1/accueil-1024.png) | [png](captures/v01-1/accueil-1440.png) |
| Service | [png](captures/v01-1/service-360.png) | [png](captures/v01-1/service-390.png) | [png](captures/v01-1/service-768.png) | [png](captures/v01-1/service-1024.png) | [png](captures/v01-1/service-1440.png) |
| Tableau de bord | [png](captures/v01-1/tableau-de-bord-360.png) | [png](captures/v01-1/tableau-de-bord-390.png) | [png](captures/v01-1/tableau-de-bord-768.png) | [png](captures/v01-1/tableau-de-bord-1024.png) | [png](captures/v01-1/tableau-de-bord-1440.png) |
| Commande livrée | [png](captures/v01-1/commande-livree-360.png) | [png](captures/v01-1/commande-livree-390.png) | [png](captures/v01-1/commande-livree-768.png) | [png](captures/v01-1/commande-livree-1024.png) | [png](captures/v01-1/commande-livree-1440.png) |
| État vide | [png](captures/v01-1/tableau-de-bord-vide-360.png) | — | — | — | [png](captures/v01-1/tableau-de-bord-vide-1440.png) |
| Paiement en vérification | [png](captures/v01-1/commande-paiement-en-verification-360.png) | — | — | — | [png](captures/v01-1/commande-paiement-en-verification-1440.png) |

**Avant / après** : [accueil](captures/avant-apres/mobile-accueil-ecran-complet.png) · [service](captures/avant-apres/mobile-service-ecran-complet.png) · [tableau de bord](captures/avant-apres/mobile-tableau-de-bord-ecran-complet.png) · [commande](captures/avant-apres/mobile-commande-livree-ecran-complet.png) (versions « premier écran » et « bureau » dans le même dossier).

## Vérifications

Le détail — **vérifications automatisées**, **examen visuel** et **essais non réalisés** — est dans [`verification/RAPPORT-VERIFICATION-V01.md`](verification/RAPPORT-VERIFICATION-V01.md). La « réduction de largeur » y est présentée comme une **simulation**, pas comme un test de zoom navigateur.
