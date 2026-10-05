# 03 — Direction visuelle de FreeCI

> **Statut : proposition de conception, non validée (identité à valider par le porteur : CDC N04, ARB §15).** Diffusion : **LOCAL**.
> Les ratios de contraste ci-dessous sont **calculés** (formule WCAG 2.x) sur des valeurs **mesurées dans les captures** (marquées *mesuré*) ou **proposées** (*prop.*). Rien n'est « testé » sur une application : aucune n'existe.

Étiquettes : **SRC** · **DEC** · **PROP** · **Q** (voir `01` §0).

---

## 1. Ce que disent les captures (IMG-1 à IMG-4)

Valeurs échantillonnées sur les pixels des captures ; elles décrivent une **démonstration**, pas une charte.

| Observé | Valeur *mesurée* | Verdict |
|---|---|---|
| Fond du bandeau d'accueil | `#102D4B` | **Conserver** (DEC D09 : bleu marine). |
| Logo « f. » et boutons primaires | `#183B60` | **Conserver** comme couleur d'action. |
| Fond de page / cartes / pastille | `#F7F8FB` / `#FFFFFF` / `#EEF2F7` | **Conserver** (fonds clairs, cartes sobres). |
| Texte secondaire | `#596777` — contraste **5,45:1** sur `#F7F8FB`, **5,14:1** sur `#EEF2F7` | Conforme AA mais **juste** pour du texte de 12–14 px (étiquettes de compteurs, métadonnées, « Exemples »). **Renforcer** (→ `#4B5968`, 6,75:1 / 6,38:1). |
| Surtitre orange sur fond marine | `#FFAD82` — 7,71:1 | Lisible. Conserver comme accent. |
| Surtitre « COMPTE CLIENT FICTIF » et badge « Livrée » | `#B9491B` — 4,90:1 sur `#F7F8FB` ; 4,71:1 sur `#FFF1E8` | Passe AA **de justesse**. **Assombrir** (→ `#A63F14`, 6,28:1 / 5,68:1). |
| Badge « Ouverte » | `#276A49` sur `#EAF6EF` — 5,83:1 | Conforme. |
| Paragraphe du bandeau | `#CCD9E8` sur `#102D4B` — 9,78:1 | Conforme. |

**Ce qui fonctionne** : séparation nette en-tête / navigation / contenu ; cartes à bordure fine sans ombre lourde ; hiérarchie H1 large + chapô ; champ de recherche intégré au bandeau ; état vide (IMG-4) avec message et consigne ; marquage de démonstration (« FICTIF », « DEMO- »).

**Ce qu'il faut améliorer** (liés à INS §4) :

| Constat | Amélioration proposée |
|---|---|
| L'orange est à la fois **accent décoratif** (surtitres) et **teinte d'un statut** (« Livrée »). | Orange = accent de marque **uniquement** ; statuts en couleurs sémantiques avec icône et libellé (§3.3). |
| Le badge « Livrée » ne dit pas **quoi faire**. | Marqueur d'action distinct : « Examiner la livraison » (§6, `04` §6). |
| Les 3 compteurs précèdent les actions. | Actions et échéances d'abord (`04` §6–§7). |
| Les 4 entrées de navigation sont réparties sur toute la largeur (espaces très variables) et se désolidarisent du logo. | Navigation groupée à gauche du logo ou centrée, espacement constant (§6.1). |
| Le logo « f. » est un monogramme minuscule sans signe distinctif ; le nom affiché est « Freelance CI ». | Identité **FreeCI** (§2). |
| La puce « Exemples » est petite et grise sur gris (5,14:1). | Marquage de démonstration **lisible** et non décoratif (§6.8). |
| Les noms (Aminata Koné…) ressemblent à des personnes réelles ; l'avatar à initiales rappelle un profil. | Exemples manifestement fictifs ou **prestations sans nom** (`01` §6). |
| L'administration réutilise l'en-tête public. | Coque d'administration distincte (`01` C10, `04` §9). |
| Un défilement fléché circulaire flotte au centre des captures. | Artefact de capture présumé ; **ne pas reproduire** un bouton flottant sans fonction. |

