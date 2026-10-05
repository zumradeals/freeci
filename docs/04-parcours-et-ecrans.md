# 04 — Parcours et écrans

> **Statut : base de travail acceptée par le porteur (revue du 2026-10-05).** Les écrans restent à présenter en maquettes haute fidélité et à examiner visuellement avant tout développement ; les règles métier sont portées par le serveur. **Publication** dans le dépôt public autorisée par le porteur (D19), hors secrets, identifiants, données personnelles réelles et pièces client originales.
> Les maquettes sont **décrites** (disposition, proportions, contenus, actions, variantes) ; aucune n'est dessinée ni codée. Les comportements responsives et d'accessibilité sont des **critères de conception** : **aucun n'a été testé**, il n'existe pas d'application.

Étiquettes : **SRC** (avec repère F/N/P/A/C/CL/FR/AD) · **DEC** · **PROP** · **Q** (`05` §5). Jetons visuels : `03`. Actions métier : `02` §6 et rubriques « Contrats métier appelés » de chaque écran.

---

## 0. Règles transverses

### 0.1 Anatomie d'un écran (PROP — répond à INS §4 « trois questions »)

```text
┌ En-tête de coque (public / espace / administration)
├ Repère de navigation actif + fil d'Ariane si profondeur ≥ 3          ← « Où suis-je ? »
├ H1 (un seul) + phrase de contexte
├ BLOC « Action attendue » (si l'utilisateur doit agir)                  ← « Que dois-je faire maintenant ? »
│    verbe + objet + échéance  ·  [Action principale]  ·  secondaires
│    « Ce qui se passera : … » (une phrase d'effet)                      ← « Que se passera-t-il après ? »
├ Contenu (sections H2)
└ Pied : aide / support, mentions de démonstration le cas échéant
```

Règle : une **seule action primaire** par vue ; la phrase d'effet est **adjacente** au bouton (jamais dans une infobulle).

### 0.2 Matrice responsive (DEC D10 — SRC N02)

Mobile d'abord : les tâches essentielles (inscription, mission, proposition, paiement, livraison, validation — N02) sont conçues à **360 px**, puis enrichies. **390 px** utilise la même mise en page que 360 px avec un peu plus d'air (2 chips de filtre visibles au lieu de 1, titres de carte sur 2 lignes sans coupure) ; l'écran de 360 px est le **cas le plus contraignant** de référence.

