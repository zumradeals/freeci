# 05 — Décisions, couverture et critères de validation

> **Statut : base de travail acceptée par le porteur (revue du 2026-10-05).** Les écrans restent à présenter en maquettes haute fidélité et à examiner visuellement avant tout développement ; les règles métier sont portées par le serveur. **Publication** dans le dépôt public autorisée par le porteur (D19), hors secrets, identifiants, données personnelles réelles et pièces client originales.
> Ce document consigne ce qui est **décidé**, **proposé**, **ouvert** ; il ne déclare rien « testé » : aucune application n'existe.

Étiquettes : **DEC** décision du porteur · **PROP** proposition · **Q** question · **SRC** source.

---

## 1. Registre des arbitrages

### 1.1 Décisions du porteur (DEC)

| Réf. | Décision | Trace |
|---|---|---|
| D01 | Nom du produit : **FreeCI** | `01` §4 |
| D02–D04 | **Laravel/PHP**, **PostgreSQL**, organisation modulaire (interface / actions métier / intégrations) | `02` §1, §3 |
| D05 | `dgafrique-core` = référence à examiner | `02` §2 |
| D06 | Choix « Next.js » du dossier client **dépassé** ; exigences métier conservées | `01` C01 |
| D07 | Données propres ; aucune dépendance à l'identité, la fédération ou le paiement de GAMAD sans décision explicite | `02` P7, §7 |
| D08–D10 | Beauté, intuitivité, responsivité traduites en choix vérifiables ; 360/390/768/1024/1440 ; 44 × 44 px ; WCAG AA ; zoom 200 % | `03`, `04` §0 |
| D11 | Aucune application, migration, dépendance, déploiement, fusion ni activation de paiement à cette étape | Respecté |
| D12 | *(supplantée par D19)* Dépôt public : documents client et dérivés en local tant que non autorisés | `01` §4 |
| D13 | **Départ de zéro** : maquettes validées et SQL n'existent pas ; trois documents = bases de réflexion ; quatre captures = références visuelles partielles (**clôt Q01, Q02**) | `01` §1, C03, C04 |
| D14 | Modèle de données **conçu depuis les besoins et invariants** ; 55 tables non objectif ; migrations à l'implémentation | `02` §4 |
| D15 | Pile **validée** : Laravel, PostgreSQL, Blade + Livewire + Tailwind + Alpine, monolithe modulaire ; autonomie vis-à-vis de GAMAD (**clôt Q08**) | `02` §1, §5 |
| D16 | **Conventions Laravel** pour les noms de tables ; file de tâches **sur PostgreSQL** acceptable, sous réserve de documenter concurrence, reprises, prévention des doubles effets financiers (**clôt Q11 en partie**) | `02` §4.1, §8.3 |
| D17 | **Versions exactes à vérifier** avant installation ; aucune version citée n'est approuvée | `02` §8.1 |
| D18 | **Direction visuelle validée** (marine, fonds clairs, orange en accent, actions avant compteurs, administration distincte) ; beauté, intuitivité, responsivité, identité reconnaissable ; écrans à présenter et examiner visuellement | `03`, `04` |
| D19 | **Publication autorisée** des cinq documents dans le dépôt public (visibilité inchangée) ; exclus : secrets, identifiants, données personnelles réelles, pièces client originales (**clôt Q16**) | §6 |

### 1.2 Arbitrages proposés (PROP) — à valider

