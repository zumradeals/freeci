# 02 — Architecture proposée (Laravel / PostgreSQL)

> **Statut : base de travail acceptée par le porteur (revue du 2026-10-05).** Les écrans restent à présenter en maquettes haute fidélité et à examiner visuellement avant tout développement ; les règles métier sont portées par le serveur. **Publication** dans le dépôt public autorisée par le porteur (D19), hors secrets, identifiants, données personnelles réelles et pièces client originales.
> Aucune installation, migration ni code n'a été produit. **Les versions citées sont des constats du 2026-10-05, non approuvés** (D17) : à vérifier avant toute installation.

Étiquettes : **SRC** exigence d'une source · **DEC** décision du porteur · **PROP** proposition · **Q** question (`05` §5).

---

## 1. Principes directeurs

| N° | Principe | Origine |
|---|---|---|
| P1 | Monolithe modulaire Laravel : un dépôt, un processus web, un processus de tâches, une base PostgreSQL. Transactions locales à la base. | DEC D02–D04 ; SRC CDC §14, ARC §1 |
| P2 | Trois couches : **Interface** (HTTP, Blade, Livewire) → **Actions métier** (règles, droits, transactions) → **Intégrations** (paiement, fichiers, courriel, analyse antivirus). L'interface n'applique aucune règle ; elle appelle des actions. | DEC D04 |
| P3 | Les montants, droits, états et échéances sont **toujours recalculés côté serveur** depuis les données de référence. Aucun état financier n'est modifiable par une requête générique. | SRC CDC §14, N10, F25 |
| P4 | Toute action sensible est **autorisée, transactionnelle, idempotente et tracée** (clé d'opération, historique, outbox, audit). | SRC F23, ARC §8, §11 |
| P5 | Le moindre pouvoir : chaque rôle et chaque compte technique n'a que ce dont il a besoin ; la séparation conception / validation / exécution est conservée. | PROP (cohérent avec N10, F40, CDC §12) |
| P6 | Les composants externes sont **remplaçables** derrière des ports (prestataire de paiement non qualifié ; stockage, courriel, analyse de fichiers à choisir). | DEC (paiement à qualifier) ; PROP |
| P7 | FreeCI possède ses données : pas de registre partagé, pas d'identité fédérée, pas de prestataire imposé. | DEC D07 |
| P8 | Réversibilité : migrations versionnées, paramètres commerciaux versionnés, aucune donnée de démonstration en production. | SRC CDC §18, ARC §13 ; PROP |

---

## 2. Examen de `zumradeals/dgafrique-core` (référence)

Lecture seule d'un clone superficiel (791 fichiers). **Ce dépôt est un autre produit** (« GAMAD », réseau social d'action ; son README et son `AGENTS.md` indiquent maintenance, « NO-GO production », frontend retiré puis repris). Il sert ici d'exemple d'organisation ; il ne vaut ni autorité ni modèle de maturité pour FreeCI.

### 2.1 Constats vérifiés

| Domaine | Constat (fichier) |
|---|---|
| Versions déclarées | `composer.json` : PHP `^8.3`, `laravel/framework ^13.17`, `livewire/livewire ^4.4`, PHPUnit `^12.5.12`, Pint. `package.json` : `tailwindcss ^4.3.3`, `@tailwindcss/vite ^4.3.3`, `vite ^8.2.2`, `laravel-vite-plugin ^3.2.0`. **Pas de dépendance `alpinejs`** : `resources/js/app.js` importe `{ Livewire, Alpine }` depuis `vendor/livewire/livewire/dist/livewire.esm` (Alpine est fourni par Livewire). |
| Écart interne | ADR-001 et README annoncent « PHP 8.4 » ; `composer.json` exige `^8.3`. |
| Couches | `app/Domain/` (seulement `Identity`), `app/Application/<Domaine>/*Service.php` (27 dossiers : Missions, Ledger, Messaging, Moderation…), `app/Infrastructure/{AI,GamadCore,Payments}`, `app/Http/Controllers` (≈ 97 fichiers, 91 classes `final`, structure quasi plate), `app/Models` (plat, 82 modèles), `app/Providers` par domaine, `routes/cap0xx.php` (un fichier de routes par « capacité »). |
| Contrôleurs | `MissionController` injecte des services **mais importe aussi des modèles et interroge directement** (`PortalAdministrator::query()…`, `Mission`, `Need`…) : la frontière interface/métier n'y est pas étanche. |
| Registre | `Application/Ledger/LedgerService` : journal « simple, immuable, additif », **observe** un paiement déjà confirmé, idempotent (rejouer retrouve la même écriture), sans méthode de mise à jour ni suppression ; correction future par nouvelle écriture liée (`reverses_entry_id`). Pattern pertinent. |
| Paiement | `Infrastructure/Payments/GeniusPayClient` : client **lié à un prestataire**, drapeaux `config('payments.membership.enabled')`, prix canonique codé (500 XOF), erreurs `PAYMENT_PROVIDER_NOT_LIVE`. |
| Interface | `resources/views/components/dg/*` : `button`, `card`, `field`, `input`, `select`, `textarea`, `notice`, `state`, `navigation`, `icon`… (Blade anonymes) ; layouts `public` et `member` ; ≈ 20 feuilles CSS par fonctionnalité. **Aucun composant Livewire** (`app/Livewire` absent). |
| Tests | `tests/Feature/FrontendFoundationTest` rend des composants Blade et vérifie la navigation mobile (contrat canonique) ; `tests/Frontend/*.test.mjs` via `node --test` ; tests de contrat externes pour le paiement. |
| Gouvernance | `AGENTS.md` : hiérarchie de vérité (code + tests > documents), interdiction de réimplémenter une règle métier côté interface, « pas de bouton sans comportement réel, autorisation, retour et preuve ». |
| Données | 62 migrations ; clés `uuid` ; ≈ 260 lignes `DB::transaction`/`lockForUpdate` dans `app/` ; identité portée par un moteur externe (GAMAD Core). |