| Domaine | 360 | 390 | 768 | 1024 | 1440 |
|---|---|---|---|---|---|
| **Navigation** | En-tête 56 px : logo, messages, **Menu** ; tiroir plein écran (sélecteur d'espace, liens, « Publier une mission ») | Idem | Idem ; « Publier une mission » visible dans l'en-tête | Liens en ligne dans l'en-tête public ; **menu latéral 248 px** dans l'espace privé | Idem, contenu **centré et borné** (1200/1280), non étiré |
| **Recherche et filtres** | Champ pleine largeur ; bouton « Rechercher » **sous** le champ (48 px) ; **« Filtres (n) »** ouvre un panneau plein écran ; filtres appliqués en **chips** sous la recherche, avec « Tout effacer » | Idem (2 chips visibles avant retour à la ligne) | Champ + bouton en ligne ; panneau de filtres en volet latéral modal (400 px) | Filtres **permanents** en colonne gauche (280 px) | Idem 1024 ; grille de résultats 3–4 colonnes |
| **Cartes et listes** | 1 colonne ; **chaque carte garde prix, délai, vendeur/état** ; titre sur ≤ 2 lignes, détail complet au clic | Idem | 2 colonnes de cartes ; listes en cartes | 3 colonnes (catalogue) ; **listes denses** possibles en tableau (commandes) avec mêmes colonnes | 3–4 colonnes ; tableaux à largeur bornée |
| **Comparaison de propositions** | Cartes **empilées**, triables ; chaque carte porte **les 6 lignes étiquetées** (prix, délai, livrables, corrections, validité, version) | Idem | Cartes sur 2 colonnes ; mode **« Mettre côte à côte »** pour 2 propositions (lignes = critères) | Grille de 3 cartes ; mode côte à côte jusqu'à **3** (colonnes = propositions, lignes = critères, en-tête du critère répété) | Idem 1024, plus d'air |
| **Formulaires** | 1 colonne ; **étapes** pour les formulaires longs (mission : 5 étapes) avec « Étape n sur 5 » ; barre d'actions **non masquante** ; brouillon automatique | Idem | 1 colonne ≤ 640 px centrée ; sections visibles | Formulaire (640) + **panneau d'aperçu/récapitulatif collant** (320–360) | Idem 1024 |
| **Commande (C05)** | En-tête compact (réf., **état, échéance, montant** toujours visibles) ; bloc « Action attendue » ; **onglets en puces sur 2 rangées** ; barre d'action collante **seulement** si action attendue | Idem | Onglets sur 1 rangée ; résumé en en-tête | **2 colonnes** : contenu (8/12) + **résumé collant** (4/12) | Idem, largeur bornée |
| **Messages et fichiers** | Liste → conversation en écran distinct ; zone de saisie **fixe en bas avec marge compensée** ; ajout de fichier depuis l'appareil (galerie/fichiers) ; progression et résultat de contrôle par fichier | Idem | Liste (320) + conversation côte à côte | Idem + panneau de contexte (commande/mission) à droite | Idem, largeur bornée |
| **Suivi financier** | Lignes financières en **cartes** : libellé, **montant**, **état**, **référence**, **date de dernier contrôle** | Idem | Cartes sur 2 colonnes ou tableau 4 colonnes | **Tableau** (mêmes champs) + totaux | Idem |

**Garanties de conception communes** (SRC N02, N03 ; DEC D10) : aucun défilement horizontal **de la page** ; **aucun montant, date ou statut tronqué** (retour à la ligne, jamais d'ellipse sur un champ essentiel) ; cibles **≥ 44 × 44 px** ; **zoom 200 %** = équivalent de ≈ 180 px de large CSS sur un 360 : les mises en page 1 colonne doivent rester lisibles et complètes ; aucun fonctionnement dépendant du **survol** ; navigation **clavier** complète avec focus visible ; barres fixes **ne masquent ni contenu ni champ** (marge basse compensée, retrait quand le clavier virtuel s'ouvre).

### 0.3 États standard (SRC ARB §14, CDC §4) — modèles de message

| État | Comportement | Exemple de libellé |
|---|---|---|
| **Chargement** | Structure conservée (squelette) ; texte pour lecteur d'écran ; double soumission empêchée | « Chargement de vos commandes… » |
| **Vide** | Dit ce qui manque **et** propose l'action utile | « Aucune proposition reçue pour l'instant. [Vérifier le contenu de votre besoin] » |
| **Recherche sans résultat** | Filtres appliqués visibles et retirables ; suggestion moins restrictive ; **« Publier une mission »** | « Aucun service ne correspond. Retirez un filtre ou publiez votre besoin. » |
| **Erreur de saisie** | Message près du champ + résumé ; données **conservées** ; focus sur la première erreur | « Le prix doit être compris entre 5 000 et 500 000 FCFA. » *(bornes = paramètres, à valider)* |
| **Erreur système** | Message sobre, **identifiant de corrélation**, action | « Un problème est survenu de notre côté. Vos données ne sont pas perdues. Code : A7F3-91. [Réessayer] » |
| **Succès** | Nouvel état + référence + **action suivante** ; jamais « paiement terminé » avant vérification serveur | « Demande envoyée. Réponse attendue avant le 7 oct. 14:30. [Voir ma commande] » |
| **Accès interdit / absent** | **Même message** pour « n'existe pas » et « pas à vous » ; aucune partie, montant ni fichier ; connexion proposée seulement si elle permet la reprise autorisée | « Cette page est introuvable ou vous n'y avez pas accès. » |
| **Incertitude financière** | « Vérification en cours », date du dernier contrôle, support ; **aucune invitation à relancer** | « Nous vérifions votre paiement (dernier contrôle à 14:12). » |

### 0.4 Données de démonstration (DEC INS §5 ; SRC N04)

Bandeau permanent « Démonstration » (`03` §6.8) ; références `DEMO-…` ; identités manifestement fictives ou **exemples de prestations sans nom** ; aucune statistique réelle alimentée ; pas de faux avis, volume ou badge. Un contenu réel et un contenu fictif ne sont **jamais mélangés dans la même liste** sans marquage individuel.

### 0.5 Calcul des boutons disponibles (SRC ARB §10, F23)

Les actions affichées sont **calculées** par rôle, droits et état réel (`Presenter` d'écran) et **revérifiées par le serveur** à l'exécution : un bouton manipulé hors interface est refusé (`409`/`403`). Les états financiers (paiement, remboursement, reversement) sont affichés **séparément** de l'avancement du travail.

---

## 1. Inventaire des 46 gabarits (SRC ARB §2–§6 ; décompte vérifié : 10 + 5 + 8 + 5 + 8 + 10)

Routes : reprises de ARB, **notation Laravel** `{param}` (PROP, voir `01` C06). « Niveau » : **A** = approfondi dans ce document ; **I** = inventorié, conçu avec les composants communs ; **R** = reportable selon CDC §2.

### 1.1 Public (visiteur)

| Repère | Écran | Route | Acteur | Réfs | Niveau |
|---|---|---|---|---|---|
| P01 | Accueil | `/` | Visiteur, tous | F11 | **A §2** |
| P02 | Catalogue des services | `/services` | Visiteur | F11, F13 | **A §2** (recherche) |
| P03 | Détail du service | `/services/{slug}` | Visiteur → client | F12, F13, F19–F20 | **A §3** |
| P04 | Liste des missions | `/missions` | Visiteur, freelance | F14 | I |
| P05 | Détail de la mission | `/missions/{id}` | Visiteur, freelance | F14–F16 | I (voir §5 pour l'envoi de proposition) |
| P06 | Annuaire des freelances | `/freelances` | Visiteur | F04, F11 | I |
| P07 | Profil public | `/freelances/{slug}` | Visiteur | F04, F05, F36 | I |
| P08 | Fonctionnement | `/comment-ca-marche` | Visiteur | CDC §4 | I |
| P09 | Centre d'aide | `/aide` | Visiteur, tous | CDC §4 | I |
| P10 | Informations et conditions | `/informations/{page}` | Visiteur | F01, N15, N18 | I |

### 1.2 Compte et pages communes

| Repère | Écran | Route | Acteur | Réfs | Niveau |
|---|---|---|---|---|---|
| A01 | Inscription | `/inscription` | Visiteur | F01 | I |
| A02 | Connexion | `/connexion` | Visiteur | F02, N11 | I |
| A03 | Vérification de l'adresse | `/verification-email` | Compte | F02 | I |
| A04 | Mot de passe oublié | `/mot-de-passe-oublie` | Visiteur | F02 | I |
| A05 | Nouveau mot de passe | `/reinitialiser-mot-de-passe` | Visiteur | F02, N11 | I |
| C01 | Compte | `/espace/compte` | Tous | F03, F06, F38 | I |
| C02 | Messages | `/espace/messages`, `/{id}` | Tous | F37 | **A** (§8 onglet Messages) |
| C03 | Notifications | `/espace/notifications` | Tous | F38 | I |
| C04 | Liste des commandes | `/espace/commandes` | Client, freelance | F23 | I (liée à §8) |
| C05 | Commande | `/espace/commandes/{id}` | Client, freelance | F19–F23, F29–F36 | **A §8** |
| C06 | Paiement de la commande | `/espace/commandes/{id}/paiement` | Client | F24–F25 | **A §9** |
| C07 | Support | `/espace/support`, `/{id}` | Tous | F41 | I |
| C08 | Favoris | `/espace/favoris` | Tous | F13 | I — **R** |

### 1.3 Espace client

| Repère | Écran | Route | Réfs | Niveau |
|---|---|---|---|---|
| CL01 | Tableau de bord client | `/espace/client` | ARB §4 | **A §6** |
| CL02 | Mes missions | `/espace/client/missions` | F14, F18 | I |
| CL03 | Éditeur de mission | `/espace/client/missions/nouvelle`, `/{id}/modifier` | F14, F15 | **A §4** |
| CL04 | Mission et propositions reçues | `/espace/client/missions/{id}` | F16–F18 | **A §5** |
| CL05 | Historique des paiements | `/espace/client/paiements` | F24–F28 | I |

### 1.4 Espace freelance

| Repère | Écran | Route | Réfs | Niveau |
|---|---|---|---|---|
| FR01 | Tableau de bord freelance | `/espace/freelance` | ARB §5 | **A §7** |
| FR02 | Profil et portfolio | `/espace/freelance/profil` | F04, F05 | I |
| FR03 | Mes services | `/espace/freelance/services` | F09, F10 | I |
| FR04 | Éditeur de service | `/espace/freelance/services/nouveau`, `/{id}/modifier` | F09, F10 | I |
| FR05 | Mes propositions | `/espace/freelance/propositions` | F16 | I |
| FR06 | Éditeur de proposition | `/espace/freelance/missions/{id}/proposition` | F16 | **A §5** |
| FR07 | Reversements | `/espace/freelance/reversements` | F26, F28 | I (partie dans §7) |
| FR08 | Bénéficiaire de paiement | `/espace/freelance/beneficiaire` | F08 | I |

### 1.5 Administration

| Repère | Écran | Route | Réfs | Niveau |
|---|---|---|---|---|
| AD01 | Tableau de bord / files de travail | `/admin` | F42 | **A §10** |
| AD02 | Publications | `/admin/publications` | F10, F39 | **A §10** |
| AD03 | Utilisateurs | `/admin/utilisateurs` | F40, F07 | I |
| AD04 | Signalements | `/admin/signalements` | F37, F39 | I |
| AD05 | Commandes | `/admin/commandes` | F41 | I |
| AD06 | Transactions | `/admin/transactions` | F25–F28, F41 | I (rapprochement : §10 « dossiers à traiter ») |
| AD07 | Litiges | `/admin/litiges`, `/{id}` | F34–F35 | I (§10 « dossiers à traiter ») |
| AD08 | Support | `/admin/support`, `/{id}` | F41 | I (§10 « dossiers à traiter ») |
| AD09 | Paramètres | `/admin/parametres` | F42 | I |
| AD10 | Journal | `/admin/journal` | N14 | I |

**Écarts avec les captures** (`01` C07) : IMG-2/3/4 ne montrent que 6 + 7 + 4 entrées de menu ; **FR08 (Bénéficiaire)**, **C07 (Support)**, **C03 (Notifications)** et **AD03–AD10** n'y apparaissent pas ; le « Profil public » de IMG-3 correspond à FR02 + P07.

### 1.6 Menus proposés (PROP, ARB §1 + captures)

| Espace | Entrées (ordre) |
|---|---|
| Public | Services · Missions · Freelances · Comment ça marche — [Publier une mission] |
| Client | Vue d'ensemble · Mes missions · Commandes · Paiements · Messages · Favoris · Compte |
| Freelance | Vue d'ensemble · Mes services · Propositions · Commandes · Reversements · Bénéficiaire · Messages · Profil public · Compte |
| Commun (en-tête) | Messages (compteur en texte) · Notifications · Aide |
| Administration | Files de travail · Publications · Signalements · Litiges · Support · Commandes · Transactions · Utilisateurs · Paramètres · Journal — **filtré par habilitation** |

---

## 2. Accueil et recherche (P01, P02)

| | |
|---|---|
| **Acteur** | Visiteur, ou utilisateur connecté qui cherche un service |
| **Objectif** | Comprendre en 5 secondes ce que fait FreeCI et **lancer une recherche** ou **publier une mission** (SRC ARB §7, F11) |
| **Informations prioritaires** | Promesse descriptive · champ de recherche · 8 catégories · services **réellement publiés** · deux façons de démarrer (service / mission) |
| **Action principale** | **Rechercher** (« Rechercher ») |
| **Actions secondaires** | **Publier une mission** (en-tête + lien sous la recherche) · parcourir une catégorie · « Comment ça marche » · « Proposer mes services » |
| **Effet annoncé** | Sous la recherche : « Vous verrez les services publiés correspondant à votre recherche. » ; sous « Publier une mission » : « Décrivez votre besoin, recevez des propositions chiffrées. » |

### 2.1 Maquette — ordinateur 1440 px (contenu borné à 1200 px)

```text
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ [F•] FreeCI   Services  Missions  Freelances  Comment ça marche     [Publier une mission] ✉ [Se connecter] │  64 px, fond blanc
├──────────────────────────────────────────────────────────────────────────────────────────┤
│▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓│ bandeau marine-900
│▓ DES COMPÉTENCES EN CÔTE D'IVOIRE            ┌─────────────────────────────────┐ ▓│  ≈ 440 px de haut
│▓ Un freelance pour votre prochain projet.    │ Services récemment publiés      │ ▓│  texte : 7/12 (≈ 680)
│▓ (Display 48/54, blanc)                      │ ───────────────────────────────│ ▓│  carte : 5/12 (≈ 440), r-lg
│▓ Plans, développement, design… Trouvez une   │ [img] Titre du service      ➜  │ ▓│
│▓ prestation ou publiez votre besoin.         │       Vendeur · 5 j · 35 000 FCFA│ ▓│
│▓ Que recherchez-vous ?                       │ [img] …                         │ ▓│
│▓ ┌───────────────────────────┐[Rechercher]  │ [img] …                         │ ▓│
│▓ │🔍 Un plan AutoCAD, un logo…│  (56 px)     │ Voir tous les services →        │ ▓│
│▓ └───────────────────────────┘              └─────────────────────────────────┘ ▓│
│▓ Un besoin précis ? Publier une mission →                                         ▓│
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Quel est votre besoin ?                                          Tous les services →    │ H2
│ [BTP et Architecture] [Ingénierie et Industrie] [Développement…] [Design et Graphisme]  │ 4 × 2 tuiles, 56 px mini
│ [Photo et Vidéo]      [Marketing et Communication] [Rédaction…]  [Formation…]           │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Deux façons de démarrer                                                                  │
│ ┌ Je choisis un service ───────────────┐  ┌ Je publie une mission ────────────────────┐ │ 2 cartes 50/50
│ │ 1 Je compare  2 Je décris mon besoin │  │ 1 Je décris  2 Je reçois des propositions │ │
│ │ 3 Le freelance accepte 4 Je paie     │  │ 3 Je choisis 4 Je paie                    │ │
│ │ [Voir les services]                  │  │ [Publier une mission]                     │ │
│ └──────────────────────────────────────┘  └────────────────────────────────────────────┘ │
│ Après le paiement : livraison, corrections, validation, suivi du reversement.           │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Vous êtes freelance ? [Créer mon profil]   ·   Pied de page : aide, conditions, mentions │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**Variante « moins de 3 services publiés »** : la carte de droite du bandeau est **remplacée** par « Comment ça marche » en 3 étapes ; la section « Services récemment publiés » n'est pas affichée (aucun remplissage fictif). **Variante démonstration** : la carte porte l'étiquette « **Exemples de prestations** » (14 px, contraste ≥ 6:1, **non masquable**), les entrées sont des prestations **sans nom de personne** (« Convertir vos plans PDF en fichiers AutoCAD · exemple ») et le bandeau « Démonstration » est présent.

### 2.2 Maquette — téléphone 360/390 px

```text
┌ 360 ───────────────────┐
│ [F•] FreeCI  ✉   [Menu]│ 56 px
├────────────────────────┤
│▓ DES COMPÉTENCES EN    │ bandeau marine
│▓ CÔTE D'IVOIRE         │ padding 24/16
│▓ Un freelance pour     │ Display 32/38
│▓ votre prochain projet.│
│▓ Trouvez une prestation│
│▓ ou publiez votre      │
│▓ besoin.               │
│▓ Que recherchez-vous ? │
│▓ ┌────────────────────┐│ champ 48 px
│▓ │🔍 Un plan AutoCAD… ││
│▓ └────────────────────┘│
│▓ [   Rechercher   ]    │ bouton pleine largeur 48 px
│▓ Publier une mission → │ lien 44 px
├────────────────────────┤
│ Quel est votre besoin ?│
│ [BTP et Archi…][Ingé…] │ 2 colonnes, 8 tuiles
│ …                      │
├────────────────────────┤
│ Services publiés  (n)  │ cartes empilées (image 4:3 + infos)
│ [carte service]        │
│ Voir tous les services │
├────────────────────────┤
│ Deux façons de démarrer│ cartes empilées
└────────────────────────┘
```

Pas de barre fixe en bas. Les libellés de catégorie passent à la ligne (jamais d'ellipse) ; la tuile garde 56 px de haut minimum.

### 2.3 Détails de conception

- **Champ de recherche** : étiquette **visible** « Que recherchez-vous ? » ; l'exemple « Un plan AutoCAD, un logo, un site web » est un *placeholder* **illustratif** (jamais l'unique étiquette). Pas de suggestions en direct en V1 (PROP, à évaluer après mesure N06).
- **Catégories** : 8, noms **identiques** partout (recherche, service, mission, administration — ARB §7). Nombre de services par catégorie affiché **seulement s'il est réel et > 0**.
- **Promesse** : descriptive, sans superlatif ni chiffre non établi. Les phrases sur le paiement (« paiement protégé », « reversement après validation ») **ne sont affichées qu'après qualification du prestataire** (Q03) ; en démonstration : « Paiement simulé ».
- **Aucun** faux avis, volume d'activité, « N freelances vérifiés », « note moyenne » ou badge non justifié (DEC INS §5, SRC N04).

### 2.4 Résultats de recherche (P02)

| Zone | Contenu |
|---|---|
| En-tête | H1 « Services » ; champ de recherche ; **« 27 services »** (compte réel) ; tri (Pertinence · Prix croissant · Prix décroissant · Plus récents) |
| Filtres (F11) | Catégorie · compétence · **prix min/max en FCFA** · **délai maximal (jours)** · ville · mode (à distance / sur place). Valeurs validées côté serveur |
| Chips | Filtres appliqués, retirables un par un + « Tout effacer » |
| Résultats | Cartes de service (`03` §6.4), **20 par page maximum** (F11), pagination numérotée avec libellés « Page 2 sur 4 » |
| Favori | Cœur **avec libellé accessible** « Ajouter aux favoris » ; sur invité : renvoie à la connexion avec reprise (ARB §1) |

**Mobile (360)** : bouton « Filtres (2) » 48 px ouvre un panneau **plein écran** (champs groupés, boutons « Réinitialiser » et « Afficher 27 résultats » fixés en bas avec marge compensée). **Ordinateur (≥ 1024)** : colonne de filtres permanente 280 px, résultats en 3 colonnes.

### 2.5 États

| État | Comportement |
|---|---|
| Chargement | Squelette de 6 cartes ; le bandeau d'accueil est statique et immédiat |
| Vide (aucun service publié) | « Les premiers services seront publiés ici. » + **[Publier une mission]** et **[Proposer mes services]** (aucun contenu fictif hors démonstration) |
| Recherche sans résultat | « Aucun service ne correspond à *« charpente »* avec ces filtres. » + chips retirables + **[Publier une mission]** (SRC CDC §4) |
| Erreur | « La recherche est momentanément indisponible. » + **[Réessayer]** ; les catégories restent utilisables (liens directs) |
| Succès | Sans objet (navigation) |
| Accès interdit | Sans objet (public) ; un ancien service **archivé/suspendu** affiche « Ce service n'est plus disponible » avec alternatives, sans donnée privée (SRC ARB §2) |

### 2.6 Contrats métier appelés

| Élément | Contrat (lecture publique) | Réfs |
|---|---|---|
| Recherche et filtres | `Catalog\SearchServices` — filtres/tri **validés au serveur**, pagination ≤ 20, **uniquement** services publiés | F11, ARC §3 |
| Catégories | `Catalog\ListCategories` | CDC §3 |
| Services récents | `Catalog\ListPublishedServices` (aucun volume inventé) | F12 |
| Favori | `Catalog\ToggleFavorite` (auth ; privé) | F13 |

---

## 3. Détail d'un service et demande de prestation (P03)

| | |
|---|---|
| **Acteur** | Visiteur → client vérifié (≠ auteur du service) |
| **Objectif** | Évaluer l'offre et **envoyer un brief** pour obtenir l'acceptation du freelance (SRC F12, F20) |
| **Informations prioritaires** | Titre · **prix** · **délai** · **corrections incluses** · livrables et formats · périmètre et exclusions · éléments à fournir · vendeur · avis réels |
| **Action principale** | **Demander cette prestation** |
| **Actions secondaires** | Contacter le freelance (privé) · ajouter aux favoris · signaler le service |
| **Effet annoncé** | « Vous décrivez votre besoin. Le freelance a **48 h** pour accepter. **Vous ne payez qu'après son acceptation.** » *(48 h : paramètre proposé, lu dans la politique en vigueur)* |

### 3.1 Maquette — ordinateur 1440 px

```text
┌ En-tête public ───────────────────────────────────────────────────────────────────────┐
│ Services › BTP et Architecture › Convertir vos plans PDF en fichiers AutoCAD            │ fil d'Ariane
├───────────────────────────────────────────────┬───────────────────────────────────────┤
│ H1 Convertir vos plans PDF en fichiers AutoCAD│  ┌ Offre ───────────────────────────┐  │ colonne droite ≈ 360 px
│ [avatar] Vendeur · Abidjan · [E-mail vérifié] │  │ 35 000 FCFA        (Montant 24/30)│ │ collante (top 80 px)
│                                               │  │ ⏱ Délai : 5 jours                 │ │
│ ┌ Galerie 16:9 (≤ 8 médias) ───────────────┐  │  │ ✎ 2 corrections incluses          │ │
│ │                                           │  │  │ 📦 Livrables : DWG, PDF de contrôle│ │
│ └───────────────────────────────────────────┘  │  │ [ Demander cette prestation ]     │ │ bouton primaire 48 px
│ ▫ ▫ ▫ ▫  (vignettes 64 px, focusables)         │  │ Vous décrivez votre besoin. Le    │ │
│                                               │  │ freelance a 48 h pour accepter.   │ │ phrase d'effet
│ Ce que vous recevez          (H2)             │  │ Vous ne payez qu'après.           │ │
│  • livrables et formats remis …               │  │ ♡ Favori · ✉ Contacter · ⚑ Signaler│ │
│ Ce qui n'est pas inclus                       │  └──────────────────────────────────┘ │
│ Ce que je dois recevoir de vous (éléments)    │  ┌ À propos du vendeur ─────────────┐  │
│  nombre de plans · niveaux · qualité du PDF…  │  │ titre, ville, langues, dispo      │ │
│ Avis (réels, avec date) — ou « Aucun avis »   │  │ Profil complet →                  │ │
└───────────────────────────────────────────────┴───────────────────────────────────────┘
        contenu 8/12 ≈ 760 px                                  4/12 ≈ 360 px
```

**Téléphone (360/390)** : colonne unique. Ordre : fil d'Ariane réduit (« ‹ Services ») · H1 · **bloc prix/délai/corrections** (carte compacte, 3 lignes étiquetées) · galerie (swipe + boutons précédent/suivant visibles) · sections de contenu · vendeur · avis. **Barre d'action collante en bas** : `35 000 FCFA · 5 jours` + **[Demander]** (hauteur 72 px incluant la zone sûre) ; la page réserve la même hauteur en marge basse ; la barre **disparaît** quand le formulaire de demande est ouvert ou le clavier affiché.

### 3.2 Formulaire de demande (rattaché à P03 — pas de gabarit permanent, SRC ARB §11)

Présentation : **panneau latéral 480 px** (≥ 1024) ou **écran plein** « Décrire votre besoin » (< 1024), en 3 étapes avec « Étape n sur 3 ».

| Étape | Contenu | Règles |
|---|---|---|
| 1 — Rappel | Service, **prix, délai, corrections**, **version du service** consultée | La version est **liée** à la demande (accord figé, F19) |
| 2 — Votre besoin | Champs **générés par les « éléments à fournir »** du service (ex. PDF→DWG : *nombre de plans, surfaces ou niveaux, qualité du PDF, formats remis, version logicielle de destination*) + précisions libres ; **fichiers** (liste, taille, progression, contrôle) | Champs obligatoires annoncés « (obligatoire) » ; **brouillon privé** sauvegardé ; jamais dans l'URL (SRC ARB §1) |
| 3 — Récapitulatif | Prix, délai, corrections, brief résumé ; **« Ce qui se passera »** : 1) le freelance reçoit votre demande ; 2) il accepte ou refuse sous 48 h ; 3) après acceptation vous payez sous 24 h ; 4) le travail commence après paiement confirmé et brief complet | Case **« J'ai lu les conditions de commande (v…) »** → version et date enregistrées (F19) |
| Envoi | **[Envoyer la demande]** (verrouillé pendant l'envoi) | Un dépassement du périmètre du service donne lieu à une **nouvelle proposition avant paiement** (CDC §6) |

### 3.3 Garde-fous d'accès (SRC F02, F20, ARB §1)

| Situation | Comportement du bouton principal |
|---|---|
| Visiteur | Mène à la connexion/inscription avec **reprise sur la même page** (retour interne vérifié) |
| Adresse non vérifiée | Mène à A03 ; reprise ensuite |
| Auteur du service | Bouton **absent** ; texte « C'est votre service. [Modifier] » ; l'achat est de toute façon refusé côté serveur |
| Compte limité/suspendu | Texte « Votre compte ne permet pas de nouvelle demande », motif et recours (F07) |

### 3.4 États

| État | Comportement |
|---|---|
| Chargement | Squelette (titre, galerie, offre) |
| Vide partiel | « Aucun avis pour l'instant. » (pas d'invention) ; médias absents : emplacement neutre |
| Erreur d'envoi | Résumé + champs ; **données conservées** ; « Votre brief est enregistré, vous pouvez réessayer. » |
| Succès | Écran « **Demande envoyée à [vendeur]** » : référence, **échéance de réponse (date + heure d'Abidjan)**, « Vous serez notifié sur FreeCI et par courriel. » · **[Voir ma commande]** (C05, état « À accepter ») |
| Accès interdit | Service non publié/archivé : « Ce service n'est plus disponible » ; commande d'autrui : message d'accès `0.3` |

### 3.5 États métier particuliers

- **Service modifié entre la consultation et l'envoi** : `409` → bandeau « Ce service a été modifié (prix, délai ou périmètre). Relisez les conditions mises à jour avant d'envoyer. » avec **comparaison avant/après**. Aucune acceptation silencieuse.
- **Service suspendu** pendant la rédaction : envoi refusé, brouillon conservé.
- **Demande hors périmètre** : le freelance répond par « précision » ou refuse ; un changement de prix = **nouvelle commande** (SRC F31, CDC §6).

### 3.6 Contrats métier appelés

| Bouton | Contrat | Réfs |
|---|---|---|
| Affichage | `Catalog\GetPublishedService` (projection publique à liste de champs explicite) | ARC §3 |
| Brouillon du brief | `Orders\SaveServiceRequestDraft` (privé) | ARB §1 |
| **Envoyer la demande** | `Orders\RequestService` — `serviceVersionId`, réponses, fichiers `clean`, `operationKey` → commande `awaiting_acceptance`, accord figé, conversation | F12, F19, F20 |
| Contacter | `Communication\StartConversation` (contexte service) | F13, F37 |
| Favori / Signaler | `Catalog\ToggleFavorite` · `Administration\ReportContent` | F13, F39 |

---

## 4. Publication d'une mission (CL03)

| | |
|---|---|
| **Acteur** | Client vérifié (A03 passée) |
| **Objectif** | Décrire un besoin assez clairement pour recevoir des **propositions chiffrées comparables** (SRC F14) |
| **Informations prioritaires** | Besoin et livrables · budget · échéances · mode de réalisation · pièces privées |
| **Action principale** | **Soumettre la mission** (active quand les champs requis sont valides) |
| **Actions secondaires** | Enregistrer le brouillon · Voir l'aperçu public · Supprimer le brouillon |
| **Effet annoncé** | « Votre mission sera **examinée avant d'être visible**. Vous êtes notifié(e) sur FreeCI et par courriel de la décision. Les pièces restent **privées** : vous choisissez à qui les transmettre. » |

### 4.1 Maquette — ordinateur (≥ 1024 px)

```text
┌ Espace client ┬──────────────────────────────────────────────────────────────────────────┐
│ menu 248 px   │ Mes missions › Nouvelle mission                                           │
│               │ H1 Publier une mission        Brouillon enregistré à 14:12 ✓              │
│               │ ┌ Formulaire 1 colonne (640 px) ─────────────┐ ┌ Aperçu public (360 px) ┐ │
│               │ │ ① Votre besoin                              │ │ Ce que verront les     │ │
│               │ │  Titre (obligatoire) [__________] 0/100     │ │ freelances             │ │
│               │ │  Catégorie ▾  Compétences [+]               │ │ ────────────────────── │ │
│               │ │  Description du besoin   0/5 000            │ │ Titre …                │ │
│               │ │  💡 Aide : documents de départ, limites,    │ │ Catégorie · Budget     │ │
│               │ │   formats attendus, validations nécessaires │ │ Échéance · Mode        │ │
│               │ │ ② Budget et calendrier                      │ │ Compétences            │ │
│               │ │  ( ) Montant fixe  ( ) Fourchette  [____]FCFA│ │ ⚠ Coordonnées privées  │ │
│               │ │  Date limite de candidature  [__/__/____]   │ │   détectées : 1        │ │
│               │ │  Échéance souhaitée du projet [__/__/____]  │ │ ────────────────────── │ │
│               │ │ ③ Réalisation                               │ │ Pièces : privées (non  │ │
│               │ │  ( ) À distance  ( ) Sur place → Ville [__] │ │ affichées)             │ │
│               │ │  Livrables et formats attendus              │ └────────────────────────┘ │
│               │ │ ④ Pièces privées (non publiques)            │      collant sous l'en-tête │
│               │ │  [Ajouter des fichiers]  nom·taille·contrôle│                              │
│               │ │ ⑤ Vérifier et envoyer                       │                              │
│               │ │  [Enregistrer le brouillon] [Soumettre…]    │                              │
│               │ └─────────────────────────────────────────────┘                              │
└───────────────┴──────────────────────────────────────────────────────────────────────────┘
```

**Téléphone (360/390)** : **un bloc par écran** (5 étapes), indicateur « Étape 2 sur 5 — Budget et calendrier », boutons **Retour** et **Continuer** en pied de page (non fixes lorsque le clavier est ouvert) ; l'**aperçu public** est la dernière étape (« Vérifier et envoyer »). Le brouillon est **enregistré automatiquement** à chaque changement d'étape et sur demande ; fermer le panneau **n'efface rien sans l'indiquer** (SRC ARB §14).

### 4.2 Champs et contrôles (SRC ARB §8, F14 ; **bornes = propositions à valider**)

| Bloc | Champs | Contrôle |
|---|---|---|
| Votre besoin | Titre, catégorie, compétences, description | Titre 15–100 car. et description 150–5 000 car. **par analogie avec les services** (CDC §6) — **Q18** : bornes propres aux missions ? |
| Budget et calendrier | Montant **fixe** ou **fourchette** (FCFA, entiers) ; **date limite de candidature** ; **échéance souhaitée** | Bornes de montant = paramètres (5 000–500 000 FCFA proposés) ; date limite < échéance souhaitée ; **durée réalisable promise = autre chose** (proposition du freelance, CDC §7) |
| Réalisation | À distance / sur place + ville ; livrables et formats | Ville requise si sur place ; responsabilités particulières (ex. dimensionnement vs production de plans, CDC §7) à décrire dans le besoin |
| Pièces privées | Fichiers | JPEG/PNG/WebP/PDF + formats métiers autorisés ; **privés par défaut** ; limites proposées 10 Mo (médias publics) / 100 Mo (livraisons) ; contrôle de type réel et quarantaine (N13). **L'ajout ne rend rien public** |
| Vérifier et envoyer | Aperçu public ; **détection de coordonnées privées** (téléphone, adresse e-mail) dans le texte public | Avertissement bloquant à la soumission tant que des coordonnées restent dans le texte public ; le modérateur revérifie (F39) |

**Aide à la rédaction** (ARB §8) : modèle de plan (« Documents de départ · Limites de l'étude · Formats attendus · Validations nécessaires · Responsabilités »), avec l'exemple de la **charpente métallique** (CDC §7) ; cet exemple est une **aide**, jamais une mission publiée.

### 4.3 États

| État | Comportement |
|---|---|
| Chargement | Squelette du formulaire ; brouillon restauré |
| Vide (CL02, aucune mission) | « Vous n'avez pas encore publié de mission. [Publier une mission] » |
| Erreur de saisie | Résumé en tête (liens vers champs) + message sous chaque champ ; focus sur la première erreur ; **tout est conservé** |
| Erreur système | « Votre brouillon est enregistré sur FreeCI. Réessayez dans un instant. Code : … » |
| Succès | « **Mission soumise.** Elle est en cours de contrôle (état : *En contrôle*). Nous vous notifions dès sa publication. » + **[Voir ma mission]** (CL04) |
| Accès interdit | Adresse non vérifiée → A03 avec reprise ; compte limité → message + recours |

### 4.4 États métier particuliers

| État de la mission | Ce que voit le client |
|---|---|
| Brouillon | Éditable ; invisible du public |
| Soumise (**En contrôle**) | Lecture ; possibilité de retirer |
| **À corriger** | **Motif du modérateur visible dans l'éditeur** (SRC ARB §5) ; corriger puis re-soumettre |
| Ouverte | Publique (sans pièces ni coordonnées) ; propositions visibles du seul client |
| Réservée / attribuée / fermée | Voir §5 ; fermée = historique conservé |

**Q14 (non bloquante)** : modification d'une mission **ouverte** ayant déjà reçu des propositions. **PROP** : changements majeurs (budget, périmètre) → nouveau contrôle, et les propositions existantes affichent « Besoin modifié depuis votre proposition » avec invitation à les mettre à jour.

### 4.5 Contrats métier appelés

| Bouton | Contrat | Réfs |
|---|---|---|
| Enregistrer le brouillon | `Missions\SaveMissionDraft` (champs incomplets admis) | ARC §4 |
| Ajouter des fichiers | `Files\UploadPrivateFile` (quarantaine, contrôle, clé opaque) | F15, N13 |
| **Soumettre la mission** | `Missions\SubmitMission` — tous les champs requis, version | F14, F15 |
| Fermer / rouvrir | `Missions\CloseMission`, `Missions\ReopenMission` (historique conservé) | F18 |
| Décision de modération | `Missions\PublishMission` / `Missions\RequestMissionFix` (voir §10) | F39 |

---

## 5. Proposition du freelance et comparaison côté client (FR06, CL04)

### 5.1 FR06 — Envoyer une proposition

| | |
|---|---|
| **Acteur** | Freelance vérifié, **différent du client** de la mission |
| **Objectif** | Répondre au besoin avec un **accord clair** : prix ferme, durée, livrables, corrections, validité |
| **Action principale** | **Envoyer la proposition** |
| **Secondaires** | Enregistrer le brouillon · Prévisualiser · Retirer (si déjà envoyée) |
| **Effet annoncé** | « Le client peut choisir votre proposition **jusqu'au [date de validité]**. Vous pouvez la modifier ou la retirer **tant qu'elle n'est pas choisie** ; chaque modification crée une **nouvelle version**. Une seule proposition active par mission. » |

**Organisation** : rappel du besoin (carte repliable : titre, budget, **échéance souhaitée**, livrables — **jamais** d'information d'un autre candidat) puis quatre blocs (ARB §8) : **Réponse au besoin** (message, livrables proposés) · **Accord commercial** (prix ferme entier en FCFA, durée en jours, corrections incluses, **date de fin de validité**, par défaut +7 jours — paramètre) · **Vérification** (aperçu « tel que le verra le client » ; alerte non bloquante : « Votre durée de 15 jours dépasse l'échéance souhaitée du client (10 oct.). Le client en sera averti avant de vous choisir ») · **Envoi**.

**Variantes** : *proposition existante* → titre « Modifier ma proposition (version 2) » + **historique des versions** (lecture seule) ; *mission réservée/fermée* → écran d'explication sans donnée privée ; *propre mission* → refus « Vous ne pouvez pas répondre à votre propre mission » (F16) ; *adresse non vérifiée* → A03.

**États** : chargement (squelette) · vide (sans objet) · erreur (champ + résumé, données conservées) · **succès** (« Proposition envoyée — version 1. Valable jusqu'au … [Voir mes propositions] ») · accès interdit (mission non ouverte : message d'accès).

**Contrats** : `Missions\SubmitProposal` (crée une proposition ou **ajoute une version**, vérifie l'unicité), `Missions\WithdrawProposal`, lecture `Missions\GetMissionForProposal` (projection **publique** de la mission). Réfs : F16 ; ARC §4 (`proposal_active_uq`).

### 5.2 CL04 — Comparer et choisir

| | |
|---|---|
| **Acteur** | Client propriétaire de la mission |
| **Objectif** | Comparer des offres **comparables** et choisir **une** version valide |
| **Informations prioritaires** | Par proposition : **prix ferme**, **délai de réalisation**, livrables, corrections, **validité**, **version**, profil, message |
| **Action principale** | **Choisir cette proposition** (par carte) |
| **Secondaires** | Voir le profil · Poser une question (conversation privée) · Comparer (sélection de 2–3) · Trier |
| **Effet annoncé** (récapitulatif) | « La mission sera **réservée à ce freelance**. Vous payez sous **24 h** (délai proposé). Les autres candidats sont informés **après paiement confirmé**. Si vous ne payez pas, la mission pourra être **rouverte**. » |

```text
┌ Espace client ┬───────────────────────────────────────────────────────────────────────────┐
│               │ Mes missions › Plans de charpente métallique                      [Ouverte]│
│               │ [ Aperçu ] [ Propositions (3) ]                          onglets, URL propre│
│               │ Trier : Prix croissant ▾          [Mettre côte à côte (0/3)]               │
│               │ ┌ Proposition A ─────────┐┌ Proposition B ─────────┐┌ Proposition C ────┐ │ 3 cartes ≈ 31 %
│               │ │ ◉ Nom · Abidjan        ││ ◉ Nom · Bouaké         ││ ◉ …               │ │
│               │ │ [E-mail vérifié]       ││ [E-mail vérifié]       ││                   │ │
│               │ │ 120 000 FCFA  (28/32)  ││ 150 000 FCFA           ││ 135 000 FCFA      │ │
│               │ │ Délai : 12 jours       ││ Délai : 8 jours        ││ Délai : 15 jours  │ │
│               │ │ ⚠ Après votre échéance ││ Corrections : 2        ││ ⚠ Après échéance  │ │
│               │ │ Corrections : 1        ││ Livrables : plans+IFC  ││ …                 │ │
│               │ │ Livrables : plans DWG  ││ Valable jusqu'au 12/10 ││                   │ │
│               │ │ Valable jusqu'au 12/10 ││ Version 2 · modifiée…  ││                   │ │
│               │ │ « Extrait du message… »││ « … »                  ││ « … »             │ │
│               │ │ ☐ Comparer             ││ ☐ Comparer             ││ ☐ Comparer        │ │
│               │ │ [Choisir cette proposition]  Profil · Poser une question                │ │
│               │ └────────────────────────┘└────────────────────────┘└───────────────────┘ │
└───────────────┴───────────────────────────────────────────────────────────────────────────┘
```

**Tri** : prix, délai, date de réception — **pas de « score » ni de classement opaque** (aucune promesse de qualité, SRC F04 badge ne promet pas la qualité). Le **badge** précise **ce qui est vérifié** (« Adresse e-mail vérifiée »), jamais « qualifié ».

**Mode « côte à côte »** (≥ 768 px) : tableau à **lignes = critères** (Prix, Délai, Livrables, Corrections, Validité, Version, Profil) et colonnes = 2–3 propositions ; en-tête de colonne répété au défilement vertical ; **aucun défilement horizontal** (largeur partagée en parts égales ; 2 propositions à 768 px). **< 768 px** : mêmes critères en **cartes empilées**, chaque carte conservant ses 6 lignes étiquetées.

**Récapitulatif de choix** (dialogue plein écran sur mobile, panneau modal 560 px sur ordinateur) : parties · titre · périmètre · livrables · **prix à payer** · corrections · délai · **version** (« Version 2 du 8 oct. 10:12 ») · effet (ci-dessus). Boutons : **[Confirmer et passer au paiement]** / [Revenir aux propositions].

**Versions et validité** (SRC ARB §4, §12 « Choix devenu indisponible ») :

| Situation | Comportement |
|---|---|
| Proposition modifiée après la lecture | Bandeau « Modifiée (v2 → v3) depuis votre lecture. Relisez avant de choisir. » ; **aucune sélection silencieuse** ; bouton actif après affichage de la version à jour |
| Expirée | Carte grisée « Expirée le … » ; non sélectionnable |
| Retirée | « Retirée par le freelance » ; historique conservé |
| Mission **réservée** | Onglet : « Mission réservée à [nom] — paiement attendu avant [date] ». Aucune autre sélection tant que la commande n'a pas expiré ; sans divulguer de donnée privée du candidat choisi |
| Commande expirée | « Le délai de paiement est dépassé. [Rouvrir la mission] » → propositions encore valides redeviennent sélectionnables ; décision tracée (F18) |
| Mission attribuée | Lecture seule ; lien vers C05 |

**États** : chargement (squelettes de cartes) · **vide** (« Aucune proposition pour l'instant. Vérifiez que votre besoin est clair. [Modifier la mission] » — CDC §4/ARB §4) · erreur (conflit `409` expliqué ; indisponibilité `503` avec réessai) · **succès** (sélection → redirection vers C06 avec bandeau « Mission réservée. Payez avant … ») · accès interdit (mission d'un autre client : message d'accès).

**Contrats** : `Missions\ListProposalsForClient` (lecture ; **client propriétaire seulement**) · `Missions\SelectProposal` (`proposalVersionId`, `expectedVersion`, `operationKey` ; verrous mission → proposition ; une seule commande vivante) · `Communication\StartConversation` (contexte mission) · `Missions\ReopenMission`. Réfs : F16–F18, F19 ; T05.


---

## 6. Tableau de bord client (CL01)

| | |
|---|---|
| **Acteur** | Client (compte avec rôle client actif) |
| **Objectif** | Savoir **immédiatement ce qu'il faut faire** et pour quand ; retrouver missions, propositions et commandes |
| **Informations prioritaires** | **Actions attendues avec échéance** · commandes en cours · missions · (en dernier) compteurs |
| **Action principale** | **Celle de l'élément le plus urgent** de « À faire maintenant » ; à défaut **Publier une mission** |
| **Secondaires** | Voir toutes les commandes · Mes missions · Messages |
| **Effet annoncé** | Chaque ligne « À faire » nomme l'effet : « *Examiner la livraison* — Vous pourrez la valider ou demander une correction (1 sur 2 restante). » |

**Différence avec IMG-2** (`01` C08) : la capture présente trois **compteurs** (Commandes actives 1 · Propositions reçues 3 · Missions ouvertes 1) **avant** « Commandes à suivre ». La conception inverse l'ordre : les compteurs sont **conservés**, mais en dernière position et cliquables.

### 6.1 Maquette — ordinateur 1440 px

```text
┌ Espace client ┬────────────────────────────────────────────────────────────────────────────────┐
│ ▎Vue d'ensemble│ ESPACE CLIENT   [Client | Freelance]  (sélecteur d'espace, si deux rôles)       │
│  Mes missions │ H1 Vos projets                                         [Publier une mission]   │
│  Commandes    │ ┌ À faire maintenant (3) ───────────────────────────────────────┐ ┌ Missions ─┐ │
│  Paiements    │ │ ▸ Examiner la livraison v2  · Convertir vos plans PDF… DEMO-26018│ │ Plans char…│ │
│  Messages (2) │ │   Décidez avant le 14 oct. 12:00 (dans 6 j) [Examiner la livraison]│ │ [Ouverte]  │ │
│  Favoris      │ │ ▸ Comparer les propositions (3) · Plans de charpente métallique │ │ 3 proposi- │ │
│  Compte       │ │   Date limite de candidature : 12 oct. [Comparer les propositions]│ │ tions      │ │
│               │ │ ▸ Payer avant 7 oct. 14:30 (dans 22 h) · Logo… [Payer 25 000 FCFA]│ │ [Gérer]    │ │
│               │ └───────────────────────────────────────────────────────────────┘ │ ───────────│ │
│               │ ┌ En attente de l'autre partie (1) ─────────────────────────────┐ │ Voir toutes│ │
│               │ │ Site vitrine · En cours · échéance 20 oct. · 120 000 FCFA        │ └───────────┘ │
│               │ └───────────────────────────────────────────────────────────────┘               │
│               │ Vos chiffres :  Commandes actives 2 ›   Propositions reçues 3 ›   Missions ouvertes 1 › │
└───────────────┴────────────────────────────────────────────────────────────────────────────────┘
        menu 248 px          zone principale 8/12 (≈ 700 px)                  rail 4/12 (≈ 340 px)
```

**Téléphone** : une colonne — H1 · **[Publier une mission]** (secondaire, 44 px) · **À faire maintenant** (cartes pleine largeur ; chaque carte : icône, **verbe + objet**, **échéance en date + « dans 22 h »**, **bouton complet** 48 px) · En attente de l'autre partie · Missions · compteurs (3 liens en liste). Le sélecteur d'espace est dans le tiroir **et** en tête de page pour un compte à deux rôles.

### 6.2 Règles de contenu

| Règle | Détail |
|---|---|
| **Tri de « À faire »** | Échéance la plus proche d'abord ; à égalité, **blocage par le client** avant attente d'autrui |
| **Types d'éléments** (SRC ARB §4, §10) | *Compléter le brief* (commande `awaiting_brief`) · *Payer avant …* (`awaiting_payment`, → C06) · *Examiner la livraison* (`delivered`, → onglet Livraisons de C05) · *Comparer les propositions* · *Répondre à la demande de report* · *Répondre à une question* (message non lu) · *Corriger votre mission* (motif de modération) · *Un paiement est en vérification* (information, **sans** action de relance) |
| **Échéances** | **Date + heure d'Abidjan** et durée relative ; un retard est un **indicateur** « En retard de 2 j », pas un état |
| **Séparation** | « Missions de recrutement » et « Commandes conclues » distinctes (ARB §4) |
| **Compteurs** | Uniquement les dossiers **du compte** ; période indiquée si elle intervient ; **absents des statistiques en démonstration** |
| **Marqueur d'action** | Étiquette pleine `marine-700` (`03` §3.4) distincte du badge d'état |

### 6.3 États

| État | Comportement |
|---|---|
| Chargement | Squelette de 3 lignes « À faire » ; chaque bloc se charge **indépendamment** |
| Vide | « Rien à faire pour l'instant. » + **[Publier une mission]** **[Parcourir les services]** |
| Erreur partielle | Bloc en erreur : « Vos actions ne peuvent pas s'afficher. [Réessayer] — vos commandes restent accessibles : [Commandes] » ; les autres blocs restent visibles |
| Succès | Bandeau après retour d'une action : « Livraison validée. Commande clôturée. » + prochaine action utile |
| Accès interdit | Compte sans rôle client : « Activez l'espace client dans votre compte. [Ouvrir mon compte] » (C01) ; compte suspendu : motif, recours, accès **encadré** au support (F07) |
| Démonstration | Bandeau « Démonstration » ; références `DEMO-` ; données **exclues** des statistiques |

### 6.4 Contrats métier appelés

| Élément | Contrat | Réfs |
|---|---|---|
| Liste « À faire » | `Orders\Queries\ListClientTasks` (lecture composée : commandes, missions, propositions, reports, non-lus ; **bornée au compte**) | ARB §4 |
| Commandes / missions | `Orders\ListOrders` (filtre rôle client) · `Missions\ListClientMissions` | C04, CL02 |
| Payer | → C06 `Finance\InitiatePayment` (§9) | F24 |
| Examiner / valider / corriger | → C05 `Orders\ValidateDelivery`, `Orders\RequestCorrection` (§8) | F31–F32 |

---

## 7. Tableau de bord freelance (FR01)

| | |
|---|---|
| **Acteur** | Freelance (rôle activé) |
| **Objectif** | Répondre à temps, livrer à temps, comprendre **où en est l'argent** |
| **Informations prioritaires** | **Demandes à accepter (48 h)** · **livraisons attendues** · échéances · état des reversements |
| **Action principale** | Celle de l'élément le plus urgent : **Répondre à la demande**, **Préparer la livraison**, sinon **Créer un service** |
| **Secondaires** | Mes services · Voir les missions ouvertes · Profil public |
| **Effet annoncé** | « *Répondre à la demande* — Si vous acceptez, le client a 24 h pour payer ; le travail ne commence qu'après paiement confirmé et brief complet. » |

**Correction de démonstration** (`01` C09) : IMG-3 affiche le compte « AK » comme **autre partie** de sa propre commande. Le freelance voit toujours **le client** comme autre partie.

### 7.1 Maquette — ordinateur 1440 px

```text
┌ Espace freelance ┬──────────────────────────────────────────────────────────────────────────┐
│ ▎Vue d'ensemble  │ ESPACE FREELANCE   [Client | Freelance]                                   │
│  Mes services    │ H1 Vos prestations                                  [Créer un service]    │
│  Propositions    │ ┌ À faire maintenant (3) ───────────────────────────────┐ ┌ Vos reversements ┐│
│  Commandes       │ │ ▸ Répondre à la demande · Client : A. Ouattara · 35 000 │ │ Attribué    0 FCFA│
│  Reversements    │ │   Répondre avant le 7 oct. 14:30 (dans 22 h)            │ │ Éligible    0 FCFA│
│  Bénéficiaire    │ │   [Répondre à la demande]  Voir la demande              │ │ En cours    0 FCFA│
│  Messages        │ │ ▸ Préparer la livraison · DEMO-26018 · échéance 14 oct. │ │ Confirmé    0 FCFA│
│  Profil public   │ │ ▸ Vérifier votre bénéficiaire (requis avant reversement)│ │ ⚠ Bénéficiaire à │
│  Compte          │ └───────────────────────────────────────────────────────┘ │   vérifier       │
│                  │ ┌ En attente du client (2) ┐ ┌ Missions à découvrir (3) ─┐ │ [Voir le détail]│
│                  │ │ …                        │ │ selon vos compétences …    │ └──────────────────┘│
│                  │ Vos chiffres : Commandes actives 1 ›  Demandes à accepter 0 ›  Propositions 2 ›│
└──────────────────┴──────────────────────────────────────────────────────────────────────────┘
```

### 7.2 Règles de contenu

| Règle | Détail |
|---|---|
| **Types « À faire »** (SRC ARB §5, §10, F20, F29, F31) | *Répondre à la demande* (48 h) · *Préparer la livraison* (échéance `due_at`) · *Déposer la correction* (`revision_requested`) · *Répondre à la demande de report* · *Mettre à jour votre proposition* (validité proche, besoin modifié) · *Corriger votre service* (motif de modération) · *Terminer votre profil* · *Vérifier votre bénéficiaire* · *Créer un service* (si aucun) |
| **Bloc financier** | **Quatre montants distincts** : *Attribué* (validé, non versé) · *Éligible* · *En cours de reversement* · *Confirmé*. **Un montant attribué n'est jamais présenté comme reçu** (SRC ARB §5). Chaque montant a sa date de dernier contrôle ; un état incertain s'affiche « **À rapprocher** » avec accès au support |
| **Commission et frais** | Montant de commission et frais **tels qu'enregistrés dans l'accord** (ex. 100 000 → commission 10 000 → part 90 000, *propositions à confirmer*) |
| **Profil à terminer** | Liste de contrôle F04 : titre · présentation · ville · ≥ 1 compétence ; **« Publier mon profil »** seulement quand complet |
| **Missions à découvrir** | Missions **ouvertes réelles** correspondant aux compétences ; jamais de mission fictive hors démonstration |

### 7.3 États

| État | Comportement |
|---|---|
| Chargement | Squelette ; bloc financier indépendant |
| Vide (nouveau freelance) | **Parcours en 3 étapes** : 1 Terminer le profil · 2 Créer un service **ou** répondre à une mission · 3 Ajouter le bénéficiaire ; barre « 1 étape sur 3 faite » (texte + progression) |
| Erreur partielle | Bloc financier : « Impossible d'afficher vos montants. Ils ne sont pas modifiés. [Réessayer] » |
| Succès | « Demande acceptée. Le client a jusqu'au … pour payer. » |
| Accès interdit | Rôle freelance non activé : « Activez l'espace freelance. [Ouvrir mon compte] » ; compte limité : motif et recours |

### 7.4 Contrats métier appelés

| Élément | Contrat | Réfs |
|---|---|---|
| « À faire » | `Orders\Queries\ListFreelancerTasks` | ARB §5 |
| Accepter / refuser | `Orders\AcceptServiceRequest` · `Orders\DeclineServiceRequest` (motif) | F20 |
| Livrer | → C05 `Orders\DeliverOrder` (§8) | F29 |
| Résumé financier | `Finance\Queries\GetPayoutSummary` (parts **attribuée / éligible / en cours / confirmée**, dernier contrôle) | F26, F28 |
| Bénéficiaire | → FR08 (parcours sécurisé du prestataire, statut et référence masqués) | F08 |
| Publier le profil | `Accounts\PublishFreelancerProfile` | F04 |

---

## 8. Dossier de commande partagé (C05)

C05 est **le dossier commun** aux deux parties et aux deux entrées (service, mission). Son **en-tête** et son **bloc d'action** répondent aux trois questions de l'écran (SRC ARB §9).

| | |
|---|---|
| **Acteur** | Client ; freelance ; support habilité (accès affecté, motif, journalisé) |
| **Objectif** | Suivre **accord, brief, livraisons, échanges, finances, historique, avis** d'une commande |
| **Informations prioritaires** | **Référence · titre · autre partie · état · échéance · montant · action attendue** |
| **Action principale** | Selon rôle et état (§8.3) |
| **Secondaires** | Messages · demander un report · ouvrir un litige · contacter le support · télécharger |
| **Effet annoncé** | Phrase d'effet dans le bloc d'action et **dans chaque confirmation** (§8.4) |

### 8.1 Maquette — ordinateur 1440 px (client, commande « Livrée »)

```text
┌ Espace client ┬──────────────────────────────────────────────────────────────────────────────┐
│               │ Commandes › DEMO-26018                                                       │
│               │ H1 Convertir vos plans PDF en fichiers AutoCAD                               │
│               │ ┌ En-tête ──────────────────────────────────────────────────────────────┐   │
│               │ │ [Livrée] ⓘ  Freelance : Nom · Abidjan   Échéance : 14 oct. 12:00 (dans 6 j)│   │
│               │ │ Montant : 35 000 FCFA     Réf. DEMO-26018                               │   │
│               │ │ Accord ▸ Paiement ▸ Brief ▸ Réalisation ▸ [Livraison] ▸ Validation ▸ Clôture│   │ stepper textuel
│               │ └───────────────────────────────────────────────────────────────────────┘   │
│               │ ┌ ACTION ATTENDUE ─────────────────────────────────────────────────────┐    │
│               │ │ Examiner la livraison v2 — décidez avant le 14 oct.                   │    │
│               │ │ [Valider la livraison]   [Demander une correction (1 sur 2 restante)]  │    │
│               │ │ Ce qui se passera : valider clôture la commande et rend le reversement  │    │
│               │ │ éligible ; vous ne pourrez plus demander de correction.                │    │
│               │ └───────────────────────────────────────────────────────────────────────┘    │
│               │ [Accord][Brief][Livraisons ●][Messages (2)][Finances][Historique][Avis]       │ onglets
│               │ ┌ Contenu de l'onglet (8/12) ─────────────────┐ ┌ Résumé collant (4/12) ─┐   │
│               │ │ Livraison v2 · 12 oct. 09:40                 │ │ État · Échéance        │   │
│               │ │ Message du freelance …                       │ │ Montant · Autre partie │   │
│               │ │ Fichiers : plan-RDC.dwg 4,2 Mo [Vérifié] [⬇] │ │ Prochaine étape        │   │
│               │ │ v1 (8 oct.) · correction demandée : …        │ │ Aide · Contacter le    │   │
│               │ └──────────────────────────────────────────────┘ │ support · Litige ▾     │   │
└───────────────┴──────────────────────────────────────────────────────────────────────────────┘
```

**Téléphone (360/390)** : en-tête **compact** (titre, **état, échéance, montant** sur 3 lignes étiquetées, jamais coupés) · stepper en **liste verticale repliée** sur l'étape courante (« Étape 5 sur 7 : Livraison » + « Voir toutes les étapes ») · **bloc d'action** pleine largeur · onglets en **puces sur 2 rangées** (« Messages (2 non lus) ») · contenu · **barre d'action collante** (uniquement si une action est attendue : bouton primaire + « Autres actions ▾ ») ; marge basse compensée ; barre retirée quand le clavier est ouvert.

### 8.2 Contenu des onglets (SRC ARB §9)

| Onglet | Contenu | Actions |
|---|---|---|
| **Accord** | Parties · version de l'offre/proposition · périmètre · livrables · **prix** · délai · corrections · **versions des conditions** ; le client voit le **prix et les frais affichés**, le freelance voit aussi **commission et part attribuée** (*répartition à confirmer, Q05*) | Lire (figé) ; **demander un report** (action dédiée) |
| **Brief** | Éléments requis (issus de l'accord) en **liste de contrôle** « Fourni / Manquant » ; réponses ; pièces ; message « Brief complet / 2 éléments manquants » | Client : compléter · Freelance : signaler ce qui manque |
| **Livraisons** | Versions **v1, v2…** (plus récente d'abord) : date, message, **fichiers** (nom, taille, **résultat du contrôle**, téléchargement par lien court), corrections liées ; compteur « Corrections : 1 sur 2 » | Freelance : **Livrer** · Client : télécharger, **demander une correction**, **valider** |
| **Messages** | Conversation liée à la commande ; non lus ; pièces ; rappel « *Un fichier envoyé ici n'est pas une livraison formelle* » ; signaler | Envoyer ; **les messages ne changent ni prix, ni commission, ni état financier** (CDC §11) |
| **Finances** | **Trois blocs séparés : Paiement · Remboursement · Reversement**, chacun avec montant, **état**, **référence**, **dernier contrôle**, « ce que cela signifie » ; dernier état incertain → « **À rapprocher** » | Client : aller à C06 si autorisé ; **Contacter le support** |
| **Historique** | Transitions : auteur, date, **état précédent → suivant**, motif ; reports ; décisions | Consulter ; lien vers la livraison/dossier |
| **Avis** | Après clôture validée : note 1–5 + commentaire ; publication après deux dépôts ou 14 j | Déposer ; répondre ; signaler |

### 8.3 Actions selon l'état (SRC ARB §10 — calculées, revérifiées au serveur)

| État (libellé) | Action principale **client** | Action principale **freelance** | Échéance / indicateur affiché |
|---|---|---|---|
| À accepter | *(attendre)* retirer la demande | **Accepter** ou refuser | Réponse avant (48 h proposées) |
| À payer | **Payer** (→ C06) · annuler | *(attendre la confirmation)* | Payer avant (24 h proposées) |
| En attente du brief | **Compléter le brief** | Signaler ce qui manque | « 2 éléments manquants » |
| En cours | Répondre à un report | **Livrer** · proposer un report | Échéance de livraison ; **retard** = indicateur |
| Livrée | **Valider** · demander une correction | *(attendre)* | Examen avant (7 j proposés ; **sans libération automatique**) |
| Correction demandée | Préciser | **Déposer la nouvelle livraison** | Compteur de corrections |
| Validée | Consulter la suite financière | Consulter commission, part, reversement | « Reversement : à vérifier / éligible » (état séparé) |
| Clôturée | Déposer un avis | Déposer un avis | Avis publiés après 2 dépôts ou 14 j |
| En litige | Déposer les preuves ; répondre | Idem | « **Reversement bloqué** » |
| Annulée / expirée | Consulter motif et remboursement | Consulter motif | Aucune livraison supplémentaire |

**Indicateurs** : un **retard** affiche « En retard de N jours » + « Contacter le support » ; il **ne valide rien et ne reverse rien** (F22). Après **7 jours sans réponse** à une livraison : bandeau « Cette livraison attend votre examen depuis 7 jours. Le support a été saisi ; **rien n'est validé automatiquement**. » (F32).

### 8.4 Confirmations (dialogue à trois parties : objet · conséquences · boutons — `03` §6.7)

| Action | Objet | Conséquences affichées | Boutons |
|---|---|---|---|
| **Livrer** | « Déposer la livraison v3 : 2 fichiers » | « Le client est notifié et peut valider ou demander une correction. La commande passe à *Livrée*. » | Déposer la livraison / Revenir |
| **Valider** | « Valider la livraison v2 » | « La commande est clôturée. Le reversement devient éligible **sous réserve de la vérification du bénéficiaire** ; il n'est pas immédiat. Vous ne pouvez plus demander de correction. » | Valider la livraison / Revenir |
| **Demander une correction** | « Correction n° 2 sur 2, liée à la v2 » | « Vous aurez utilisé toutes les corrections incluses. Une demande hors périmètre peut nécessiter une nouvelle commande. » | Envoyer la demande / Revenir |
| **Annuler** (avant paiement) | « Annuler la demande » | « Aucun montant n'a été encaissé. La demande est fermée. » | Annuler la demande / Garder |
| **Annuler** (après paiement) | « Demander l'annulation » | « Un examen contradictoire s'ouvre ; **le reversement est bloqué**. Le support décide du travail retenu et du remboursement éventuel. » | Demander l'annulation / Revenir |
| **Ouvrir un litige** | « Ouvrir un litige sur DEMO-26018 » | « Le reversement non exécuté est bloqué. Ajoutez vos preuves : les deux parties voient motif, étapes et décision. » | Ouvrir le litige / Revenir |
| **Accepter un report** | « Reporter l'échéance au 18 oct. (+4 j) » | « La nouvelle échéance remplace l'ancienne **seulement après votre acceptation**. » | Accepter / Refuser |

### 8.5 États

| État | Comportement |
|---|---|
| Chargement | Squelette de l'en-tête ; onglet chargé à la demande ; double soumission bloquée |
| Vide (par onglet) | Livraisons : « Aucune livraison pour l'instant. Le freelance dépose ici ses fichiers. » · Messages : « Aucun message. Écrivez à [nom]. » · Avis : « Les avis s'ouvrent après la validation. » |
| Erreur | `409` : « La commande a changé pendant que vous la consultiez. [Actualiser] » ; `503` : « Service momentanément indisponible. Rien n'a été modifié. » |
| Succès | Nouvel état + **référence** + action suivante (ex. « Livraison déposée. Le client a jusqu'au 21 oct. pour l'examiner. ») |
| **Accès interdit / inexistant** | « Cette commande est introuvable ou vous n'y avez pas accès. » — **même message**, aucune partie/montant/fichier (SRC CDC §4, N10) |
| Support | Bandeau « Vous consultez ce dossier en tant que support — accès journalisé » ; actions limitées à l'habilitation |
| Compte suspendu | Actions désactivées avec **motif**, support accessible (F07) |

### 8.6 Contrats métier appelés

| Action | Contrat | Réfs |
|---|---|---|
| Lire la commande | `Orders\GetOrderDossier` (**bornée aux parties** ; support affecté avec motif et audit) | F23, N10 |
| Accepter / refuser | `Orders\AcceptServiceRequest`, `Orders\DeclineServiceRequest` | F20 |
| Compléter le brief | `Orders\CompleteBrief` | F21 |
| Livrer | `Orders\DeliverOrder` | F29–F30 |
| Correction / validation | `Orders\RequestCorrection`, `Orders\ValidateDelivery` | F31–F32 |
| Report | `Orders\RequestExtension`, `Orders\AnswerExtension` | F22 |
| Annulation / litige | `Orders\CancelBeforePayment`, `Orders\RequestCancellation`, `Orders\OpenDispute` | F33–F34 |
| Messages | `Communication\SendMessage` | F37 |
| Téléchargement | `Files\IssueDownload` (lien court, fichier `clean`) | F30, N13 |
| Avis | `Orders\SubmitReview` | F36 |

---

## 9. Paiement et résultat « en attente de vérification » (C06)

| | |
|---|---|
| **Acteur** | Client de la commande |
| **Objectif** | **Payer l'accord** puis connaître **le résultat vérifié côté serveur** (SRC F24–F25) |
| **Informations prioritaires** | Montant à payer · échéance de paiement · ce qui est acheté · moyens **réellement activés** |
| **Action principale** | **Payer 35 000 FCFA** |
| **Secondaires** | Annuler la demande (si encore ouverte) · Voir l'accord · Contacter le support |
| **Effet annoncé** | « Vous serez redirigé(e) vers le prestataire de paiement. **Le travail commence quand le paiement est confirmé et que le brief est complet.** Le retour de votre navigateur seul ne confirme rien. » |

**Mode démonstration** : bandeau fort « **Paiement simulé — aucun argent n'est débité** » ; moyens listés = « Simulation » ; références `DEMO-`. **Aucun moyen réel** (Wave, Orange Money, MTN MoMo, Moov Money, carte) n'est affiché tant que le contrat ne l'active pas (F24, Q03).

### 9.1 Maquette — avant paiement (ordinateur 1440 px)

```text
┌ Espace client ┬──────────────────────────────────────────────────────────────────────────┐
│               │ Commandes › DEMO-26018 › Paiement                                         │
│               │ H1 Payer votre commande                                                   │
│               │ ┌ Bandeau : Paiement simulé — aucun argent n'est débité ───────────────┐  │ (démo)
│               │ ┌ Moyen de paiement (7/12) ──────────────┐ ┌ Récapitulatif (5/12) ───────┐│
│               │ │ ◉ Simulation                            │ │ Convertir vos plans… (v1)   ││
│               │ │ ○ (moyens activés par contrat — aucun)  │ │ Freelance : Nom             ││
│               │ │ ─────────────────────────────────────── │ │ Délai : 5 jours (dès le     ││
│               │ │ Échéance : payer avant le 7 oct. 14:30   │ │ paiement + brief complet)   ││
│               │ │ (dans 22 h)                              │ │ Corrections : 2             ││
│               │ │ ☐ J'ai lu les conditions de commande v1.2│ │ Prix convenu   35 000 FCFA  ││
│               │ │ Vous serez redirigé(e) vers le prestataire│ │ Frais affichés       0 FCFA ││
│               │ │ …Le travail commence après paiement     │ │ TOTAL À PAYER  35 000 FCFA  ││ 28/32
│               │ │ confirmé et brief complet.               │ │ [ Payer 35 000 FCFA ]       ││
│               │ └──────────────────────────────────────────┘ │ Annuler la demande · Aide  ││
└───────────────┴──────────────────────────────────────────────────────────────────────────┘
```

**Téléphone** : récapitulatif **d'abord** (carte avec **total à payer** 28/32), puis moyen de paiement (cartes radio ≥ 56 px), échéance, conditions, bouton **Payer** pleine largeur 48 px en fin de page ; **barre collante** « Total 35 000 FCFA · [Payer] » acceptable (aucun champ de saisie sensible sur cette page : les cartes, codes secrets et codes de validation sont saisis **chez le prestataire**, SRC N17), avec marge basse compensée.

### 9.2 Page de résultat — un seul écran, piloté par l'état **lu en base**

La page de retour **ne tire aucune conclusion des paramètres d'URL** : elle lit l'état de l'opération et peut demander une vérification (`Finance\RefreshPaymentStatus`). Le bouton **Payer** est **absent** tant qu'un paiement est `pending` ou `unknown` (SRC ARC §9 ; anti-paiement aveugle).

| État lu | Titre et ton | Contenu | Actions |
|---|---|---|---|
| **Confirmé** (serveur) | *Paiement confirmé* — succès | Montant, référence, **date de confirmation**. Si brief complet : « Le travail commence. Livraison prévue le … » ; sinon : « **Complétez le brief** pour lancer le travail (2 éléments manquants). » | **[Compléter le brief]** ou **[Voir ma commande]** |
| **Vérification en cours** (`pending`/`unknown`) | *Vérification du paiement en cours* — avertissement | « Nous vérifions votre paiement auprès du prestataire. **Ne payez pas une seconde fois.** Dernier contrôle : 14:12. Votre commande reste réservée tant qu'une vérification est en cours. » ; actualisation automatique espacée (retrait progressif), puis « Cela prend plus de temps que prévu. » | **[Actualiser le statut]** (limité) · [Voir ma commande] · **[Contacter le support]** (avec la référence) |
| **Échoué** | *Paiement non abouti* — erreur | « Aucun montant n'a été confirmé. » Raison **générique** ; référence | **[Réessayer le paiement]** (si le délai n'est pas échu et aucun paiement en vérification) · [Contacter le support] |
| **Abandonné** (retour sans paiement) | *Paiement non effectué* — information | « Vous n'avez pas finalisé le paiement. Il reste jusqu'au … » | [Payer] · [Annuler la demande] |
| **Expiré** | *Délai de paiement dépassé* — neutre | « La commande a expiré. » ; commande mission : « [Rouvrir la mission] pour choisir une proposition encore valide. » | selon origine |
| **Reçu après expiration / doublon** | *Paiement en cours d'examen* — avertissement | « Un paiement a été reçu après l'expiration (ou en double). **Le support l'examine ; aucune seconde commande n'est créée.** » + référence | [Contacter le support] |

**États génériques** : chargement (squelette + « Vérification… ») · erreur système (« Impossible de lire l'état du paiement. Votre paiement n'est pas annulé. Code : … ») · accès interdit (message d'accès, aucune donnée).

### 9.3 Contrats métier appelés

| Élément | Contrat | Réfs |
|---|---|---|
| Afficher le récapitulatif | `Finance\GetPaymentSummary` (**montant lu dans l'accord**, jamais dans la requête) | F19, F25 |
| **Payer** | `Finance\InitiatePayment` — méthode **activée**, `operationKey` ; refuse si paiement `pending`/`unknown` ; appel au prestataire hors transaction longue | F24, F25 |
| Résultat | `Finance\GetPaymentStatus` (lecture) · `Finance\RefreshPaymentStatus` (vérification serveur → prestataire, limitée) | F25 |
| Confirmation (hors écran) | `Finance\ConfirmPayment` — notification **authentifiée et dédoublonnée**, vérification de référence, montant, devise | F25, T07–T08 |
| Retard / doublon | `Finance\RecordLatePayment` → `reconciliation_case` | F27, T09 |
| Annuler (avant paiement) | `Orders\CancelBeforePayment` | F33 |

---

## 10. File administrative de publications et de dossiers à traiter (AD01, AD02)

| | |
|---|---|
| **Acteurs** | **Modérateur** (publications, signalements) · **Support** (dossiers confiés) · **Habilitation financière** (transactions, rapprochement) · **Administrateur** (paramètres, droits). Chacun ne voit **que** ses files |
| **Objectif** | **Traiter** : décider vite, avec le contenu exact et le motif, sans perdre la traçabilité (SRC F39–F42, AD01–AD10) |
| **Informations prioritaires** | Ce qui attend, **depuis quand**, et la **plus ancienne** demande ; puis le détail |
| **Action principale** | **Publier** (AD02) ou **Ouvrir la file la plus ancienne** (AD01) |
| **Secondaires** | Demander une correction · Suspendre · Archiver · Affecter · Passer au suivant |
| **Effet annoncé** | « *Publier* rend le contenu visible dans la recherche. *Demander une correction* le renvoie à son auteur avec votre motif. » |

**Coque distincte** (`01` C10, `03` §6.1) : bandeau `marine-900` « Administration · [rôle d'habilitation] », **pas** d'en-tête public ni de sélecteur d'espace (celui de IMG-4 n'existe qu'en démonstration, avec libellé). Authentification **renforcée** (MFA, N11) ; **gestion des droits** réservée à une habilitation distincte.

### 10.1 AD01 — Files de travail

```text
┌ Administration · Modération ─────────────────────────────────────────────── [Compte ▾] ┐
├ Files ──────────┬────────────────────────────────────────────────────────────────────┤
│ ▎Files de travail│ H1 À traiter                                  Mise à jour 14:12 ↻   │
│  Publications (5)│ ┌ Publications en attente   5 │ plus ancienne : il y a 19 h ┐ [Ouvrir]│
│  Signalements (2)│ ┌ Signalements ouverts       2 │ plus ancien : il y a 3 h    ┐ [Ouvrir]│
│  Litiges (1)     │ ┌ Litiges ouverts            1 │ plus ancien : il y a 2 j    ┐ [Ouvrir]│ (si habilité)
│  Support (4)     │ ┌ Livraisons sans réponse > 7 j  3 ┐ ┌ Commandes en retard  6 ┐         │
│  Transactions    │ ┌ Paiements/reversements à rapprocher 2 (habilitation financière) ┐ [Ouvrir]│
│  Utilisateurs    │ ─── Indicateurs (période et formule affichées) ───────────────────────  │
│  Paramètres      │  Services publiés · Missions avec ≥ 1 proposition · Commandes financées │
│  Journal         │  Livraisons validées · Litiges  — « Volume ≠ chiffre d'affaires » (CDC §1) │
└──────────────────┴────────────────────────────────────────────────────────────────────┘
```

Les files **sans habilitation** n'apparaissent pas. Les indicateurs distinguent **volume de commandes, commissions, frais, remboursements, montants attribués** (F42) et affichent **période et formule**.

### 10.2 AD02 — Publications (liste + détail)

```text
┌ Liste (380 px) ──────────────────┬ Détail ──────────────────────────────────────────────┐
│ Filtres : Type ▾ Statut ▾ Ancienneté ▾ │ Service · Soumis il y a 19 h · Version 3 (modification importante) │
│ ▎Service · Convertir vos plans… │ ┌ Aperçu exact tel que publié ───────────────────────┐ │
│    Nouveau · il y a 19 h        │ │ (rendu de P03, médias, prix, périmètre)            │ │
│  Mission · Charpente métal…     │ └────────────────────────────────────────────────────┘ │
│    Modifié · il y a 2 h         │ Contrôles automatiques : champs requis ✓ · médias contrôlés ✓ │
│  Service · …  ⚑ 1 signalement   │   · coordonnées privées détectées ✗ (1) · version publique : v2 [Comparer]│
│ …                               │ Historique : v1 publiée 3 oct. · v2 refusée (motif) … │
│                                 │ Motif (obligatoire pour correction/suspension/archivage)│
│                                 │ [____________________________________________]        │
│                                 │ [ Publier ] [Demander une correction] [Suspendre] [Archiver]│
│                                 │ Effet : « Publier » rend le contenu visible …  · Passer au suivant →│
└─────────────────────────────────┴──────────────────────────────────────────────────────┘
```

**Règles** (SRC F10, F39, ARB §6) : décision **avec motif** (obligatoire sauf publication) ; une modification importante d'un service publié **repasse en contrôle** sans toucher les commandes existantes ; les contenus à risque sont retirés de la recherche **sans supprimer les dossiers commerciaux** ; **modérateur ≠ pouvoir financier** (T16).

**Téléphone/tablette** : le détail s'ouvre en **écran séparé** (liste → détail), les mêmes actions sont toutes disponibles (aucune fonction masquée) ; conception **prioritairement ≥ 768 px** (*Q15 : usage mobile de l'administration à confirmer*).

### 10.3 Dossiers à traiter (AD04–AD08 — même gabarit « file + dossier »)

| Composant commun | Règle |
|---|---|
| Liste | Filtres (état, ancienneté, responsable, priorité), tri par ancienneté, **affectation** (« M'affecter », « Affecter à… »), compteur par file |
| Dossier | Parties, **accord** et versions, preuves, échanges, **décisions précédentes** ; accès à des données privées **uniquement après « Ouvrir le dossier » + motif**, **journalisé** (bandeau « Accès journalisé ») |
| Décision | Motivée, notifiée aux deux parties, journalisée ; **exécution financière suivie séparément** (AD06) |
| Opérations financières (remboursement, reversement manuel, changement de bénéficiaire, droits) | **Authentification renforcée + motif** ; au-delà du **seuil configurable** (à fixer, Q05) → **second approbateur distinct** sur **l'action exacte** (empreinte) ; aucune modification directe des écritures |
| Résultat financier inconnu | **Rapprochement d'abord** (`ReconcileUnknown`), **aucune** nouvelle tentative avant résolution |
| Exports | Mêmes filtres que l'écran ; **champs autorisés seulement** ; cellules protégées contre l'interprétation comme formules ; **journalisés** (CDC §12) |

### 10.4 États

| État | Comportement |
|---|---|
| Chargement | Squelette des lignes ; compteurs asynchrones |
| Vide | « **Aucune publication en attente.** Dernière vérification : 14:12. » (pas de texte de démonstration en production ; en démonstration : « Soumettez une mission depuis l'espace client pour voir le contrôle de publication », comme IMG-4) |
| Erreur | « Impossible de charger la file. [Réessayer] » ; `409` si un autre agent a déjà décidé : « Ce dossier a été traité par … à … » |
| Succès | « Service publié. » + **dossier suivant** proposé |
| Accès interdit | « Votre habilitation ne permet pas cette action. » (tentative **journalisée**) |

### 10.5 Contrats métier appelés

| Élément | Contrat | Réfs |
|---|---|---|
| Files et compteurs | `Administration\Queries\ListWorkQueues` (selon habilitation) | F42 |
| Liste de publications | `Administration\ListPendingPublications` | F39 |
| Publier / corriger / suspendre / archiver (service) | `Catalog\PublishService`, `Catalog\RequestServiceFix`, `Catalog\SuspendService`, `Catalog\ArchiveService` (motif) | F10, F39 |
| Publier / corriger (mission) | `Missions\PublishMission`, `Missions\RequestMissionFix` | F14, F39 |
| Affecter | `Administration\AssignCase` | F41 |
| Litige | `Administration\ResolveDispute` (décision, part retenue, remboursement) | F35 |
| Rapprochement | `Finance\ReconcileUnknown` | F25–F27 |
| Opérations manuelles | `Finance\InitiateRefund`, `Finance\InitiatePayout`, `Administration\ApproveSensitiveAction` (approbateur distinct) | F26–F27, CDC §12 |
| Journal | `Administration\RecordAudit` (automatique) · lecture `Administration\Queries\ListAuditEvents` (habilitation distincte) | N14 |

---

## 11. Vérification croisée des deux parcours (SRC ARB §11–§12)

| Étape | Parcours **service** | Parcours **mission** | Écran commun |
|---|---|---|---|
| Découvrir / besoin | P01 → P02 → P03 | CL03 → AD02 → P04/P05 | — |
| Accord | Brief → **acceptation du freelance** (C05 « À accepter ») | FR06 → **sélection** (CL04) | Accord figé (C05 onglet Accord) |
| Paiement | C06 | C06 | **C06** |
| Démarrage | Paiement confirmé **+ brief complet** | idem | **C05** |
| Réalisation | Livraison v1…vn, corrections, validation | idem | **C05** |
| Finance | Paiement · remboursement · reversement **séparés** | idem | C05 « Finances », CL05, FR07 |

**Branches à prévoir dans les maquettes** (SRC ARB §11–§12) : demande refusée ou expirée avant paiement · brief incomplet après paiement · paiement inconnu, doublon ou tardif · livraison contestée · choix devenu indisponible · commande expirée et mission rouverte.

### 11.1 Ordre de production des maquettes (reprend ARB §15, adapté)

1. **Lot 1** : P01, P02, P03 (§2–§3). 2. **Lot 2** : CL03, FR06, CL04 (§4–§5). 3. **Lot 3** : C05, C06 dans leurs états (§8–§9), mobile et ordinateur. 4. **Lot 4** : CL01, FR01 (§6–§7). 5. **Lot 5** : AD01, AD02 et le gabarit file + dossier (§10). 6. **Lot 6** : comptes (A01–A05, C01), puis écrans « I » de l'inventaire avec les mêmes composants.

Chaque maquette sera livrée avec : contenu réel de démonstration **étiqueté**, états vide/chargement/erreur/succès/accès interdit, versions **360 et 1440 px** au minimum (390/768/1024 pour les écrans à comportement spécifique : comparaison, commande, paiement), et la liste des **contrats métier** appelés.

**Revue visuelle obligatoire (D18).** Chaque lot est **présenté en maquettes haute fidélité puis examiné par le porteur** avant son développement. L'examen porte sur : identité FreeCI reconnaissable (`03` §2.4), proportions (`03` §2.5), lisibilité, clarté des trois questions (`04` §0.1), parcours sur téléphone **et** ordinateur. Aucune règle métier n'est portée par la maquette ni par l'interface : **le serveur reste l'autorité** (`02` P3).
