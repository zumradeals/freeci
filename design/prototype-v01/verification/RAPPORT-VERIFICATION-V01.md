# Rapport de vérification — prototype FreeCI V01.1

Date des mesures : 2026-10-05. Cible : les six pages du prototype V01.1, ouvertes en `file://`.
Outils : Chromium (Playwright 1.56) en mode headless ; axe-core 4.13.0 (injecté pour la mesure, **non inclus** dans le prototype). Script : [`verify.mjs`](verify.mjs) ; résultats bruts : [`resultats-v01.json`](resultats-v01.json).

Ce rapport sépare **trois niveaux de preuve** :

| Niveau | Nature | Où |
|---|---|---|
| **A. Vérifications automatisées** | Mesures et scénarios exécutés par script, reproductibles | §1–§3 |
| **B. Examen visuel** | Relecture des captures par l'auteur de la conception (jugement humain, **pas** un test utilisateur) | §4 |
| **C. Essais non réalisés** | Ce qui reste seulement prévu ou hors de portée de l'environnement | §5 |

> **Important — zoom.** La ligne « zoom 200 % » ci-dessous est une **simulation par réduction de la largeur de fenêtre (÷ 2, densité ×2)**. Ce **n'est pas** un essai du zoom du navigateur (ni du zoom de texte seul) : elle vérifie qu'une mise en page de 180 à 720 px de large reste utilisable, rien de plus.

## 1. Niveau A — mise en page et responsivité (automatisé)

**Matrice** : 6 pages × 5 largeurs (360, 390, 768, 1024, 1440 px) = **30 combinaisons** en affichage normal ; puis 30 combinaisons « zoom simulé » (180, 195, 384, 512, 720 px de large).

| Contrôle | Méthode | Résultat final |
|---|---|---|
| Aucun défilement horizontal global | `scrollWidth` − `clientWidth` du document | **0 px** sur 60/60 |
| Aucun élément hors du viewport | rectangle de chaque élément visible | **0** sur 60/60 |
| Aucun texte tronqué | conteneurs masquant un dépassement ; `text-overflow: ellipsis` | **0** (hors conteneurs décoratifs et libellés **volontairement** masqués visuellement) |
| Aucun chevauchement de texte | intersection des rectangles de texte d'éléments distincts (> 4 × 4 px) | **0** sur 60/60 |
| Cibles tactiles ≥ 44 × 44 px | liens, boutons, champs, onglets, `summary` | **0** sous le seuil, hors **liens dans un texte courant** (WCAG 2.5.8) et **liens étirés** (zone = carte entière) |
| Chargement des polices | `document.fonts` | Inter chargée |
| Erreurs console / requêtes en échec | écoute pendant les chargements | **0** |
| Liens internes et ancres | lecture des fichiers | **0** manquant |

## 2. Niveau A — interactions et comportements touchés par V01.1

| Contrôle | Résultat |
|---|---|
| **Parcours à la touche Tab** (6 pages × 360 et 1440 px ; 12 à 44 arrêts par page) | Indicateur de focus (contour ≥ 2 px) à **tous** les arrêts ; aucun hors écran ; aucun masqué par la barre fixe |
| **Tiroir** (360 px) | Ouvert à Entrée ; focus dedans ; Échap ferme ; focus rendu au bouton « Menu » |
| **Onglets** | Flèches, Début, Fin ; lien profond `#finances` ; changement de hash `#historique` |
| **Onglets à 360 px** | 5 rubriques **toutes visibles**, sur 2 rangées (3 + 2) de largeurs égales (104 px puis 160 px), 48 px de haut ; onglet actif = fond marine + soulignement orange |
| **Examen de livraison** (360 et 1440 px) | La carte « Action attendue » n'a qu'**un** bouton plein, « Consulter les fichiers de la livraison v2 », et **ne contient pas** « Valider la livraison » ; le clic ouvre la rubrique Livraisons, **place le focus** sur la livraison et l'affiche à l'écran ; les deux choix (« Demander une correction », « Valider la livraison ») ont **exactement le même style** ; **aucun texte** n'oblige à télécharger avant de décider ; 5 mentions « Contrôle de sécurité réussi » et une note distincte « Contrôle de sécurité ≠ qualité du travail » ; **livraison v1 repliée** |
| **Dépliants** | À 360 px : **tous repliés** (accueil, service, commande) ; à 1440 px : **ouverts** (sauf v1 et détails du reversement, repliés par conception) |
| **Barre d'achat** (service, 360 px) | Cachée en haut de page ; **visible après défilement** ; cachée de nouveau au retour en haut |
| **Premier écran du service** (360 × 900) | Prix, délai/corrections/livrables et bouton principal tous **visibles sans défilement** (bas du bouton à 633 px) |
| **Tableau de bord** (360 et 1440 px) | Ordre dans le document : **actions → commandes → autres informations → chiffres** ; à 360 px les sections se suivent bien verticalement dans cet ordre ; **un seul** bouton plein ; la tâche « Comparer les propositions » est marquée « **Pas d'échéance de votre part** » (icône calendrier) ; les deux tâches à échéance de la part du client sont « À payer avant… » et « À décider avant… » |
| **Espace connecté** | En-tête **sans** « Publier une mission » ni navigation publique, **avec** lien « Catalogue » ; pied de page : 94 px (360) et 73 px (1440) |
| **Confiance** | **0** pastille « e-mail / vérifié » ; ligne sobre « Adresse e-mail confirmée. Cela ne vaut pas vérification d'identité, de compétence ou de qualité du travail. » ; **0** élément de note ou d'avis ; **une** étiquette de démonstration par bloc d'exemples |
| **Accueil** | 6 cartes de prestations, **6 images chargées** |
| **Fenêtres modales** | Ouverture à Entrée, Échap, retour du focus ; résultat « Simulation : commande non modifiée » et état affiché inchangé (« Livrée ») |
| **Simulation** | Clic sur « Payer 45 000 FCFA » : URL inchangée, message « Simulation : paiement non inclus… (aucun paiement réel) » |
| **Mouvements réduits** | avec `prefers-reduced-motion: reduce`, durée de transition d'un bouton : 0,00001 s |