### 2.2 Ce que FreeCI reprend, adapte ou écarte

| Élément | Décision PROP | Raison |
|---|---|---|
| Laravel + PostgreSQL + Blade + Tailwind v4 via Vite | **Reprendre** (versions à revérifier, §8) | Cohérent avec D02–D04 ; peu de dépendances. |
| `Application/<Domaine>` séparé des contrôleurs | **Adapter** : modules fermés avec `Actions/` explicites (§3) | Rend les contrats d'action nommables et testables (INS §5 « contrat métier appelé par chaque action »). |
| Contrôleurs plats + modèles interrogés depuis le contrôleur | **Écarter** | Contraire à D04 ; règle « interface sans règle ». |
| Un fichier de routes par capacité (`cap0xx.php`) | **Écarter** ; un fichier de routes **par module** | FreeCI n'a pas de référentiel de 84 capacités. |
| `LedgerService` additif, immuable, idempotent | **Reprendre le principe** ; conception propre au modèle F28/ARC §10 (lots équilibrés, écritures signées) | Besoin plus fort (allocation, remboursement, reversement). |
| `GeniusPayClient` lié à un prestataire | **Écarter** ; **port** `PaymentProvider` agnostique + adaptateur simulé | D07 ; prestataire non qualifié. |
| Intégration GAMAD Core (identité, session, fédération), ZUMRA, CAP, satellites | **Écarter** | D07. |
| Alpine via Livewire (pas de paquet séparé) | **Reprendre** | Évite deux versions d'Alpine. |
| Livewire déclaré mais non exercé | **Ne pas s'appuyer sur la référence** pour les patterns Livewire | Aucune preuve exemplaire disponible ; voir §5. |
| Tests de rendu Blade + contrat de navigation | **Reprendre** l'idée : tests de composants et de contrats d'écran | Vérifiabilité (INS §4). |
| `AGENTS.md` / `docs/AI-RULES.md` | **Adapter en plus léger** : un fichier de règles court pour les agents d'exécution (périmètre, interdictions, critères) | Préférence de gouvernance du porteur (instructions limitées et vérifiables). |

---

## 3. Organisation en couches et en modules

### 3.1 Arborescence proposée (PROP)

```text
app/
├── Modules/
│   ├── Accounts/        Actions/ Models/ Policies/ Data/ Enums/ Events/ routes.php
│   ├── Catalog/         (catégories, compétences, services, portfolio, favoris)
│   ├── Missions/        (missions, propositions, sélection)
│   ├── Orders/          (commande, accord, brief, livraisons, corrections, reports, avis)
│   ├── Communication/   (conversations, messages, notifications)
│   ├── Finance/         (opérations, registre, rapprochement, bénéficiaire)
│   └── Administration/  (modération, support, litiges, paramètres, approbations, audit)
├── Shared/              Money, Clock, OperationKey/Idempotence, Outbox, Audit, Files (file_asset)
├── Integrations/        Payments/ (Contract + Sandbox + <Prestataire>), Storage/, Mail/, FileScan/
└── Http/                Controllers minces, FormRequests, Resources, Middleware
app/Livewire/            Composants d'interface (appellent des Actions)
resources/views/         layouts/, components/fc/ (Blade), <module>/…
routes/                  web.php (public), account.php, client.php, freelance.php, admin.php, webhooks.php
```

### 3.2 Règles de dépendance (testables)

```text
Interface (Http, Livewire, Blade)
        │ appelle
        ▼
Actions métier (Modules/*/Actions)  ── utilisent ──►  Models, Policies, Shared
        │ appellent via des ports
        ▼
Integrations (adaptateurs)
```

1. Une **Action** est la seule porte d'écriture d'un module. Les contrôleurs et composants Livewire **n'écrivent pas** via Eloquent.
2. Une Action : (a) autorise via une Policy, (b) valide l'état et la version attendue, (c) ouvre **une** transaction courte, (d) écrit l'historique, l'outbox et le reçu de commande, (e) retourne un résultat typé (DTO) — jamais un modèle brut vers l'interface publique.
3. **Modules entre eux** : lecture par contrats de lecture (`Data/`) ; écriture uniquement via l'Action du module propriétaire ; événements pour la propagation (outbox). Les clés étrangères SQL restent possibles (monolithe), pas l'écriture croisée.
4. **Intégrations** : n'appellent jamais d'Action. Les entrées (notification du prestataire) passent par un contrôleur de webhook de l'interface, qui enregistre l'événement puis déclenche l'Action `Finance\ConfirmPayment`.
5. Contrôle automatique à prévoir : test d'architecture interdisant `Http` → `Models` en écriture et `Integrations` → `Actions` (outil à choisir : arch-tests Pest ou Deptrac — **à vérifier** avant adoption ; la référence utilise PHPUnit seul).

### 3.3 Responsabilités par module

| Module | Responsabilité | Ne fait pas |
|---|---|---|
| **Accounts** | Compte, mot de passe, vérification d'adresse, rôles client/freelance, habilitations du personnel, profil, acceptation des conditions | Aucun droit administratif à l'inscription (F01, N10) |
| **Catalog** | Catégories, compétences, services versionnés, médias, portfolio, favoris, projection publique, recherche | Ne crée pas de commande |
| **Missions** | Missions, pièces privées, propositions versionnées, sélection/réservation, réouverture | Ne touche ni paiement ni brief |
| **Orders** | Commande unique pour les deux entrées : accord figé, brief, cycle d'états, livraisons, corrections, reports, avis, historique | Ne parle pas au prestataire de paiement |
| **Communication** | Conversations, participants, pièces de messagerie, blocage, notifications, courriels | Ne modifie ni prix, ni échéance, ni état financier (CDC §11) |
| **Finance** | Opérations de paiement/remboursement/reversement, événements du prestataire, registre, rapprochement, bénéficiaire, éligibilité | Les montants viennent de l'accord, jamais de l'interface |
| **Administration** | Modération, support, litiges, décisions, paramètres versionnés, approbations à deux personnes, journal | Un agent ne modifie pas directement les tables financières |
| **Shared** | Monnaie, clés d'opération, outbox, audit, fichiers (`file_asset`) | Aucune règle métier de module |

