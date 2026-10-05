# Rapport de vérification — prototype FreeCI V01

Date des mesures : 2026-10-05. Cible : les six pages du prototype, ouvertes en `file://`.
Outils : Chromium (Playwright 1.56) en mode headless ; axe-core 4.13.0 (injecté pour la mesure, **non inclus** dans le prototype). Script : [`verify.mjs`](verify.mjs) ; résultats bruts : [`resultats-v01.json`](resultats-v01.json).

> Ce rapport distingue **ce qui a été effectivement mesuré** (§1–§3) de **ce qui reste seulement prévu** (§4). Les contrôles automatisés ne remplacent ni un essai sur appareil réel, ni un essai avec technologie d'assistance, ni la revue visuelle humaine.

## 1. Testé — mise en page et responsivité (automatisé)

**Matrice** : 6 pages × 5 largeurs (360, 390, 768, 1024, 1440 px) = **30 combinaisons** en affichage normal ; puis les mêmes 30 en **zoom 200 % émulé** (largeur de fenêtre CSS divisée par 2, densité ×2 : 180, 195, 384, 512, 720 px).

| Contrôle | Méthode | Résultat final |
|---|---|---|
| Aucun défilement horizontal global | `scrollWidth` du document − `clientWidth` | **0 px** sur 60/60 combinaisons |
| Aucun élément hors du viewport | rectangle de chaque élément visible (hors panneau « Écrans » fermé) | **0** sur 60/60 |
| Aucun texte tronqué | conteneurs `overflow` masquant un dépassement ; `text-overflow: ellipsis` | **0** (hors conteneurs décoratifs connus : bandeau, vignettes, galerie) |
| Aucun chevauchement de texte | intersection géométrique des rectangles de texte d'éléments distincts (seuil > 4 × 4 px) | **0** sur 60/60 |
| Cibles tactiles ≥ 44 × 44 px | tous les liens, boutons, champs, onglets, `summary` | **0** sous le seuil, hors **liens dans un texte courant** (exemption WCAG 2.5.8) et **liens étirés** dont la zone cliquable est la carte entière |
| Barre d'achat fixe (service, téléphone) | hauteur 69 px ; marge basse de la page 84 px ; défilement de focus compensé | contenu non masqué |
| Chargement des polices | `document.fonts` | Inter chargée sur les 30 combinaisons normales |
| Erreurs console / requêtes en échec | écoute pendant tous les chargements | **0** |
| Liens internes et ancres | lecture des fichiers | **0** lien ou ancre manquant |

### Problèmes détectés puis corrigés pendant la vérification

Pour transparence (chacun a été corrigé puis le contrôle a été relancé en entier) :