---

## 2. Identité FreeCI (PROP — à valider)

### 2.1 Traduction des adjectifs en choix vérifiables

| Adjectif demandé | Choix visible | Vérification |
|---|---|---|
| **Soigné** | Jetons uniques (couleur, espacement, rayon, ombre) ; une seule famille typographique ; un seul jeu d'icônes ; alignement sur grille de 4 px | Revue de la **liste de contrôle de cohérence** (§8) sur chaque écran |
| **Crédible** | Prix, délai, périmètre, vendeur et preuves **réels ou marqués démonstration** ; aucun badge non justifié | Revue de contenu (`05` §4) |
| **Intuitif** | Trois réponses sur chaque écran : *où suis-je / que faire maintenant / que se passera-t-il ensuite* ; une action principale par contexte | Test de l'écran en 5 secondes (`05` §4) |
| **Premium (sobre)** | Fond clair, marine profond, **un seul accent** ; peu d'ombres ; aucune animation décorative ; espaces généreux (≥ 24 px entre blocs) | Contrôle des jetons ; revue du nombre d'effets |
| **Responsive** | Conçu à 360 px d'abord ; mêmes informations décisionnelles à toutes les largeurs | Matrice 5 largeurs (`04` §0.2) |

### 2.2 Nom, marque et ton

- **Nom** : **FreeCI** (DEC D01), écrit « FreeCI » (C et I majuscules) partout, y compris dans les courriels et métadonnées.
- **Signature** : *« Des compétences en Côte d'Ivoire »* (reprend le surtitre de IMG-1) — **PROP**, à valider ; ne promet ni volume ni qualité.
- **Ton** : direct, concret, au « vous », verbes d'action (« Publier une mission », « Examiner la livraison »). Pas de superlatifs, pas de promesse de remboursement ou de délai non validés.

### 2.3 Marque graphique

| Élément | Description |
|---|---|
| **Monogramme** | Carré aux angles arrondis (rayon 25 % du côté), fond `marine-700`, lettre **F** blanche en graisse 700, et **un point orange** (`accent-300`) en bas à droite de la lettre — reprise du point de « f. » existant. Taille minimale 24 px ; favicon 32 px. |
| **Logotype** | « **Free**CI » sur une ligne, graisse 700 : « Free » en `ink-900`, « CI » en `accent-700` (6,28:1 sur blanc). Sur fond marine : « Free » blanc, « CI » `accent-300`. |
| **Zone de protection** | Au moins la hauteur du point orange ×2 autour de l'ensemble. |
| **Mobile (360 px)** | Monogramme + logotype ≈ 128 px de large ; le logotype ne passe jamais à la ligne. |
| **Interdits** | Dégradé, ombre portée, rotation, orange en fond plein derrière du texte blanc. |

**Q** : le dessin définitif (forme du monogramme, choix de couleur de « CI ») reste à valider par le porteur ; le présent texte est une spécification, pas un fichier de logo.

---

## 3. Palette (PROP) — 19 valeurs, rôles explicites

Toutes les valeurs sont des **jetons** ; aucune couleur « libre » dans les écrans. Ratios calculés.

### 3.1 Marque, fonds, bordures, texte