| Réf. | Sujet | Choix proposé | Alternative écartée | Raison | Repères |
|---|---|---|---|---|---|
| A01 | Architecture | Monolithe modulaire à 7 modules + `Shared` | Micro-services | Transactions locales, un seul déploiement (CDC §14) | `02` §3 |
| A02 | Couches | Interface → **Actions** → Intégrations ; règle testable | Contrôleurs « riches » (constaté dans la référence) | D04 ; vérifiabilité | `02` §2, §3.2 |
| A03 | Interface | Blade + Livewire (îlots) + Tailwind v4 (jetons) + Alpine **fourni par Livewire** | SPA ; Alpine séparé | SEO, performance mobile, formulaires sans JS ; une seule version d'Alpine | `02` §5 |
| A04 | Livewire | Adaptateur **mince** appelant une Action ; propriétés sensibles verrouillées ; prototype avant lot 1 | Règles dans les composants | La référence n'exerce pas Livewire (aucun composant) | `02` §5, §9.3 |
| A05 | Authentification | Locale (Laravel), MFA pour le personnel, **sans** fédération | Identité déléguée (ARC §3) | D07 | `01` C05 ; `02` §8.2 |
| A06 | Modèle de données | **Concevoir depuis les besoins et invariants** ; migrations Laravel produites à l'implémentation | Reprendre un SQL (inexistant) | D13, D14 | `02` §4 |
| A07 | Besoins non couverts par ARC | Couvrir : signalements, décision de modération, préférences de notification, réponse à un avis, preuves de litige, annulation après paiement (forme à décider dans le modèle) | Les laisser implicites | Exigences F10, F33–F39 | `02` §4.2 |
| A08 | Paiement | **Port** `PaymentProvider` + adaptateur **simulé** étiqueté ; réel après qualification | Client lié à un prestataire (modèle `GeniusPayClient`) | D07 ; prestataire non qualifié | `02` §7 |
| A09 | Confirmation | Webhook authentifié + vérification serveur ; retour navigateur **sans effet** ; pas de bouton « Payer » pendant `pending`/`unknown` | Confirmation au retour | F25, T07 | `04` §9 |
| A10 | Recherche | Plein texte PostgreSQL, pagination ≤ 20 | Moteur externe | Simplicité V1 ; mesure N06 | `02` §8.2 |
| A11 | Registre | Lots équilibrés, immuables, contrainte différée ; idée « additive » de la référence reprise | Mise à jour d'un solde | F28, ARC §6 | `02` §4.3 |
| A12 | Identité visuelle | Marine + fonds clairs conservés ; **orange = accent uniquement** ; sémantique séparée ; 19 jetons | Orange pour statuts | `01` C11 ; INS §4 | `03` §3 |
| A13 | Statuts | **Badge d'état** (icône + libellé + ton) distinct du **marqueur d'action** | Couleur seule ; « Livrée » orange | N03 | `03` §3.4 |
| A14 | Typographie | Une famille, auto-hébergée, échelle fixe, ≥ 14 px pour l'essentiel | Plusieurs polices | N05, lisibilité | `03` §4 |
| A15 | Tableaux de bord | **Actions + échéances d'abord**, compteurs en dernier | Compteurs d'abord (IMG-2/3) | INS §4 ; C08 | `04` §6–§7 |
| A16 | Navigation mobile | Tiroir ; **pas** de barre d'onglets fixe en V1 | Barre de 5 onglets (modèle de la référence) | ARB §1 ; éviter de masquer le contenu | `03` §6.1 |
| A17 | Comparaison | Tri prix/délai/date ; côte à côte ≤ 3 ; **aucun score** | Classement algorithmique | Pas de promesse de qualité | `04` §5.2 |
| A18 | Administration | **Coque distincte**, habilitations, MFA ; sélecteur de rôle de IMG-4 = démonstration seulement | Même coque que le public | ARB §6 ; INS | `01` C10 |
| A19 | Démonstration | Bandeau permanent, `DEMO-`, identités manifestement fictives, hors statistiques | Faux avis/volumes | N04 ; INS §5 | `04` §0.4 |
| A20 | Documents | **Publiés** dans le dépôt public (D19), sans pièces client originales | Dossier local | D19 | §6 |
| A21 | File de tâches | **PostgreSQL** au démarrage ; garanties et essais documentés ; migration possible vers Redis | Redis d'emblée | D16 ; simplicité | `02` §8.3 |
| A22 | Noms de tables | Conventions Laravel (pluriel, `snake_case`) ; noms de ARC = conceptuels | Noms de ARC | D16 | `02` §4.1 |

---

## 2. Couverture des exigences

Niveau : **Approfondi** (écran/action conçu dans `04` et `02`) · **Inventorié** (écran listé, conçu avec les composants communs, sans maquette détaillée) · **Hors dossier** (à concevoir avec un tiers ou plus tard).