---

## 4. Données (adaptation PostgreSQL)

### 4.1 Statut du modèle (D13, D14, D16)

Il n'existe **ni SQL ni schéma validé** : le modèle est **conçu à partir des besoins (F01–F42, N01–N18) et des invariants métier** (`01` §2.1). Les noms de tables de ARC §3–§6 sont une **base de réflexion** ; **le nombre de tables n'est pas un objectif** (les 55 noms de ARC sont regroupés en 4.2 à titre indicatif, et le modèle peut en compter plus ou moins). Les **migrations Laravel seront produites à l'implémentation** (une série par module), avec `DB::statement` pour ce que le constructeur de schéma ne couvre pas (index uniques partiels, contraintes `CHECK`, déclencheurs, contraintes différées).

**Convention de nommage (D16).** Conventions Laravel : tables en **snake_case au pluriel** (`orders`, `order_agreements`, `outbox_events`), clé `id`, clés étrangères `<singulier>_id`, horodatages `created_at`/`updated_at`. **Dans les documents `02`–`05`, les noms de tables au singulier (`order_record`, `file_asset`, `outbox_event`…) sont des noms conceptuels hérités de ARC** : ils désignent les tables pluriel correspondantes (`order_record` → `orders`) ; le mot réservé `order` n'est jamais utilisé seul comme nom de colonne ou d'alias non cité.

### 4.2 Regroupement indicatif des noms de tables de ARC (base de réflexion, non contraignant)

| Module | Tables (noms ARC) | n |
|---|---|---|
| Accounts | `user_account`, `user_private`, `account_role`, `staff_grant`, `freelancer_profile`, `profile_skill`, `legal_document_version`, `user_acceptance` | 8 |
| Catalog | `category`, `skill`, `portfolio_item`, `portfolio_file`, `service`, `service_version`, `service_media`, `service_skill` | 8 |
| Missions | `mission`, `mission_file`, `mission_skill`, `proposal`, `proposal_version` | 5 |
| Orders | `order_record`, `order_agreement`, `order_brief`, `order_file`, `order_event`, `delivery`, `delivery_file`, `correction_request`, `extension_request`, `review` | 10 |
| Communication | `conversation`, `conversation_member`, `message`, `message_file`, `favorite`, `notification` | 6 |
| Finance | `beneficiary`, `financial_operation`, `provider_event`, `reconciliation_case`, `ledger_account`, `ledger_batch`, `ledger_entry` | 7 |
| Administration | `policy_version`, `support_ticket`, `ticket_message`, `ticket_file`, `dispute`, `dispute_decision`, `sensitive_action_approval`, `audit_event` | 8 |
| Shared | `file_asset`, `outbox_event`, `command_receipt` | 3 |
| **Total des noms listés par ARC** | | 55 |

**Besoins que les écrans et exigences imposent et que ARC ne nomme pas (le modèle conçu devra les couvrir) :**

| Besoin | Source | Table proposée |
|---|---|---|
| Signalements de contenus, messages, avis | F37, F36, F39, AD04 | `report` |
| Décision de modération avec motif (refus, correction, suspension) | F10, F39, AD02 | `moderation_decision` |
| Préférences de notification | F38, CDC §11, C01 | `notification_preference` |
| Réponse à un avis | F36 | `review_response` |
| Preuves jointes à un litige | F34 | `dispute_evidence` (ou réutiliser `ticket_file` + `dispute_id`) |
| Annulation après paiement (demande motivée, examen contradictoire) | F33 | `cancellation_request` |
| Sessions, jetons de récupération, jobs, cache | Laravel | tables du framework (hors périmètre métier) |

**Nommage** : décidé en 4.1 (conventions Laravel, D16). Le modèle définitif — nombre et découpage des tables — sera établi **avant la première migration**, par module, avec ses contraintes et ses essais PostgreSQL.

### 4.3 Types et conventions (reprise de ARC §2, adaptée)