| Rôle | Jeton | Valeur | Usage | Contraste |
|---|---|---|---|---|
| Marque — profond | `marine-900` | `#102D4B` | Bandeau d'accueil, en-tête sombre, texte de marque | blanc : **14,01:1** |
| **Action** primaire | `marine-700` | `#183B60` | Boutons primaires, liens d'action, éléments actifs | blanc sur lui : **11,45:1** |
| Action — survol/pression | `marine-800` | `#122F4E` | Survol et pression des boutons primaires | blanc : **13,62:1** |
| Fond teinté | `marine-100` | `#E6EDF5` | Élément de navigation actif, ligne sélectionnée | — |
| Fond de page | `canvas` | `#F7F8FB` *mesuré* | Arrière-plan des pages | — |
| Surface | `surface` | `#FFFFFF` | Cartes, champs, panneaux | — |
| Surface secondaire | `surface-2` | `#EEF2F7` *mesuré* | Puces, zones de regroupement, pied de tableau | — |
| Bordure | `line` | `#D9E0E9` | Séparations décoratives (cartes, listes) | décorative |
| Bordure de composant | `line-strong` | `#7A8799` | Contour des champs, cases, boutons secondaires | **3,65:1** sur blanc ; **3,44:1** sur `canvas` (≥ 3:1, WCAG 1.4.11) |
| Texte principal | `ink-900` | `#0F1B2D` | Titres, corps | **16,28:1** sur `canvas` |
| Texte secondaire | `ink-600` | `#4B5968` | Métadonnées, aides, étiquettes | **6,75:1** sur `canvas` ; **6,38:1** sur `surface-2` |
| Texte sur marine (secondaire) | `on-marine-2` | `#D3DFEC` | Chapôs sur fond marine | **10,36:1** sur `marine-900` |

Pas de troisième gris de texte : les contenus **désactivés** (non interactifs) utilisent `ink-600` à 60 % d'opacité et sont **exemptés** de contraste (WCAG), mais jamais utilisés pour une information essentielle.

### 3.2 Accent

| Rôle | Jeton | Valeur | Usage | Contraste |
|---|---|---|---|---|
| Accent — texte sur clair | `accent-700` | `#A63F14` | Surtitres, « CI » du logotype, une mise en relief par page | **6,28:1** sur blanc ; 5,68:1 sur `#FFF1E8` |
| Accent — sur marine | `accent-300` | `#FFB48A` | Surtitres et point du logo sur fond marine | **8,10:1** sur `marine-900` |
| Accent — fond léger | `accent-100` | `#FFF1E8` | Fond d'une mise en relief non statutaire (ex. « Nouveau ») | — |

**Règles d'usage de l'orange** (DEC INS §4) :
- **Permis** : surtitres, point du logo, « CI » du logotype, une mise en relief par écran.
- **Interdit** : statut de commande/paiement/mission, message d'erreur ou d'avertissement, champ obligatoire, bouton, lien.
- Un écran n'affiche **jamais** plus de **deux** occurrences d'accent hors logo.

### 3.3 Sémantique (information, succès, avertissement, erreur)

| Rôle | Texte/icône | Fond | Contraste | Sens |
|---|---|---|---|---|
| Information | `info-700` `#1F5FA6` | `info-50` `#E8F1FB` | **5,67:1** | Processus en cours, état neutre utile ; **aussi** couleur des liens (6,47:1 sur blanc) |
| Succès | `success-700` `#1E6B45` | `success-50` `#E8F5EE` | **5,77:1** | Terminé, confirmé par le serveur |
| Avertissement | `warning-700` `#7A4B00` | `warning-50` `#FFF4D6` | **6,76:1** | Attente avec échéance, vérification en cours, action requise |
| Erreur | `error-700` `#B3261E` | `error-50` `#FDECEA` | **5,72:1** (6,54:1 sur blanc) | Échec, blocage, litige, refus |
| Focus | `focus` `#1D6FD1` | — | **4,95:1** sur blanc ; **4,66:1** sur `canvas` (≥ 3:1) | Anneau de focus ; sur fond marine : **blanc** (14,01:1) |

L'avertissement est un **brun-jaune** (teinte ≈ 40°), nettement distinct de l'orange d'accent (teinte ≈ 20°) ; il porte toujours une **icône** triangle et un **libellé**.

### 3.4 Statuts : jamais par la couleur seule (SRC N03)

Un **badge d'état** = **icône + libellé + couleur** ; la forme de l'icône change avec le ton.

| Ton | Icône | Exemples de libellés |
|---|---|---|
| Neutre | tiret dans un cercle | Brouillon · Clôturée · Annulée · Expirée · Archivé · Non éligible |
| Information | « i » dans un cercle | En cours · Livrée · Correction demandée · Éligible · En cours de reversement · Attribuée |
| Succès | coche dans un cercle | Validée · Paiement confirmé · Reversement confirmé · Publié · Ouverte |
| Avertissement | triangle « ! » | À accepter · À payer · En attente du brief · Vérification du paiement en cours · À corriger · En contrôle · À rapprocher |
| Erreur | octogone « ! » | En litige · Paiement échoué · Reversement échoué · Suspendu |