### 2.1 Exigences fonctionnelles F01–F42

| F | Sujet | Écrans | Module · action | Niveau |
|---|---|---|---|---|
| F01 | Inscription | A01 | Accounts · `Register` | Inventorié |
| F02 | Vérification et connexion | A02–A05 | Accounts · `VerifyEmail` (lien **non réutilisable**, messages **génériques**) | Inventorié (adaptation `02` §8.2) |
| F03 | Gestion du compte | C01 | Accounts | Inventorié |
| F04 | Profil freelance | FR02, P07 | Accounts · `PublishFreelancerProfile` | Inventorié (liste de contrôle `04` §7) |
| F05 | Portfolio | FR02, P07 | Catalog | Inventorié |
| F06 | Informations du client | C01 | Accounts | Inventorié |
| F07 | Statut du compte | C01, AD03 | Accounts | Partiel (états `04` §6–§8) |
| F08 | Vérification pour reversements | FR08, FR07 | Finance · bénéficiaire | Inventorié (bloc `04` §7) |
| F09 | Publication de service | FR04 | Catalog · `SubmitService` | Inventorié |
| F10 | Cycle de publication | FR03, AD02 | Catalog · `Publish/Fix/Suspend/Archive` | **Approfondi** `04` §10 |
| F11 | Recherche et filtres | P02 | Catalog · `SearchServices` | **Approfondi** `04` §2 |
| F12 | Détail du service | P03 | Catalog · `GetPublishedService` | **Approfondi** `04` §3 |
| F13 | Favoris et contact | P03, C08, C02 | Catalog · `ToggleFavorite` ; Communication | Partiel (C08 **reportable**) |
| F14 | Publication de mission | CL03 | Missions · `SubmitMission` | **Approfondi** `04` §4 |
| F15 | Visibilité des pièces | CL03 | Files · `UploadPrivateFile` | **Approfondi** `04` §4 |
| F16 | Proposition du freelance | FR06 | Missions · `SubmitProposal` | **Approfondi** `04` §5 |
| F17 | Comparaison et sélection | CL04 | Missions · `SelectProposal` | **Approfondi** `04` §5 |
| F18 | Fermeture et réouverture | CL04 | Missions · `Close/ReopenMission` | **Approfondi** `04` §5 |
| F19 | Accord de commande | C05, C06 | Orders (instantané) | **Approfondi** `04` §8–§9 |
| F20 | Commande de service | P03, C05 | Orders · `RequestService`, `AcceptServiceRequest` | **Approfondi** |
| F21 | Démarrage | C05, C06 | Finance · `ConfirmPayment` ; Orders · `CompleteBrief` | **Approfondi** |
| F22 | Report et retard | C05 | Orders · `RequestExtension`, `AnswerExtension` | **Approfondi** `04` §8 |
| F23 | Historique | C05 | Orders · `order_event` | **Approfondi** |
| F24 | Moyens de paiement | C06 | Finance · `InitiatePayment` (moyens **activés**) | **Approfondi** `04` §9 |
| F25 | Confirmation serveur | C06 | Finance · `ConfirmPayment`, `RefreshPaymentStatus` | **Approfondi** |
| F26 | Reversement | FR07, AD06 | Finance · `InitiatePayout` | Partiel (`04` §7, §10 ; FR07 détaillé : à faire) |
| F27 | Remboursement et incident | C05 Finances, AD06 | Finance · `InitiateRefund`, `RecordLatePayment` | Partiel |
| F28 | Registre financier | AD06 | Finance (lots) | Inventorié (`02` §4.3) |
| F29 | Livraison formelle | C05 | Orders · `DeliverOrder` | **Approfondi** |
| F30 | Accès aux livrables | C05 | Files · `IssueDownload` | **Approfondi** |
| F31 | Corrections | C05 | Orders · `RequestCorrection` | **Approfondi** |
| F32 | Validation | C05 | Orders · `ValidateDelivery` | **Approfondi** |
| F33 | Annulation | C05 | Orders · `CancelBeforePayment`, `RequestCancellation` | **Approfondi** `04` §8.4 |
| F34 | Ouverture de litige | C05, AD07 | Orders · `OpenDispute` | Partiel |
| F35 | Résolution | AD07 | Administration · `ResolveDispute` | Partiel (`04` §10.3) |
| F36 | Avis | C05 Avis | Orders · `SubmitReview` | Inventorié |
| F37 | Messagerie | C02, C05 | Communication · `SendMessage` | Partiel |
| F38 | Notifications | C03, C01 | Communication | Inventorié (Q12) |
| F39 | Modération | AD02, AD04 | Administration | **Approfondi** `04` §10 (AD04 : gabarit) |
| F40 | Gestion des utilisateurs | AD03 | Accounts / Administration | Inventorié |
| F41 | Transactions et support | AD05–AD08 | Administration, Finance | Partiel (gabarit `04` §10.3) |
| F42 | Paramètres et indicateurs | AD01, AD09 | Administration · `ChangePolicy` | Partiel (AD01 ; AD09 : à faire) |