| Sujet | Règle |
|---|---|
| Clés | UUID pour les objets métier (**v4 ou v7 : à fixer à l'amorçage**, avec la version de Laravel retenue), identifiants croissants pour les historiques volumineux. |
| Dates | `timestamptz`, stockées en UTC ; affichées « heure d'Abidjan » (N01). Départ et échéance calculés **une seule fois** (F21). |
| Montants | `bigint` en francs XOF entiers ; objet `Money` côté PHP ; **aucun flottant**. JSON : chaîne décimale. Format d'affichage `100 000 FCFA` avec espace insécable (`03` §5). |
| Taux | Points de base (`1 000` = 10 %) figés dans l'accord (F19). |
| Commission | `(prix × bp + 5 000) / 10 000` en division entière (arrondi au franc le plus proche, demi vers le haut) — ARC §10 ; contrôlé par les cas T14 (100 000 → 10 000 / 90 000 ; remboursement 20 000 → base 80 000 → 8 000 / 72 000). |
| Versions | Lignes de version immuables ; `current_version_id` ; `row_version` pour refuser une action sur version périmée (ARC §2). |
| Immutabilité | Accords, versions, livraisons, décisions, historiques, écritures : protégés contre `UPDATE`/`DELETE` ordinaires (déclencheurs) ; corrections par lignes liées. |
| Équilibre du registre | Lot ≥ 2 lignes, somme nulle, vérifiée par **contrainte différée** à la fin de transaction (ARC §6). |
| Unicités critiques | `proposal_active_uq` (une proposition active ou sélectionnée par mission et freelance) ; `order_mission_live_uq` (une commande vivante par mission) ; clé d'opération et référence prestataire uniques ; **un seul reversement actif ou confirmé par commande** (ARC §5, §6). |
| Séparation public / privé / financier | Projections publiques par **liste explicite de champs** ; données privées dans des tables dédiées ; références du bénéficiaire masquées (ARC §3). |
| Schéma | Schéma dédié FreeCI ou `public` : **à fixer à l'amorçage** avec l'hébergement ; comptes de base distincts pour migration, application, worker et lecture (ARC §13). |

### 4.4 États de la commande (SRC CDC §8, ARC §7)

| De | Vers | Acteur / déclencheur | Action |
|---|---|---|---|
| — | `awaiting_acceptance` | Client (service) | `Orders\RequestService` |
| — | `awaiting_payment` | Client (mission, sélection) | `Missions\SelectProposal` |
| `awaiting_acceptance` | `awaiting_payment` | Freelance | `Orders\AcceptServiceRequest` |
| `awaiting_acceptance` | `cancelled` / `expired` | Freelance (refus) · client (retrait) · délai 48 h | `Orders\DeclineServiceRequest`, `WithdrawServiceRequest`, tâche d'échéance |
| `awaiting_payment` | `awaiting_brief` / `in_progress` | Serveur après paiement **vérifié** | `Finance\ConfirmPayment` |
| `awaiting_payment` | `cancelled` / `expired` | Client · délai 24 h (**seulement si aucun paiement `pending`/`unknown`**) | `Orders\CancelBeforePayment`, tâche d'échéance |
| `awaiting_brief` | `in_progress` | Brief complet | `Orders\CompleteBrief` |
| `in_progress` | `delivered` | Freelance | `Orders\DeliverOrder` |
| `delivered` | `revision_requested` / `validated` | Client | `Orders\RequestCorrection` / `Orders\ValidateDelivery` |
| `revision_requested` | `delivered` | Freelance | `Orders\DeliverOrder` (version suivante) |
| `validated` | `closed` | Serveur | clôture commerciale ; le reversement reste séparé |
| `in_progress`, `delivered`, `revision_requested` | `disputed` | Client ou freelance | `Orders\OpenDispute` |
| `disputed` | `validated` / `revision_requested` / `cancelled` | Support habilité | `Administration\ResolveDispute` |
| après paiement | `cancelled` | Décision motivée | `Orders\RequestCancellation` → `ResolveDispute` |

Le **retard** n'est pas un état : c'est un indicateur calculé sur `due_at` (F22). Le **silence** après 7 jours ouvre un dossier de support sans valider ni reverser (F32). Les états de paiement (`created`, `pending`, `confirmed`, `failed`, `expired`, `unknown`), de remboursement et de reversement (`non éligible`, `éligible`, `demandé`, `en cours`, `confirmé`, `échoué`, + « à rapprocher ») sont **trois suivis séparés** ; l'éligibilité est **calculée**, jamais stockée comme un versement (ARC §7).

### 4.5 Droits par ressource (SRC ARC §12, N10)

| Ressource | Lecture / action | Mécanisme Laravel proposé |
|---|---|---|
| Proposition | Auteur et client propriétaire de la mission | `ProposalPolicy` + requêtes bornées par relation |
| Commande, brief | Client et freelance ; support affecté avec habilitation, motif et audit | `OrderPolicy` ; accès support via `StaffAccess` journalisé |
| Conversation | Participants explicites | `ConversationPolicy` ; pas d'ajout arbitraire |
| Pièce de mission | Client propriétaire ; candidat seulement si partagée dans sa conversation | `FilePolicy` |
| Finance / bénéficiaire | Parties pour leurs données utiles ; personnel financier ; références masquées | `FinancePolicy` + `Resources` masquants |
| Publication / audit | Modérateur ; consultation du journal et gestion des droits = habilitations **distinctes** | `StaffGrant` par capacité |
| Espace actif (client/freelance) | **N'accorde aucun droit** ; filtre l'affichage | Valeur de session lue par l'interface uniquement, jamais par les Policies |

Les listes privées sont toujours **bornées par relation** (`where client_id = auth` ou `freelancer_id = auth`), jamais « tout puis filtrer ». Les dossiers inaccessibles répondent par une erreur sobre sans révéler partie, montant ou fichier (CDC §4 ; `04` §0.3).

---

## 5. Interface : place de Blade, Livewire, Tailwind et Alpine.js

> **Pile validée par le porteur (D15).** Versions et contraintes en §8 (non approuvées, D17).

| Technologie | Rôle dans FreeCI | Limites posées |
|---|---|---|
| **Blade** | Rendu serveur des pages (SEO N09, première peinture rapide N05, formulaires qui fonctionnent sans JavaScript). Bibliothèque de composants anonymes `<x-fc.button>`, `<x-fc.field>`, `<x-fc.status-badge>`, `<x-fc.money>`, `<x-fc.empty-state>`… | Aucune requête ni règle dans les vues. |
| **Livewire** | Îlots interactifs à état serveur : filtres de recherche liés à l'URL, comparaison de propositions, onglet de messages (rafraîchissement périodique ≤ 30 s, CDC §11), suivi de statut d'un paiement « en vérification », files d'administration, indicateur de progression des fichiers. | (1) Un composant est un **mince adaptateur** : il appelle une Action. (2) Les identifiants et montants sont **verrouillés** côté serveur (propriétés non modifiables par le client ; mécanisme exact à confirmer dans la documentation Livewire 4) et chaque action **réautorise**. (3) Les téléversements volumineux (livraisons jusqu'à 100 Mo, ARB §14) **ne passent pas** par le mécanisme de téléversement temporaire par défaut sans validation de ses limites (Q13). |
| **Tailwind CSS** | Système de design par **jetons** (`@theme`), composants utilitaires, responsive mobile d'abord. Jetons définis en `03` §3–§6. | Pas de valeur de couleur ou d'espacement « libre » dans les vues ; seulement des jetons. |
| **Alpine.js** | Micro-interactions locales : tiroir de navigation, panneau de filtres, onglets, boîtes de confirmation, copie de référence. Fourni **par Livewire** (pas de second paquet). | Jamais seul vecteur d'une action métier : tout bouton « Livrer / Valider / Payer » est un formulaire qui fonctionne par requête HTTP ; rien ne dépend du survol. |