Un **marqueur d'action** est un élément **distinct** du badge d'état : étiquette pleine `marine-700` + texte blanc + flèche, intitulée par la tâche (« **Examiner la livraison** », « Payer avant le 7 oct. 14:30 »). Il n'apparaît que si **le spectateur** doit agir. Ainsi, une commande « Livrée » montre *Livrée* (badge d'état, ton information) à tous, et *Examiner la livraison* (marqueur d'action) **seulement** au client.

---

## 4. Typographie (PROP)

**Famille unique** : une police sans empattement à chasse variable, en **auto-hébergement** (pas de requête tierce), sous-ensembles latin et latin étendu (accents français), repli `system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`. Choix suggéré : *Inter* (licence libre SIL OFL — **licence à vérifier avant adoption**). Raisons : lisibilité des diacritiques français, chiffres tabulaires pour les montants (`font-variant-numeric: tabular-nums`), une seule ressource à charger (**budget cible ≤ 100 Ko de police**, à mesurer — N05).

| Style | Mobile (360–767) | Tablette (768–1023) | Ordinateur (≥ 1024) | Graisse | Usage |
|---|---|---|---|---|---|
| Display | 32/38 | 40/46 | 48/54 | 700 | Titre du bandeau d'accueil seulement |
| H1 | 28/34 | 30/36 | 32/38 | 700 | Titre de page (un par page) |
| H2 | 22/28 | 24/30 | 24/30 | 600 | Section |
| H3 | 18/24 | 18/24 | 20/26 | 600 | Carte, bloc |
| Corps | 16/24 | 16/24 | 16/24 | 400 | Texte courant (jamais < 16 px sur champs de saisie : évite le zoom automatique iOS) |
| Corps petit | 14/20 | 14/20 | 14/20 | 400 | Métadonnées, aides |
| Montant | 20/28 | 20/28 | 24/30 | 600 | Prix en carte ; **récapitulatif 28/32** ; chiffres tabulaires |
| Surtitre | 12/16 | 12/16 | 12/16 | 600 | MAJUSCULES, espacement +0,06 em, **décoratif seulement** (jamais porteur d'une information essentielle) |

Règles : aucune information essentielle sous **14 px** ; longueur de ligne **≤ 68 caractères** pour les textes longs ; majuscules réservées au surtitre ; pas de texte justifié ; pas d'italique pour de longs passages.

**Montants et dates** (SRC N01) : `100 000 FCFA` avec **espace insécable** entre milliers et avant « FCFA » (jamais de coupure de ligne dans un montant) ; dates « 7 oct. 2026, 14:30 (heure d'Abidjan) » ; échéances distinguées des dates de contrôle (« Dernier contrôle : 7 oct. 14:12 »).

---

## 5. Espacements, rayons, ombres, mouvement

### 5.1 Échelle d'espacement (grille de 4 px)

`4 · 8 · 12 · 16 · 24 · 32 · 48 · 64 · 96`

| Contexte | Mobile | Tablette | Ordinateur |
|---|---|---|---|
| Marge latérale de page | **16** | **24** | **32** |
| Largeur utile maximale | 100 % | 100 % | 1200 (public) · 1280 (espace privé avec menu latéral) |
| Rembourrage de carte | 16 | 20 | 24 |
| Espace entre cartes | 12 | 16 | 16–24 |
| Espace entre sections | 32 | 40 | 48–64 |
| Hauteur d'en-tête | 56 | 64 | 64 |

### 5.2 Cibles tactiles (DEC D10)

- Toute cible interactive **≥ 44 × 44 px** (boutons, icônes, cases, liens isolés), **≥ 8 px** d'écart entre cibles voisines.
- Boutons : hauteur **44** (par défaut) · **48** (action principale mobile) · **36** autorisé **seulement** en tableau dense sur ordinateur, avec zone cliquable étendue à 44.
- Champs de saisie : hauteur **48**, texte 16 px.
- Liens dans un texte courant : exemptés de la taille, mais **soulignés**.

### 5.3 Rayons

| Jeton | Valeur | Usage |
|---|---|---|
| `r-sm` | 8 px | Champs, boutons, badges rectangulaires |
| `r-md` | 12 px | Cartes, notices |
| `r-lg` | 16 px | Panneaux, dialogues, carte du bandeau |
| `r-full` | 9999 px | Avatars, pastilles de compteur |

### 5.4 Ombres (deux niveaux seulement)

| Jeton | Valeur | Usage |
|---|---|---|
| `elev-1` | `0 1px 2px rgb(16 45 75 / 0.06)` | Carte au survol/au focus-within, barre collante |
| `elev-2` | `0 8px 24px rgb(16 45 75 / 0.12)` | Menus déroulants, tiroir, dialogue |

Par défaut les cartes n'ont **pas d'ombre** (bordure `line` seulement). **Aucun dégradé**, aucun effet de verre, aucune ombre colorée.

### 5.5 Focus et mouvement

- **Focus visible** : anneau 2 px `focus` + 2 px de décalage blanc ; sur fond marine : anneau **blanc**. Jamais `outline: none` sans remplacement.
- **Durées** : 120 ms (survol, focus), 200 ms (tiroir, panneau, accordéon). Courbe standard unique.
- **Interdits** : défilements automatiques, carrousels automatiques, parallaxe, rebonds, animations bloquant l'action suivante, mouvement porteur d'information.
- **`prefers-reduced-motion: reduce`** : transitions réduites à un fondu instantané ; indicateurs de chargement **statiques** (pas de pulsation) ; défilement doux désactivé.

---

## 6. Composants (spécifications)

### 6.1 Navigation

| Zone | Comportement |
|---|---|
| En-tête public | Logo à gauche ; liens **Services · Missions · Freelances · Comment ça marche** groupés (≥ 1024 px) ; à droite : **Publier une mission** (secondaire), messages et compte/Connexion. Entre 768 et 1023 px : liens en deuxième ligne défilante **interdite** → ils passent dans le tiroir. |
| En-tête mobile | 56 px : monogramme + logotype, icône messages (si connecté, avec compteur textuel), bouton **Menu** (libellé visible « Menu » + icône). Le tiroir (plein écran < 768 px) contient : sélecteur d'espace (si deux rôles), navigation, « Publier une mission », compte. |
| Menu d'espace privé | ≥ 1024 px : colonne latérale 248 px, repère « Où suis-je ? » (élément actif sur `marine-100` + barre de 3 px `marine-700` + `aria-current="page"`). < 1024 px : même contenu dans le tiroir ; **pas de barre d'onglets fixe** en bas en V1 (évite de masquer du contenu, DEC D10). |
| Sélecteur d'espace | Contrôle segmenté à deux valeurs « Client | Freelance », **libellé complet**, hors administration ; indique en texte « Vous agissez en tant que client ». N'existe que pour un compte à deux rôles. |
| Administration | **Coque distincte** : bandeau supérieur `marine-900` avec mention « Administration » et nom du rôle d'habilitation ; aucun lien vers l'espace public principal, aucun sélecteur d'espace ; menu latéral limité aux entrées autorisées. |

### 6.2 Boutons

| Variante | Apparence | Usage | Règle |
|---|---|---|---|
| Primaire | Fond `marine-700`, texte blanc, 600 | **Une seule par vue** (ex. « Payer 35 000 FCFA ») | Libellé = verbe + objet ; montant ou effet quand pertinent |
| Secondaire | Fond blanc, contour `line-strong`, texte `marine-700` | Actions alternatives | — |
| Tertiaire | Texte `info-700` souligné au survol/focus | Liens d'action (« Voir toutes les commandes ») | Flèche → pour navigation |
| Destructive | Fond `error-700`, texte blanc | Annuler, retirer, ouvrir un litige | Toujours précédée d'une **confirmation** explicite (§6.7) |
| États | Survol `marine-800` ; pression idem + 1 px ; désactivé : **texte « Pourquoi ? »** adjacent, jamais un bouton grisé muet ; chargement : libellé conservé + « … » + indicateur, clics bloqués (anti double-soumission) | | |

### 6.3 Champs de formulaire

- **Étiquette au-dessus** (jamais en remplacement par un *placeholder*), 14 px 600 ; mention **« (obligatoire) »** ou **« (facultatif) »** en texte ; aide en dessous 14 px `ink-600` ; compteur de caractères visible quand une borne existe (15–100, 150–5 000).
- **Erreur** : sous le champ, icône + texte « Le titre doit comporter au moins 15 caractères (il en compte 9). » ; contour `error-700` 2 px ; `aria-describedby` ; un **résumé** en tête de formulaire avec liens vers les champs (focus déplacé dessus après soumission) ; les **valeurs saisies sont conservées**.
- Montants : champ numérique avec suffixe fixe « FCFA », espaces de milliers à l'affichage, saisie des chiffres seulement, clavier numérique sur mobile.
- Téléversement : nom, taille, progression, résultat du contrôle (« Vérifié », « En cours de vérification », « Refusé : format non autorisé »), bouton « Retirer » ; fichier en quarantaine **non téléchargeable** (ARB §14).

### 6.4 Cartes

| Carte | Contenu obligatoire (toutes largeurs) |
|---|---|
| Service | Image 4:3 (ou emplacement), titre (2 lignes max, **toujours accessible en entier** dans le détail), vendeur, **prix « 35 000 FCFA »**, délai « 5 jours », avis **réels** (note + nombre) ou rien |
| Mission | Titre, catégorie, budget/fourchette, mode (à distance / ville), **date limite de candidature**, nombre de propositions (si publié par règle) |
| Proposition | Freelance (nom, profil), **prix ferme**, **délai de réalisation**, livrables, corrections incluses, **validité**, extrait du message, action « Choisir cette proposition » |
| Commande | Référence, titre, **autre partie**, **état** (badge), **échéance**, **montant**, **marqueur d'action** |
| Ligne financière | Libellé, **montant**, **état** (badge), **référence**, **date de dernier contrôle** |
| Compteur (KPI) | Nombre + libellé clair + lien vers la liste ; **placé après** les actions attendues |

### 6.5 Tableaux → cartes (jamais de perte d'information, DEC INS §4)

Au-dessous de 768 px, une ligne de tableau devient une **carte** qui porte **les mêmes libellés** (« Montant : », « Échéance : », « État : »). Les colonnes jugées secondaires passent dans un **détail dépliable explicitement libellé** ; **montants, dates, statuts et référence ne sont jamais** masqués.

### 6.6 Notices, états vides, chargement

| Composant | Règle |
|---|---|
| Notice | Icône + titre + phrase ; **quatre tons** ; rôle `status` (info/succès) ou `alert` (erreur) ; pas de disparition automatique pour une information qui engage |
| État vide | Illustration **absente ou minimale** ; phrase « Aucune proposition reçue pour l'instant. » + **action utile** (« Vérifier le contenu de votre besoin ») |
| Chargement | **Squelette** à la structure de la page ; message texte pour lecteurs d'écran ; verrouillage des soumissions |
| Erreur de page | Message sobre, code de corrélation, **action** (« Réessayer », « Contacter le support ») ; sans donnée privée |

### 6.7 Confirmations d'actions sensibles (SRC ARB §9)

Dialogue modal (focus piégé, `Échap` ferme, retour du focus) à **trois parties** : **objet précis** (« Valider la livraison v2 de DEMO-26018 »), **conséquences** (« La commande sera clôturée. Le reversement dépendra de la vérification du bénéficiaire. Vous ne pourrez plus demander de correction. »), **boutons** (« Valider la livraison » / « Revenir à la livraison »). Pas de case « ne plus demander ». Jamais pour une action réversible et sans effet financier.

### 6.8 Marquage de démonstration (SRC N04)

Bandeau **permanent** sous l'en-tête : « **Démonstration** — Les données et paiements affichés sont fictifs. » (fond `surface-2`, texte `ink-900`, icône, 14 px, **non masquable**). Les entités fictives portent « (exemple) » dans leur titre ; les références commencent par **DEMO-** ; aucun faux avis, volume ou badge.

---

## 7. Gabarits et grille

| Largeur | Grille | Remarque |
|---|---|---|
| 360 / 390 | 1 colonne, marges 16, empilement vertical | Action principale visible dans le premier écran de tâche |
| 768 | 2 colonnes pour les cartes (≥ 2), formulaires 1 colonne ≤ 640 px | Tiroir de navigation conservé |
| 1024 | Menu latéral 248 + contenu ; 3 colonnes de cartes (public) | Panneau d'aide/récapitulatif collant à droite (320 px) pour formulaires longs |
| 1440 | Largeur utile bornée (1200/1280) et centrée ; **ne pas étirer** | Le contenu ne s'élargit pas au-delà : lisibilité |

Comportement détaillé écran par écran : `04`.

---

## 8. Liste de contrôle de cohérence (applicable à chaque écran)

1. Un seul **H1** ; hiérarchie H1 > H2 > H3 sans saut.
2. **Où suis-je ?** (titre + repère de navigation + fil d'Ariane si profondeur ≥ 3) · **Que faire maintenant ?** (une action primaire identifiable) · **Que se passera-t-il ?** (phrase d'effet sous ou près du bouton).
3. Une **seule** action primaire pleine par vue ; autres actions secondaires/tertiaires.
4. Couleurs **exclusivement par jetons** ; orange hors statut.
5. Chaque statut = icône + libellé + couleur ; chaque état vide = action utile ; chaque erreur = champ + texte + conservation.
6. Montants avec « FCFA » et espace insécable ; dates avec heure d'Abidjan ; échéance ≠ date de contrôle.
7. Cibles ≥ 44 × 44 ; espaces ≥ 8 px entre cibles.
8. Focus visible partout ; ordre de tabulation = ordre visuel.
9. Rien n'est conditionné au survol ; tout menu survolable a un équivalent clic/toucher/clavier.
10. Pas de défilement horizontal de la page ; pas de texte essentiel tronqué par une ellipse sans accès au texte complet.
11. Rayons, ombres et espacements issus des échelles §5 ; pas de valeur intermédiaire.
12. Une seule famille de police et un seul jeu d'icônes ; icônes d'action accompagnées d'un libellé (sauf fermer, rechercher, avec nom accessible).
13. Contenus fictifs étiquetés ; aucun avis, badge ou chiffre non justifié.
14. Mouvement ≤ 200 ms, respecte la réduction des mouvements.
15. Aucun élément fixe ne masque un champ ou du contenu (marge basse compensée, masquage quand le clavier mobile est ouvert).

---

## 9. Illustration des jetons (non implémentée)

Forme prévue des jetons en Tailwind v4 (`@theme`), **donnée à titre de spécification** ; aucun fichier n'a été créé :

```css
@theme {
  --color-marine-900: #102D4B;  --color-marine-800: #122F4E;
  --color-marine-700: #183B60;  --color-marine-100: #E6EDF5;
  --color-canvas: #F7F8FB;      --color-surface: #FFFFFF;     --color-surface-2: #EEF2F7;
  --color-line: #D9E0E9;        --color-line-strong: #7A8799;
  --color-ink-900: #0F1B2D;     --color-ink-600: #4B5968;
  --color-accent-700: #A63F14;  --color-accent-300: #FFB48A;  --color-accent-100: #FFF1E8;
  --color-info-700: #1F5FA6;    --color-info-50: #E8F1FB;
  --color-success-700: #1E6B45; --color-success-50: #E8F5EE;
  --color-warning-700: #7A4B00; --color-warning-50: #FFF4D6;
  --color-error-700: #B3261E;   --color-error-50: #FDECEA;
  --color-focus: #1D6FD1;
  --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
}
```

Les ratios du tableau §3 sont calculés par la formule de luminance relative WCAG ; ils seront **recontrôlés dans le navigateur** (outil à choisir) lorsque l'interface existera.