**Non approfondis dans ce dossier** (inventoriés, à maquetter ensuite, `04` §11.1 lots 4–6) : A01–A05, C01, C03, C07, FR02–FR05, FR07, FR08, CL02, CL05, AD03–AD10 (sauf gabarit), P04–P10.

### 2.2 Exigences non fonctionnelles N01–N18

| N | Sujet | Traitement dans ce dossier | Statut |
|---|---|---|---|
| N01 | Interface française, FCFA, heure d'Abidjan | `03` §4 (montants, dates) | Conçu |
| N02 | Mobile à partir de 360 px, sans défilement horizontal | `04` §0.2 | Conçu (**non testé**) |
| N03 | Accessibilité | `03` §3.4, §5, §8 ; `04` §0.2 | Conçu (**non testé**) |
| N04 | Clarté, démonstration, pas d'activité fictive | `03` §6.8 ; `04` §0.4 | Conçu |
| N05 | 3 s catalogue/détail (5 Mbit/s, 150 ms) | Rendu serveur, budget de police `03` §4 | Moyens prévus ; **à mesurer** |
| N06 | 10 000 comptes, 5 000 services, 1 000 missions, 100 sessions ; 95 % < 1,5 s | Recherche `02` §8.2 | **À mesurer** |
| N07 | 99,5 % de disponibilité | `02` §9.1 | Dépend de l'hébergeur (Q06) |
| N08 | Sauvegarde et reprise | `02` §9.1 | Conçu ; **restauration à prouver** |
| N09 | SEO et confidentialité de cache | `02` §5, §8.2 | Conçu |
| N10 | Accès aux données | `02` §4.5 | Conçu |
| N11 | Protection des comptes, MFA admin | `02` §8.2 | Conçu |
| N12 | HTTPS, secrets | Principe `02` P3, §7 ; mise en œuvre au lot 0 | **Partiel** |
| N13 | Entrées et fichiers | `02` §7, §8.2 ; `03` §6.3 | Conçu ; Q13 (gros fichiers) |
| N14 | Traçabilité et incidents | `02` §3.3 (audit) ; `04` §10 | Partiel (procédure d'incident : hors dossier) |
| N15 | Information et droits (données personnelles) | P10, C01 | **Hors dossier** (contenus juridiques) |
| N16 | Conservation | — | **Hors dossier** (registre des durées à fournir) |
| N17 | Sous-traitants | `04` §9 (saisie chez le prestataire) | **Partiel** (documentation à établir, Q06) |
| N18 | Conditions du service | P10 ; `04` §3.2, §9 (versions enregistrées) | **Hors dossier** (textes à fournir) |

---

## 3. Critères de validation métier (revue de maquette → recette future)

Chaque critère est vérifié **en revue de conception** par examen des maquettes décrites, puis **converti en essai** au développement (T01–T18 du CDC §16 ; essais ARC §14).

| Critère de conception | Preuve attendue en revue | Essai futur |
|---|---|---|
| Deux entrées → **un seul dossier** (C05) | Parcours `04` §11 : aucune étape propre à l'une n'existe après le paiement | T05, T06 |
| **Le travail ne commence qu'après paiement confirmé et brief complet** | Aucun écran n'offre « Livrer » avant `in_progress` ; « En attente du brief » existe | T06, T09 |
| Compte à deux rôles, **droits indépendants de l'espace affiché** | Policies bornées par relation ; sélecteur sans effet de droit (`02` §4.5) | T16, essai « deux rôles » |
| **Achat de son service et candidature à sa mission refusés** (même en requête directe) | Garde-fous `04` §3.3, §5.1 + règle serveur | T06, T05 |
| Une proposition active par freelance et mission ; versions conservées | Variantes FR06 ; historique des versions | T05 |
| **Sélection exclusive** (clics simultanés) | `SelectProposal` : verrou et index ; écran « mission réservée » | T05 |
| Version/validité périmées **jamais acceptées silencieusement** | Bandeau « Modifiée (v2 → v3) » ; expirée/retirée | T05 |
| Accord **figé** ; paramètres commerciaux sans effet rétroactif | Accord en lecture seule ; `policy_version` | T06, T14 |
| **Retour navigateur sans effet financier** ; vérification serveur | C06 : état lu en base ; pas de « Payer » pendant la vérification | T07, T08 |
| **Arrêt d'un worker ou rejeu** → un seul effet financier ; état explicable ; rapprochement possible | Garanties `02` §8.3 | T08, T09, T12, T13 |
| Notification répétée → **un seul** effet ; paiement tardif/doublon isolé | Écran « en cours d'examen » ; `RecordLatePayment` | T08, T09 |
| Livraison **formelle** et versionnée ; fichier de messagerie ≠ livraison | Rappel en C05 Messages ; versions v1…vn | T10 |
| **Silence du client ≠ validation ni reversement** | Bandeau « rien n'est validé automatiquement » | T11 |
| Litige : reversement **bloqué** ; décision motivée ; exécution suivie séparément | Dialogues `04` §8.4 ; AD07/AD06 | T12 |
| **États distincts** (commande / paiement / remboursement / reversement) | Onglet Finances en 3 blocs ; FR01 en 4 montants | T12, T13 |
| Reversement : une seule opération ; résultat inconnu rapproché avant tout renvoi | Dossier « Résultat inconnu » `04` §10.3 | T13 |
| Calcul entier : 100 000 → 10 000 / 90 000 ; remboursement 20 000 → 8 000 / 72 000 | Valeurs d'exemple cohérentes dans les maquettes | T14 |
| Avis : réservés aux parties, publication différée | Onglet Avis | T15 |
| Modérateur **sans** pouvoir financier ; actions sensibles : MFA, motif, double approbation | Files par habilitation ; `ApproveSensitiveAction` | T16 |
| Accès tiers : **même message** pour absent et interdit | `04` §0.3 | T16, « accès d'un autre utilisateur » |
| Fichiers privés : lien court, quarantaine, `clean` seulement | Mentions `04` §8.2, `02` §7 | T02, T04, « fichier non contrôlé » |

---

## 4. Critères de validation visuelle et responsive

Statut de tous les critères : **non testé — aucune application** (les ratios de contraste sont calculés sur des jetons, `03` §3).

| N° | Critère | Seuil / méthode | Quand |
|---|---|---|---|
| V01 | **« Où suis-je ? / Que faire ? / Que se passera-t-il ? »** | Test des 5 secondes sur la maquette : un relecteur nomme l'écran, l'action principale et son effet | Maquette |
| V02 | Une seule action primaire par vue | Revue de chaque maquette | Maquette |
| V03 | Aucun défilement horizontal de page | Contrôle à **360, 390, 768, 1024, 1440 px** | Maquette puis application |
| V04 | Aucun montant, date ou statut tronqué | Revue ; cas à longs libellés (« Marketing et Communication ») | Idem |
| V05 | Cibles **≥ 44 × 44 px** ; **≥ 8 px** d'écart | Mesure des composants | Idem |
| V06 | Contraste **WCAG AA** : texte ≥ 4,5:1 ; grand texte et composants ≥ 3:1 | Recalcul des jetons ; contrôle navigateur | Jetons (calculé) ; application (à faire) |
| V07 | Statut jamais par la couleur seule | Revue : icône + libellé sur chaque badge | Maquette |
| V08 | Focus visible ; ordre de tabulation = ordre visuel ; tout accessible au **clavier** | Parcours clavier complet de P01→C06 | Application |
| V09 | **Rien de dépendant du survol** | Revue des composants ; essai tactile | Idem |
| V10 | **Zoom 200 %** : lecture complète, sans perte de fonction | Essai à 360 px × 200 % | Idem |
| V11 | Barres fixes **ne masquent** ni contenu ni champ ; retirées avec le clavier mobile | Essai appareil | Idem |
| V12 | `prefers-reduced-motion` respecté ; durées ≤ 200 ms ; aucune animation décorative | Essai avec réglage activé | Idem |
| V13 | Erreurs proches des champs, **données conservées**, focus sur la première erreur | Essai de formulaires CL03/FR06/P03 | Idem |
| V14 | Tableaux → cartes **sans perte** (montant, date, statut, référence) | Comparaison 1440 vs 360 sur C04, FR07, CL05 | Idem |
| V15 | États vide/chargement/erreur/succès/accès interdit présents sur les **9 écrans** | Liste de contrôle `04` §2–§10 | Maquette |
| V16 | Pas d'orange en statut ; ≤ 2 occurrences d'accent par écran | Revue des jetons | Maquette |
| V17 | Cohérence : boutons, champs, icônes, badges issus des **mêmes jetons** | Liste `03` §8 | Maquette |
| V18 | Contenus de démonstration **étiquetés** ; aucun faux avis/volume/badge | Revue de contenu | Maquette |
| V19 | Perf. N05 : contenu principal catalogue/détail < 3 s (5 Mbit/s, 150 ms) | Profil réseau simulé | Application |
| V20 | Lecture de l'écran à **390 px** équivalente à 360 px (même structure) | Revue | Maquette |
| V21 | **Maquettes haute fidélité présentées et examinées par le porteur** avant chaque lot (≥ 360 et 1440 px ; 390/768/1024 pour comparaison, commande, paiement) | Séance de revue ; décision consignée | Avant développement |
| V22 | **Identité reconnaissable** : les cinq signatures `03` §2.4 sont présentes et non diluées | Revue : logo masqué, l'écran reste identifiable à marine + filet d'action + point orange + montants | Maquette |
| V23 | **Proportions** conformes à `03` §2.5 (grille, hauteurs, rythme, un point focal) | Revue sur gabarit superposé | Maquette |

---

## 5. Questions

### 5.1 Questions tranchées par le porteur (2026-10-05)

| Réf. | Question | Décision |
|---|---|---|
| Q01 | Maquettes validées ? | **N'existent pas** ; on part de zéro (D13) |
| Q02 | SQL de 55 tables ? | **N'existe pas** ; modèle conçu depuis les besoins, 55 non objectif, migrations à l'implémentation (D13, D14) |
| Q08 | Pile Blade + Livewire + Tailwind + Alpine ? | **Validée** (D15) |
| Q11 | Choix d'amorçage | **Noms de tables : conventions Laravel** ; **file sur PostgreSQL** acceptable sous réserve de documentation (`02` §8.3) ; versions à vérifier, non approuvées (D16, D17). **Reste ouvert** : UUID v4/v7 et schéma (à l'amorçage) |
| Q16 | Publication des documents ? | **Autorisée** dans le dépôt public, hors secrets, identifiants, données personnelles réelles, pièces client originales ; visibilité inchangée (D19) |