**Règles d'interface (PROP).**

1. Chaque action métier est un `POST` vers une route nommée qui appelle **une** Action ; Livewire ne fait qu'améliorer l'expérience.
2. Chaque écran a ses états **vide / chargement / erreur / succès / accès interdit** (`04` §0.3).
3. Les boutons « disponibles » sont **calculés** par une `Presenter` à partir du rôle et de l'état ; le serveur revérifie à l'exécution (ARB §10, F23).
4. Navigation d'une commande par **URL d'onglet** (partage, retour navigateur, reprise après connexion — ARB §1 « Retour après connexion »).
5. Les destinations de retour après connexion sont **internes et vérifiées** ; un brief saisi reste en brouillon privé, jamais dans l'URL (ARB §1).

---

## 6. Actions métier : contrats

Chaque action suit le même gabarit : **acteur/droit · entrées · préconditions · effets (même transaction) · erreurs · idempotence**. Erreurs communes (SRC ARC §11) : `401` session absente · `403`/`404` accès interdit (sans révéler l'existence) · `409` état ou version en conflit · `422` entrée invalide · `429` limite atteinte · `503` dépendance indisponible. Chaque réponse porte **code stable, message français, identifiant de corrélation**.

| Action (module) | Droit | Entrées principales | Préconditions | Effets dans la transaction | Idempotence | Réfs |
|---|---|---|---|---|---|---|
| `Accounts\Register` | Visiteur | nom d'affichage, courriel, mot de passe, rôles, acceptation conditions | Courriel unique ; conditions affichées | Compte, rôles, acceptation (version + date), courriel de vérification | Par courriel | F01 |
| `Accounts\VerifyEmail` | Compte | lien signé | Non expiré, **non déjà utilisé** | Adresse vérifiée ; reprise de l'action initiale | Réutilisation → message « déjà utilisé » | F02 |
| `Catalog\SubmitService` | Freelance auteur | champs du service | Profil publiable ; bornes (15–100 / 150–5 000 car., ≤ 10 compétences, 1–60 j) | Version `submitted`, historique, file de modération | `operationKey` | F09–F10 |
| `Catalog\PublishService` / `RequestServiceFix` / `SuspendService` | Modérateur | version, motif | Version soumise | Décision motivée, état, notification | Par décision | F10, F39 |
| `Orders\RequestService` | Client vérifié ≠ auteur | version de service, brief, fichiers | Service publié ; version courante ; pas son propre service | Commande `awaiting_acceptance`, **accord figé**, brief, conversation | `operationKey` | F12, F19–F20 |
| `Orders\AcceptServiceRequest` | Freelance de la commande | `expectedVersion` | État `awaiting_acceptance`, < 48 h | `accepted_at`, `payment_deadline_at` (+24 h), état `awaiting_payment` | Oui | F20 |
| `Missions\SubmitMission` | Client vérifié | champs complets | Champs requis valides ; pas de coordonnées privées dans le texte public | État `submitted` | Oui | F14–F15 |
| `Missions\SubmitProposal` | Freelance vérifié ≠ client | contenu, prix ferme, durée, validité | Mission ouverte ; une proposition active par couple | Proposition + **nouvelle version** (ancienne conservée) | Oui | F16 |
| `Missions\SelectProposal` | Client propriétaire | `proposalVersionId`, `expectedVersion`, `operationKey` | **Verrous** mission → proposition (ordre fixe) ; version courante et valide ; mission non réservée | Commande `awaiting_payment`, accord figé, copie des pièces autorisées, proposition « sélectionnée », mission « réservée », historique, outbox | `command_receipt` ; index `order_mission_live_uq` en dernier rempart | F17–F19 |
| `Finance\InitiatePayment` | Client de la commande | méthode **activée**, `operationKey` | État `awaiting_payment` ; aucun paiement `pending`/`unknown` ; montant lu dans l'accord | Opération `payment` `created` ; appel au prestataire **hors transaction longue** | Clé d'opération unique | F24–F25 |
| `Finance\ConfirmPayment` | Serveur (événement vérifié) | événement du prestataire | Événement authentifié ; **vérification serveur** de référence, montant, devise, commande ; état non régressif | Paiement `confirmed` **une fois**, lot de registre unique, mission attribuée, `awaiting_brief` ou `in_progress` (départ + échéance enregistrés **une fois**), historique, outbox | Clé de dédoublonnage ; `event_key` du lot | F21, F25, F28 |
| `Finance\RecordLatePayment` | Serveur | événement | Commande expirée/annulée ou paiement doublon | `reconciliation_case` ; **aucune** réouverture automatique | Oui | F27 |
| `Orders\CompleteBrief` | Client | réponses, pièces | État `awaiting_brief` ; fichiers `clean` | Version de complétude ; si complet : `in_progress` + dates | Oui | F21 |
| `Orders\DeliverOrder` | Freelance | message, description, fichiers `clean` | État `in_progress` ou `revision_requested` | Livraison **versionnée**, état `delivered`, notification, minuteur de silence (7 j) | `operationKey` | F29–F30 |
| `Orders\RequestCorrection` | Client | `deliveryId`, motif | État `delivered` ; **compteur** de corrections ; dernière livraison | Demande liée à la version, état `revision_requested` | Oui | F31 |
| `Orders\ValidateDelivery` | Client | `deliveryId`, `expectedVersion`, `operationKey` | État `delivered` ; aucun litige | `validated` puis `closed` ; **éligibilité** recalculée ; aucun versement direct | Oui | F32 |
| `Orders\RequestExtension` / `AnswerExtension` | Une partie / l'autre | jours, motif / réponse | Une seule demande en attente | Nouvelle échéance **seulement après acceptation** ; ancienne conservée | Oui | F22 |
| `Orders\OpenDispute` | Client ou freelance | motif, preuves | Commande payée non clôturée par reversement | Litige, **gel** du reversement non exécuté (même verrou que le reversement), notifications | Oui | F33–F34 |
| `Administration\ResolveDispute` | Support/administrateur habilité | décision, part retenue, remboursement | Authentification renforcée ; motif ; **double approbation au-delà du seuil** | Décision versionnée ; exécution financière suivie séparément | Empreinte d'approbation | F35, F26 |
| `Finance\InitiateRefund` | Habilité | décision | Plafond cumulé ≤ encaissement confirmé | Opération `refund` ; réservation, **pas** affiché « remboursé » avant confirmation | Clé d'opération | F27 |
| `Finance\InitiatePayout` | Tâche autorisée / habilité | commande | Validée, sans litige actif, bénéficiaire vérifié, fonds rapprochés, aucun reversement concurrent | Opération `payout` + événement worker, **atomique** | Clé unique ; résultat inconnu → rapprochement | F26 |
| `Finance\ReconcileUnknown` | Habilité / tâche | référence | Opération `unknown` | Recherche chez le prestataire **avant** toute nouvelle tentative | Oui | F25–F27 |
| `Files\IssueDownload` | Utilisateur autorisé | `fileId`, contexte | Fichier `clean` ; lien utilisateur–fichier–dossier | URL **courte et signée** ; jamais de clé de stockage permanente | — | F30, N13 |
| `Communication\SendMessage` | Participant | texte, pièces | Participant non bloqué ; fichiers autorisés | Message, non-lus, notification | Oui | F37 |
| `Orders\SubmitReview` | Partie d'une commande validée et clôturée | note, texte | Un avis par auteur et commande | Avis ; publication différée (2 dépôts ou 14 j) | Oui | F36 |
| `Administration\ChangePolicy` | Administrateur | nouvelle politique | Authentification renforcée ; motif | Nouvelle `policy_version` ; **sans effet** sur les accords existants | Oui | F42 |

**Actions complémentaires** (apparues lors de la conception des écrans, `04`) — même gabarit, mêmes erreurs :

| Action (module) | Droit | Effet principal | Réfs |
|---|---|---|---|
| `Accounts\PublishFreelancerProfile` | Freelance | Profil complet (titre, présentation, ville, ≥ 1 compétence) → visible | F04 |
| `Catalog\ToggleFavorite` | Utilisateur connecté | Favori privé (service ou profil), sans doublon | F13 |
| `Catalog\ArchiveService` | Modérateur / auteur | Service archivé ; commandes existantes inchangées | F10 |
| `Missions\SaveMissionDraft` | Client | Brouillon avec champs incomplets admis | F14 |
| `Missions\CloseMission` / `ReopenMission` | Client | Fermeture sans effacer l'historique ; réouverture après expiration de commande | F18 |
| `Missions\WithdrawProposal` | Freelance auteur | Proposition retirée avant sélection ; versions conservées | F16 |
| `Missions\PublishMission` / `RequestMissionFix` | Modérateur | Mission ouverte, ou renvoyée avec motif | F14, F39 |
| `Orders\SaveServiceRequestDraft` | Client | Brouillon privé du brief (jamais dans l'URL) | ARB §1 |
| `Orders\DeclineServiceRequest` / `WithdrawServiceRequest` | Freelance / client | Clôture avant paiement, sans encaissement | F20 |
| `Orders\CancelBeforePayment` | Client | Annulation immédiate si la demande est encore ouverte | F33 |
| `Orders\RequestCancellation` | Partie de la commande payée | Examen contradictoire ; reversement bloqué | F33 |
| `Communication\StartConversation` | Utilisateur connecté | Conversation privée liée à un service, une mission ou une commande | F13, F37 |
| `Files\UploadPrivateFile` | Utilisateur autorisé | `file_asset` en quarantaine, clé opaque | N13 |
| `Administration\ReportContent` | Utilisateur connecté | `report` | F37, F39 |
| `Administration\AssignCase` | Habilité | Affectation d'un dossier (responsable, priorité) | F41 |
| `Administration\ApproveSensitiveAction` | Approbateur **distinct** | Consomme une approbation liée à l'empreinte de l'action | CDC §12 |
| `Finance\RefreshPaymentStatus` | Client / serveur | Vérification auprès du prestataire (limitée) | F25 |

Lectures (requêtes) par écran : voir les « Contrats métier appelés » de `04`.

---

## 7. Intégrations (ports)

| Port | Contrat | Adaptateur V1 | Notes |
|---|---|---|---|
| `PaymentProvider` | `createCheckout`, `verify(reference)`, `parseEvent(request)` (authentifié), `refund`, `payout`, `lookup` | **`SandboxPaymentProvider`** : transactions **simulées, étiquetées** ; adaptateur réel seulement après qualification (Q03) | Le retour navigateur **n'a aucun effet financier** : il lit l'état en base (F25). Notifications reçues sur une route dédiée, sans CSRF, signature vérifiée, `provider_event` dédoublonné, puis `ConfirmPayment`. |
| `FileStorage` | disque privé, clés opaques, URL temporaires | Disque Laravel privé (local ou compatible S3) | Fichiers en quarantaine ; contenu jamais remplaçable derrière une clé déjà référencée (ARC §12). |
| `FileScanner` | analyse hors requête ; « indisponible » ⇒ reste bloqué | À choisir | N13 : taille, type réel, analyse, limites après décompression. |
| `Mailer` | courriel minimal + lien vers dossier autorisé | À choisir | Aucune donnée de contact ou de paiement dans l'objet ou le courriel (CDC §11). |
| `IdentityProvider` | — | **Aucun** (D07) | Authentification locale ; pas de fédération. |

---

## 8. Adaptation Laravel / PostgreSQL et versions

### 8.1 Versions constatées le 2026-10-05 (sources : API Packagist, registre npm) — **constats, non approuvés (D17)**

| Composant | Dernière version stable constatée | Contrainte relevée |
|---|---|---|
| `laravel/framework` | **13.34.0** (29 sept. 2026) | PHP `^8.3` |
| `livewire/livewire` | **4.4.7** (28 sept. 2026) | PHP `^8.1` ; `illuminate/*` `^10 … ^13` |
| `laravel/fortify` | 1.40.0 | PHP `^8.2` (option d'authentification, Q09) |
| `tailwindcss` / `@tailwindcss/vite` | **4.3.3** | `@tailwindcss/vite` : Vite `^5.2 … ^8` |
| `vite` | **8.3.2** | Node `^20.19.0 \|\| >=22.12.0` |
| `alpinejs` (npm) | 3.17.4 | **non nécessaire** : Alpine est embarqué par Livewire |
| Environnement d'observation | PHP 8.3.6, PostgreSQL 16.14, Node 22.22 | Constat de l'environnement de conception, **pas** une décision d'hébergement |

**Non vérifié** (sites inaccessibles depuis cet environnement) : cycles de support de PHP 8.3/8.4, de Laravel 13 et des versions majeures de PostgreSQL ; compatibilité fine Livewire 4 ↔ Laravel 13 au-delà des contraintes déclarées. **À faire avant installation (D17)** : lire les pages officielles de support, **vérifier la compatibilité** de chaque version (Laravel ↔ Livewire ↔ Tailwind/Vite ↔ PHP ↔ PostgreSQL), fixer les versions de production, figer par fichiers de verrouillage, puis les faire **approuver par le porteur**.

### 8.2 Points d'adaptation

| Sujet | Décision PROP |
|---|---|
| **Authentification** | Sessions Laravel locales. Vérification d'adresse : le lien signé de Laravel expire mais n'est pas strictement « à usage unique » ; **F02/T01 exigent le refus d'un lien réutilisé** → comportement dédié (message « déjà utilisé »). Récupération et connexion : **messages génériques** qui ne révèlent pas l'existence d'un compte (F02). Limitation des essais (N11). Autres sessions révocables (F03). **MFA obligatoire** pour le personnel. Fortify ou kit officiel : Q09. |
| **Rôles / espace actif** | Rôles commerciaux = données ; espace actif = valeur de session d'affichage (sans effet sur les droits). Personnel = `staff_grant` (auteur, motif, échéance, révocation). |
| **Concurrence** | `DB::transaction` + verrouillage `FOR UPDATE` dans un **ordre fixe** (mission → proposition → commande → opérations) ; réessai borné sur interblocage avec la **même** clé d'opération ; index uniques en dernier rempart (ARC §8). |
| **Idempotence** | `command_receipt` (acteur + action + clé + empreinte de la requête) écrit dans la même transaction que l'effet. |
| **Outbox et tâches** | Chaque transaction écrit `outbox_event` ; un répartiteur alimente la file Laravel. Pilote de file : **base PostgreSQL** pour démarrer (D16), sans dépendance Redis ; migration possible vers Redis derrière le même répartiteur. Consommateurs idempotents par `event_key`. Garanties détaillées en **§8.3**. |
| **Recherche** | Recherche plein texte PostgreSQL (configuration française, accents) avec index adaptés, pagination ≤ 20 (F11) ; mesurée contre N06 (10 000 comptes, 5 000 services, 1 000 missions). Aucun moteur externe en V1. |
| **Fichiers** | Disque privé ; contrôle du type **réel** ; quarantaine ; URL temporaires ; quotas 100 Mo / 500 Mo (propositions) ; Q13 pour les gros fichiers. |
| **Cache** | Pages privées : `Cache-Control: private, no-store` ; aucun cache partagé de pages personnalisées (N09). |
| **Qualité** | Pint ; analyse statique ; tests de politiques par ressource (T16/N10) ; tests de concurrence avec deux sessions PostgreSQL (ARC §14) ; tests de rendu des composants. Outils précis à choisir à l'amorçage. |

### 8.3 File de tâches sur PostgreSQL : concurrence, reprises, doubles effets financiers (D16)

La file sur PostgreSQL est **acceptée pour démarrer** à la condition que ce qui suit soit **appliqué et testé**. Les comportements exacts du pilote de file Laravel (verrouillage à la prise d'une tâche, délai de reprise, nombre d'essais) sont **à vérifier dans la version installée** avant de s'y fier.

**Principe.** `outbox_event` est la **source de vérité** : il est écrit **dans la même transaction** que l'effet métier. Un répartiteur le transforme en tâche **après validation de la transaction**. La livraison est **au moins une fois** : *tout consommateur doit donc être idempotent*.

| Sujet | Règle |
|---|---|
| **Concurrence entre workers** | Une tâche n'est prise que par **un** worker à la fois (verrouillage de ligne de type « sauter les lignes verrouillées ») ; plusieurs workers autorisés. Le **traitement** d'un événement ne repose jamais sur ce verrou seul : il **revérifie** l'état métier sous verrou `FOR UPDATE` dans l'**ordre fixe** de §8.2 (mission → proposition → commande → opérations). |
| **Reprises** | Le délai de reprise d'une tâche prise mais non terminée est **supérieur à sa durée maximale** ; un **délai d'expiration** par tâche est plus court que ce délai. Nombre d'essais et **attente croissante** définis par type de tâche ; échecs définitifs conservés et **alertés**. Arrêt brutal d'un worker : la tâche redevient disponible et est rejouée **sans effet en double**. |
| **Idempotence** | Chaque événement porte un `event_key` unique ; le consommateur **ne refait pas** un effet déjà enregistré (reçu de commande, clé d'opération, `event_key` du lot du registre). |
| **Appels sortants financiers** | (1) La **référence sortante** et la **clé d'opération** sont **générées et enregistrées avant** l'appel au prestataire. (2) L'opération passe `created → pending` par une **mise à jour conditionnelle** (`WHERE état = 'created'`) : seule la tâche qui l'obtient (1 ligne modifiée) a le droit d'appeler. (3) L'appel a lieu **hors transaction longue**. (4) Les tâches d'écriture chez le prestataire ne sont **pas rejouées automatiquement** : un délai dépassé ou une réponse illisible laisse l'opération en `unknown` ; la reprise passe par la **recherche par référence** (`lookup`) et le rapprochement, **jamais** par un nouvel envoi à l'aveugle. (5) Si le prestataire accepte l'idempotence par référence, elle est utilisée **en plus**, pas à la place. |
| **Pas de double versement** | **Un seul reversement actif ou confirmé par commande** (index unique partiel) ; même verrou de commande que l'ouverture d'un litige ; éligibilité recalculée à la **prise** de la tâche, pas seulement à sa création. Un envoi déjà accepté par le prestataire est suivi comme incident, jamais « annulé ». |
| **Pas de double confirmation** | Notification du prestataire **dédoublonnée** (`provider_event`), vérifiée côté serveur, **état non régressif** ; un seul lot de registre par `event_key`. |
| **Panne après acceptation, avant écriture** | Cas prévu : le prestataire a agi, FreeCI n'a pas enregistré le résultat → l'opération reste `pending`/`unknown` → **rapprochement** par la référence enregistrée avant l'appel. |
| **Limites connues** | Latence d'interrogation de la file ; **croissance** des tables de tâches (purge des tâches terminées à prévoir) ; **pool de connexions** partagé entre web et workers ; nécessité de **surveiller le retard** (âge de la plus ancienne tâche) et les échecs. Si ces limites se confirment à la recette, passage à Redis **sans changer** le contrat des tâches. |
| **Essais exigés** | Deux workers sur la même tâche ; **arrêt forcé** d'un worker au milieu d'un reversement ; rejeu d'une notification ; expiration de commande pendant un paiement `pending` ; demande de litige et reversement simultanés ; réponse du prestataire perdue. Résultat attendu : **un seul effet financier**, état explicable, rapprochement possible (T08, T09, T12, T13). |

---

## 9. Sécurité, exploitation et lots (SRC N07–N18, ARC §13, §15)

### 9.1 Exploitation

- Deux processus (web, tâches) + planificateur : échéances commerciales (48 h, 24 h), silence après livraison (7 j), publication des avis (2 dépôts ou 14 j), rapprochement quotidien (F28), alertes.
- Environnements développement / recette / production **séparés** (bases, stockages, clés, intégrations). Migrations exécutées par un compte distinct de celui du serveur. Essai sur base vide puis sur copie de recette (ARC §13).
- Sauvegardes chiffrées quotidiennes, 30 jours, accès distinct ; perte max 24 h, reprise < 8 h ; **restauration vérifiée avant lancement** (N08).
- Journaux sans mot de passe, pièce d'identité ni donnée de carte (N14).

### 9.2 Ordre de développement proposé (reprend ARC §15, adapté ; **non engagé**)

| Lot | Contenu | Vérification d'entrée en recette |
|---|---|---|
| 0 | Socle : dépôt, jetons de design, composants `fc`, comptes à deux rôles, politiques, audit, environnements | T01, accès privés, test d'architecture |
| 1 | Publications : profils, services versionnés, missions, modération, recherche publique | T02–T04 |
| 2 | Accords et réalisation : propositions, sélection, commande, brief, messages, livraison, corrections, report, avis | T05, T06, T10, T11, T15 |
| 3 | Finance et exploitation sur **paiement simulé** : registre, rapprochement, remboursement, reversement, support, litiges | T07–T09, T12–T14 |
| 4 | Adaptateur du prestataire qualifié, recette de bout en bout, restauration, pilote | T16–T18 + conditions de lancement (CDC §18) |

### 9.3 Risques techniques identifiés

| Risque | Parade proposée |
|---|---|
| Livewire v4 peu documenté dans la référence ; téléversements > 100 Mo | Prototype de téléversement et de composant de comparaison avant le lot 1 ; chemin dédié pour gros fichiers (Q13). |
| Règles métier dispersées dans l'interface | Test d'architecture + revue d'Action par fonctionnalité. |
| Résultat financier inconnu → double envoi | Port `PaymentProvider` avec `lookup` ; aucune nouvelle tentative avant rapprochement (F26, F27). |
| Garde-fous SQL (contraintes, déclencheurs, index partiels) oubliés lors de la rédaction des migrations | Tests de contraintes sur PostgreSQL réel (ARC §14), pas sur SQLite. |
| Dérive de nommage (singulier/pluriel, FR/EN) | Conventions Laravel (D16, §4.1) ; revue du modèle avant la première migration. |
| File PostgreSQL : double effet financier, reprise après arrêt d'un worker, retard de traitement | Garanties et essais de §8.3 ; surveillance du retard de file ; migration possible vers Redis. |