1. Le bouton « Publier une mission » restait visible dans l'en-tête à 360 px (règle CSS écrasée).
2. Libellés de catégories coupés au milieu des mots à 360 px → tuiles empilées.
3. Prix « 35 000 / FCFA » sur deux lignes (règle `small` trop générale).
4. Image de la galerie réduite par un rembourrage hérité d'une classe homonyme (`.main`) → classe renommée.
5. Eyebrow du bandeau d'accueil hérité en orange clair **sur fond blanc** dans la carte (contraste insuffisant, relevé par axe-core) → corrigé.
6. Lien profond d'onglet (`#finances`) sans effet lors d'un changement de hash → écouteur ajouté.
7. À 180–195 px (zoom 200 % d'un téléphone de 360 px), grilles dimensionnées par leur contenu → colonnes `minmax(0, 1fr)` ; règles spécifiques ≤ 300 px (logotype et libellé « Menu » masqués visuellement, tuiles et métadonnées sur une colonne, mots longs sécables).
8. Pas de chevauchement du tableau financier à 1024 px → grille auto-ajustée.

## 2. Testé — clavier, focus et composants (automatisé)

| Contrôle | Résultat |
|---|---|
| Parcours à la touche Tab, 6 pages × 2 largeurs (360 et 1440) | 17 à 42 arrêts par page ; **tous** ont un indicateur de focus (contour ≥ 2 px) ; aucun hors écran ; aucun masqué par la barre fixe |
| Tiroir (360 px) | ouvert à Entrée ; focus à l'intérieur ; Échap le ferme ; focus rendu au bouton « Menu » |
| Panneau « Écrans du prototype » | ouvert au clavier ; contenu dans le viewport à 360 px |
| Onglets (commande) | Flèche droite → onglet suivant et panneau affiché ; Fin/Début ; lien profond `#finances` ; changement de hash `#historique` |
| Fenêtre « Valider la livraison » | ouverte à Entrée ; focus à l'intérieur ; Échap la ferme ; focus rendu au déclencheur ; après confirmation : message **« Simulation : la commande n'a pas été modifiée »** et l'état affiché reste « Livrée » |
| Fenêtre de demande (service, 360 px) | plein écran 360 × 900 ; pied de fenêtre visible ; défilement de la page verrouillé |
| Galerie | activation d'une vignette à Entrée : image, légende et `aria-pressed` mis à jour |
| Simulation | clic sur « Payer 45 000 FCFA » : URL inchangée, message « Simulation : … (aucun paiement réel) » |
| Réduction des mouvements | avec `prefers-reduced-motion: reduce`, durée de transition d'un bouton : 0,00001 s |

## 3. Testé — accessibilité automatisée et contrastes

- **axe-core 4.13.0** (règles WCAG 2.0/2.1/2.2 A et AA + bonnes pratiques), 6 pages × 2 largeurs (360, 1440) : **0 violation** ; 40 à 46 règles passées par page.
- axe signale « à vérifier » (*incomplete*, non conclu) des contrastes qu'il ne sait pas calculer : texte du **bandeau d'accueil** (fond à trame de points), et **marqueurs/étiquettes du fil d'étapes** (pseudo-éléments). Recalcul manuel à partir des jetons (formule WCAG) : blanc sur `#102D4B` **14,0:1** ; texte d'introduction `#D3DFEC` **10,4:1** ; surtitre `#FFB48A` **8,1:1** ; puces blanches sur fond translucide ≈ **11,7:1** ; texte secondaire `#4B5968` sur blanc **7,2:1**. Ces valeurs sont **calculées**, non mesurées dans le navigateur.
- Point orange décoratif `#F26B1D` sur `#102D4B` : 4,6:1 (non porteur de texte).

## 4. Non testé — seulement prévu ou hors de portée de l'environnement

| Sujet | État | Précision |
|---|---|---|
| Appareils réels (iPhone, Android), tactile, clavier virtuel, zones sûres | **Non testé** | Aucun appareil disponible ; la barre fixe et le retrait au clavier virtuel ne sont pas vérifiés sur appareil |
| Safari, Firefox, Edge, Chrome Android | **Non testé** | Chromium uniquement |
| Zoom 200 % réel du navigateur, zoom du texte seul, tailles de police système agrandies | **Non testé** | Émulé par réduction de la largeur de fenêtre (équivalence de mise en page, pas de rendu typographique réel) |
| Lecteurs d'écran (NVDA, VoiceOver, TalkBack) | **Non testé** | Seuls les contrôles automatiques axe-core sont faits : l'annonce réelle des états, des onglets et des fenêtres n'est pas vérifiée |
| Largeur 320 px, orientation paysage, très grands écrans > 1440 | **Non testé** | Les cinq largeurs demandées + l'émulation 180–720 px sont couvertes |
| Contraste mesuré dans le navigateur de toutes les paires | **Partiel** | Voir §3 (cas non conclus par axe-core : recalcul manuel) |
| Performance (3 s à 5 Mbit/s, N05), poids réel | **Non mesuré** | Ordre de grandeur : HTML 23–50 Ko par page, CSS 648 lignes, JS 121 lignes, police 48 Ko ; aucun essai réseau |
| Impression, mode sombre, RTL | **Non testé / non prévu** | Hors périmètre V01 |
| Revue visuelle par un humain | **À faire** | Les captures ont été examinées par l'auteur de la conception ; la revue du porteur reste à faire |
| Interactions à la souris (survol), animations réelles | **Non testé** | Seuls clic, clavier et mouvements réduits ont été simulés |
| Fonctions du prototype hors V01 : recherche, favoris, messagerie, missions, comparaison, FR/AD | **Absentes** | Les liens correspondants affichent un message de simulation |

## 5. Limites de la méthode

- Le contrôle de chevauchement compare des rectangles de **texte** ; il ne détecte pas un chevauchement purement graphique (icône sur image, par exemple). Les captures ont été relues visuellement pour ces cas.
- Les « cibles < 44 px » exemptent les liens dans un paragraphe (WCAG 2.5.8) et les liens étirés ; un relecteur plus strict peut vouloir les compter.
- Les mesures en `file://` ne couvrent pas la mise en cache, l'HTTPS ni les en-têtes réels.

## 6. Reproduire

```bash
# prérequis : Node 22, Playwright + Chromium, axe-core (fichier axe.min.js)
AXE_PATH=/chemin/axe.min.js node design/prototype-v01/verification/verify.mjs
```