### 5.2 À trancher avant le prompt de développement

| Réf. | Question | Recommandation |
|---|---|---|
| Q11 (reste) | **Versions exactes** (PHP, PostgreSQL, Laravel, Livewire, Tailwind, Vite) après vérification de compatibilité et de support ; UUID v4/v7 ; schéma | Vérifier les pages officielles, proposer un tableau de versions **à approuver**, puis figer |
| Q19 | **Format de présentation** des maquettes haute fidélité (images, PDF, prototype statique jetable séparé de l'application) | Images/PDF aux largeurs de `04` §0.2 pour le lot 1 |
| Q05 (présentation) | Répartition des frais et ce que voit chaque partie, avant les maquettes C05/C06 | Valeurs d'exemple étiquetées, paramétrables |

Aucune question ne bloque l'**examen** des documents.

### 5.3 Questions non bloquantes (à traiter avant les lots concernés)

| Réf. | Question | Quand | Défaut proposé |
|---|---|---|---|
| Q03 | **Prestataire de paiement** et modèle de circulation des fonds (séquestre, reversement différé, bénéficiaire juridique, remboursements) | **Avant lot 4 et lancement** ; non bloquant pour les lots 0–3 (paiement simulé) | Port + adaptateur simulé |
| Q04 | Dessin définitif du logo, couleur de « CI », signature | Avant maquettes finales | Spécification `03` §2 |
| Q05 | **Paramètres commerciaux** : commission 10 %, **répartition des frais** (supportés par la plateforme ?), ce que voit le client (frais affichés), bornes 5 000–500 000 FCFA, délais 48 h / 24 h / 7 j / 14 j, seuil de double validation, quotas 10 Mo / 100 Mo / 500 Mo | Avant maquettes du lot 3 (C05/C06) pour la **présentation** ; avant lancement pour les valeurs | Paramètres versionnés, valeurs d'exemple étiquetées |
| Q06 | Hébergement, pays de traitement, courriel, stockage, analyse de fichiers ; documentation des sous-traitants (N17) | Avant recette | À choisir selon coût et conformité |
| Q07 | **Catalogue des habilitations** du personnel (modération, support, finance, droits, journal) | Lot 0 | 5 : modération · support · finance · droits · journal |
| Q09 | Authentification : locale confirmée ? Fortify ou kit officiel ? | Lot 0 | Locale ; outil choisi à l'amorçage |
| Q10 | **Rattachement de FreeCI à GAMAD** (projet distinct, lié, ou composant) | Gouvernance | **Projet distinct, potentiellement intégrable** (aucune déclaration d'appartenance) |
| Q12 | Notifications « secondaires » et préférences (F38) | Lot 2 | Essentielles non désactivables ; messages regroupés |
| Q13 | Gros fichiers (jusqu'à 100 Mo) : mode de téléversement et stockage | Prototype lot 1 | Chemin dédié, validation des limites |
| Q14 | Mission **réservée** : visibilité publique ? Mission **modifiée** après propositions : effet ? (`01` C15, `04` §4.4) | Lot 2 | « En cours de sélection », non proposable ; propositions marquées « Besoin modifié » |
| Q15 | Usage **mobile** de l'administration ? | Lot 3 | Complet ≥ 768 px ; fonctions conservées en dessous |
| Q17 | La **démonstration** sera-t-elle publique ? (CDC §2 : « démonstration privée ») | Avant mise en ligne | **Privée**, paiement simulé |
| Q18 | Bornes de titre/description des **missions** (CDC ne les donne que pour les services) | Lot 1 | 15–100 / 150–5 000 caractères |

---

## 6. Éléments manquants

| Élément | Impact | Responsable présumé |
|---|---|---|
| **Maquettes haute fidélité** des écrans prioritaires (n'existent pas : à produire, puis à examiner) | Examen visuel avant développement (D18) | Conception |
| **Modèle de données détaillé** (n'existe pas : à concevoir depuis les besoins) | Avant la première migration (D14) | Conception |
| Contrat et documentation du prestataire de paiement ; liste des moyens activés | Modèle de fonds, états, adaptateur (Q03) | Porteur / prestataire |
| Textes légaux : mentions de l'opérateur, conditions, confidentialité, règles de commande/commission/remboursement/litige, durées de conservation | N15–N18, P10 | Porteur / conseil |
| Statut juridique de l'opérateur ; traitement fiscal et facturation | Reçu ≠ facture (CDC §9) | Conseil comptable |
| Responsable et horaires du **support** ; procédure de litige | Condition de lancement (CDC §18) | Porteur |
| Logo (fichier vectoriel), éventuelle photographie, contenus éditoriaux réels (services pilotes) | Identité, page d'accueil | Porteur |
| Liste des **extensions de fichiers métiers** autorisées (DWG, IFC, RVT, TEKLA…) | Règles de fichiers (CDC §10) | Porteur / pilote |
| Hébergement, budget, volumétrie réelle | N06–N08 | Porteur / équipe |
| **Vérification des versions** : support et compatibilité PHP / Laravel / Livewire / Tailwind / Vite / PostgreSQL (sites de support inaccessibles depuis l'environnement de conception) | Approbation des versions (D17) | Équipe, avant installation |

### Diffusion (Git)

- Publication des cinq documents autorisée par le porteur (D19) dans `zumradeals/freeci` (public) ; **visibilité inchangée**.
- **Exclus** de la publication : secrets, identifiants d'accès, données personnelles réelles, pièces client originales (les trois .docx et les captures ne sont pas versionnés).
- Aucune installation de dépendance, aucune migration, aucun frontend codé, aucun déploiement, aucune activation de paiement.
- Le clone de `dgafrique-core` est **en dehors** du dépôt FreeCI et en lecture seule.

---

## 7. Éléments précis à faire valider au client

Le porteur a validé les **principes** de la direction (D18) et la pile (D15). Les éléments ci-dessous restent à **présenter visuellement** et à faire valider ; la colonne « Réponse attendue » indique ce qui est déjà acquis.

| N° | Élément | Où | Réponse attendue |
|---|---|---|---|
| V-1 | Nom **FreeCI**, signature « Des compétences en Côte d'Ivoire », logotype (« CI » en accent) | `03` §2 | Valider / ajuster |
| V-2 | Palette de **19 jetons**, orange réservé à l'accent, statuts sémantiques | `03` §3 | Principes validés (D18) ; **jetons exacts à examiner sur maquettes** |
| V-3 | Marqueur d'action **distinct** du badge d'état (« Examiner la livraison ») | `03` §3.4 | Valider |
| V-4 | Tableaux de bord **actions d'abord**, compteurs en dernier | `04` §6–§7 | **Validé (D18)** ; à montrer sur maquette |
| V-5 | Accueil : promesse sans chiffre ni avis fictifs ; carte « Services récents » remplacée par « Comment ça marche » si < 3 services | `04` §2 | Valider |
| V-6 | Formulaire de demande de service en **3 étapes** liées à la version du service | `04` §3 | Valider |
| V-7 | Mission en **5 étapes** (mobile), aperçu public, détection de coordonnées privées bloquante | `04` §4 | Valider ; Q18 |
| V-8 | Comparaison **sans score**, côte à côte ≤ 3, récapitulatif d'accord avant paiement | `04` §5 | Valider |
| V-9 | Dossier de commande : bloc « Action attendue », 7 onglets, confirmations à 3 parties | `04` §8 | Valider |
| V-10 | Paiement : **pas de bouton « Payer » pendant la vérification** ; page de résultat pilotée par l'état serveur ; paiement **simulé** étiqueté | `04` §9 | Valider |
| V-11 | **Administration séparée** (coque, MFA, habilitations, accès journalisé) | `04` §10 | Principe **validé (D18)** ; détail : Q07, Q15 |
| V-12 | **Valeurs commerciales** : commission 10 % déduite du freelance ; frais de paiement supportés par la plateforme ; montants 5 000–500 000 FCFA ; 48 h / 24 h / 7 j / 14 j ; seuil de double validation (à fixer) | CDC §18 | Valider ou remplacer (Q05) |
| V-13 | **Transparence** : le client voit prix + frais affichés ; le freelance voit aussi commission et part attribuée | `04` §8.2 | Valider |
| V-14 | Favoris (C08) et préférences de notification : **reportables** ? | CDC §2 | Décider |
| V-15 | Formulation des promesses de paiement (« après paiement confirmé… ») **uniquement après qualification** du prestataire | `04` §2.3 | Valider |
| V-16 | Pile **Blade + Livewire + Tailwind + Alpine** ; **aucune** dépendance à GAMAD (identité, fédération, paiement) | `02` §5, §7 | **Validé (D15, D07)** ; Q10 : rattachement à GAMAD à confirmer |
| V-17 | Sort des documents : publication dans le dépôt public (hors secrets, données personnelles, pièces client) | §6 | **Résolu (D19)** : publication autorisée |