## 3. Niveau A — accessibilité automatisée et contrastes

- **axe-core 4.13.0** (WCAG 2.0/2.1/2.2 A et AA + bonnes pratiques), 6 pages × 2 largeurs (360, 1440) : **0 violation** ; 40 à 46 règles passées par page.
- axe classe « à vérifier » (*incomplete*) des contrastes qu'il ne sait pas calculer : texte du **bandeau d'accueil** (trame de points) et **marqueurs du fil d'étapes** (pseudo-éléments). Recalcul manuel à partir des jetons (formule WCAG) : blanc sur `#102D4B` **14,0:1** ; texte d'introduction **10,4:1** ; surtitre **8,1:1** ; puces ≈ **11,7:1** ; texte secondaire `#4B5968` sur blanc **7,2:1**. Valeurs **calculées**, non mesurées dans le navigateur.

## 4. Niveau B — examen visuel (par l'auteur de la conception)

**Images relues** : les six écrans à 360 et 1440 px, plus le tableau de bord à 1024 px, avant la publication. Ce n'est **pas** une revue du porteur ni un test avec des utilisateurs.

**Défauts relevés à l'examen ou par les contrôles, puis corrigés** :

1. Tuiles de catégories de hauteurs inégales ; prix et délai sur deux lignes dans les cartes compactes ; double bordure de la carte de suivi sur téléphone ; indicateur d'onglet actif qui débordait du bouton (remplacé par un soulignement).
2. Trois liens d'action du service répartis sur deux lignes sur téléphone ; liens de pied de page plus étroits que 44 px.
3. **À 1024 px**, les tâches du tableau de bord étaient écrasées (colonne latérale droite trop tôt) : détecté par le contrôle de chevauchement, **corrigé** en n'affichant cette colonne qu'à partir de 1280 px.
4. Légende du plan coupée dans l'illustration ; démonstration encore répétée à plusieurs endroits (allégée).

**Points visuels jugés satisfaisants** (appréciation de l'auteur, à confirmer par le porteur) : hiérarchie du premier écran mobile du service et de la commande ; lisibilité des prix et des échéances ; variété des six illustrations ; cohérence des composants.

**Points que seul le porteur peut trancher** : voir le message de livraison (choix visuels et arbitrages restants).

## 5. Niveau C — essais non réalisés ou seulement prévus

| Sujet | État | Précision |
|---|---|---|
| Appareils réels (iPhone, Android), tactile, clavier virtuel, barre d'adresse mobile, zones sûres | **Non testé** | Aucun appareil disponible. En particulier : hauteur dynamique (`100dvh`) des fenêtres et comportement de la barre d'achat |
| Safari, Firefox, Edge, Chrome Android | **Non testé** | Chromium uniquement (dont `<details>`, `IntersectionObserver`) |
| **Zoom navigateur réel** à 200 %, zoom du texte seul, tailles de police système | **Non testé** | Seulement la **simulation par largeur de fenêtre** décrite plus haut |
| Lecteurs d'écran (NVDA, VoiceOver, TalkBack) | **Non testé** | Seuls les contrôles automatiques axe-core ; l'annonce réelle des onglets, dépliants et fenêtres n'est pas vérifiée |
| Tests avec de vrais utilisateurs (compréhension, confort, rapidité) | **Non réalisé** | Les critères « intuitif » restent des hypothèses de conception |
| Largeur 320 px, orientation paysage, écrans > 1440 px | **Non testé** | Les cinq largeurs demandées sont couvertes |
| Contraste mesuré dans le navigateur de toutes les paires | **Partiel** | Voir §3 |
| Performance (3 s à 5 Mbit/s), poids | **Non mesuré** | Ordre de grandeur : HTML 20–48 Ko par page, CSS 856 lignes, JS 159 lignes, police 48 Ko |
| Impression, mode sombre, RTL | **Non testé / non prévu** | Hors périmètre |
| Survol à la souris, animations réelles | **Non testé** | Seuls clic, clavier et mouvements réduits ont été simulés |
| Fonctions hors prototype : recherche, favoris, messagerie, missions, comparaison, administration | **Absentes** | Les liens affichent un message de simulation |

## 6. Limites de la méthode

- Le contrôle de chevauchement compare des rectangles de **texte** ; il ne détecte pas un chevauchement purement graphique. Les captures ont été relues pour ces cas.
- Les « cibles < 44 px » exemptent les liens dans un paragraphe (WCAG 2.5.8) et les liens étirés ; un relecteur plus strict peut vouloir les compter.
- Les mesures en `file://` ne couvrent ni le cache, ni l'HTTPS, ni les en-têtes réels.

## 7. Reproduire

```bash
# prérequis : Node 22, Playwright + Chromium, axe-core (fichier axe.min.js)
AXE_PATH=/chemin/axe.min.js node design/prototype-v01/verification/verify.mjs
```
