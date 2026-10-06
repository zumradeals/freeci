# 01 — Cadrage de FreeCI

> **Statut : base de travail acceptée par le porteur (revue du 2026-10-05).** Les écrans restent à présenter en maquettes haute fidélité et à examiner visuellement avant tout développement ; les règles métier sont portées par le serveur. **Publication** dans le dépôt public autorisée par le porteur (D19), hors secrets, identifiants, données personnelles réelles et pièces client originales. **Base acceptée ≠ tous les détails approuvés** : seules les décisions **DEC** (D01–D22) sont validées ; le reste est **proposition (PROP)** ou **question (Q)**, révisable après rendu.
> Ce dossier cite les documents client comme **bases de réflexion** ; les pièces originales ne sont pas versionnées. Dépôt `zumradeals/freeci` public ; visibilité inchangée.

## 0. Conventions de lecture

Chaque conclusion porte une étiquette d'origine.

| Étiquette | Sens |
|---|---|
| **SRC** | Exigence issue d'une source, avec son repère (F01–F42, N01–N18, P/A/C/CL/FR/AD, section). |
| **DEC** | Décision explicite du porteur, donnée dans l'instruction de mission (INS). |
| **PROP** | Proposition de conception de ce dossier, révisable. |
| **Q** | Question restant à trancher. Les **Q bloquantes** sont listées en `05` §5. |

Abréviations de sources : **CDC** = Cahier des charges v1.0 (2 oct. 2026) ; **ARB** = Arborescence et parcours v1.0 (2 oct. 2026) ; **ARC** = Architecture et base de données v1.0 (3 oct. 2026) ; **IMG-1** accueil, **IMG-2** espace client, **IMG-3** espace freelance, **IMG-4** administration ; **INS** = instruction de mission du porteur ; **REF** = dépôt `dgafrique-core` examiné comme référence.

## 1. Ce qui a été examiné, et ce qui est réellement disponible

| Élément | Annoncé par | Disponible ? | Statut de vérification |
|---|---|---|---|
| CDC, ARB, ARC (.docx) | INS | Oui, lus intégralement | Lus ; texte extrait sans perte apparente (tableaux inclus). Mise en page non examinée. |
| 4 captures (accueil, client, freelance, admin) | INS | Oui | Références visuelles **partielles** : une seule largeur (≈ 950–1060 px), aucun état mobile, aucun état d'erreur, un seul écran par espace. |
| « Maquettes principales validées le 3 octobre » | ARC §16 | **N'existent pas** (confirmé par le porteur, D13) | Mention de ARC **sans objet** : on part de zéro. Les 4 captures sont des références visuelles partielles. |
| « SQL de 55 tables » | ARC « Livrables », §15 | **N'existe pas** (confirmé par le porteur, D13) | Mention de ARC **sans objet**. Les noms de tables énumérés en prose dans ARC §3–§6 sont une **base de réflexion** ; le nombre 55 n'est **pas un objectif** (D14). |
| « 46 gabarits » | ARB intro | Oui, dans ARB §2–§6 | **Vérifié par décompte** : P01–P10 (10), A01–A05 (5), C01–C08 (8), CL01–CL05 (5), FR01–FR08 (8), AD01–AD10 (10) = **46**. |
| `zumradeals/dgafrique-core` (référence) | INS | Oui, lecture seule, clone superficiel de la branche par défaut (791 fichiers) | Examiné : `composer.json`, `package.json`, `AGENTS.md`, ADR-001, structure `app/`, un service de registre, un client de paiement, un contrôleur, les composants Blade, le test de fondation. Analyse en `02` §2. |
| Dépôt `zumradeals/freeci` | INS | Oui | `README.md` seul (commit `727c834`). Branche par défaut `main` = `origin/main` = `727c834`. Aucun travail existant à préserver au-delà de ce README. Dépôt **public**. |
| Documentation fournisseur de paiement | CDC §9 | **Non consultée** | Hors périmètre ; le prestataire n'est pas qualifié. |
| Versions des outils | INS | Partiellement | Versions de paquets vérifiées auprès de Packagist et npm le 2026-10-05 (voir `02` §8). Cycles de support PHP/PostgreSQL/Laravel **non vérifiés** (sites inaccessibles depuis l'environnement). |

**Conséquence pour la suite (D13, D14).** On part de zéro pour la réalisation. Les trois documents sont des **bases de réflexion**, les quatre captures des **références visuelles partielles**. Les écrans sont conçus à partir de ARB + CDC + captures, puis seront présentés en maquettes haute fidélité ; le **modèle de données est conçu à partir des besoins et des invariants métier** (`02` §4) ; les **migrations Laravel seront produites à l'implémentation**. Aucun SQL ni maquette validée n'est attendu du client.

## 2. Compréhension de FreeCI

**FreeCI** est une marketplace de prestations indépendantes destinée d'abord à la Côte d'Ivoire (français, prix en FCFA, devise technique XOF). Elle permet de **trouver une compétence, conclure un accord clair, suivre la prestation et comprendre sa situation financière** (DEC). Les freelances vendent des prestations ; les clients publient des missions et choisissent une proposition ; la plateforme suit le besoin jusqu'à la livraison, la validation et le reversement (CDC objet).

Deux entrées, un seul dossier (DEC ; CDC §2, ARB §1, §11, §12) :

1. **Service publié** : découverte → brief → acceptation du freelance → paiement.
2. **Mission publiée** : besoin → propositions → sélection d'une version → paiement.

Les deux aboutissent à une même **commande** (`C05`). La réalisation commence **uniquement après paiement confirmé côté serveur et brief complet** (F21), puis : livraison formelle (F29), corrections (F31), validation explicite (F32), suivi du reversement (F26).

### 2.1 Invariants préservés (DEC + SRC)

| Invariant | Origine |
|---|---|
| Un compte peut cumuler client et freelance ; le changement d'espace ne change que l'affichage et jamais les droits | DEC ; CDC §3, ARB §1 |
| Un seul attributaire par mission en V1 ; commande à prix fixe | DEC ; CDC §1, F17 |
| Accord commercial figé (instantané) ; livraisons versionnées | DEC ; F19, F29, ARC §2 |
| États distincts : commande, paiement, remboursement, reversement | DEC ; CDC §8 fin, §9 « États financiers » |
| Aucun reversement automatique fondé sur le silence du client | DEC ; F32, F22 |
| Fichiers privés, autorisations par dossier, décisions traçables | DEC ; F30, N10, N14, F23 |
| Confirmation de paiement côté serveur uniquement | DEC ; F25 |
| Valeurs commerciales (commission 10 %, 48 h / 24 h / 7 j / 14 j, bornes 5 000–500 000 FCFA, quotas fichiers, seuil de double validation) = **propositions à confirmer**, configurables, sans effet sur les commandes conclues | DEC ; CDC §Paramètres, §18 |
| Prestataire de paiement **non qualifié** ; paiement simulé jusque-là, clairement identifié | DEC ; CDC §9 |

### 2.2 Acteurs et rôles (SRC CDC §3, ARB §1, §6)

| Acteur | Peut | Ne peut pas |
|---|---|---|
| Visiteur | Consulter services, profils, missions publiques | Contacter, proposer, commander |
| Client | Publier une mission, comparer, payer, valider | Voir les propositions ou commandes d'autrui |
| Freelance | Publier des services, proposer, livrer | Acheter son service, répondre à sa mission |
| Modérateur | Examiner contenus et signalements, suspendre | Rembourser, reverser |
| Support | Suivre les dossiers qui lui sont confiés | Modifier des écritures ; accès limité et journalisé |
| Administrateur / finance | Paramètres, litiges, opérations financières | Agir sans authentification renforcée, motif et journal |

**Q** : le CDC liste Visiteur / Client / Freelance / Modérateur / Administrateur (§3) mais ARB §6 distingue aussi Support et « habilitation financière » ; ARC prévoit des habilitations (`staff_grant`) sans en nommer le catalogue. Le catalogue exact d'habilitations est à fixer (Q07).

### 2.3 Rattachement à GAMAD

Les sources ne rattachent pas FreeCI à GAMAD. Conformément à INS : FreeCI **conserve ses propres données et responsabilités**, ne copie pas les registres multiples de GAMAD Core, et **n'introduit aucune dépendance** à l'identité, à la fédération ou au prestataire de paiement de GAMAD sans décision explicite. FreeCI est donc traité ici comme **projet distinct, potentiellement intégrable** — pas comme composant officiel de GAMAD. Toute déclaration d'appartenance relève du porteur (Q10).

## 3. Périmètre de la V1 (SRC CDC §2, F01–F42)

La V1 doit permettre **une transaction complète, de la recherche à la clôture**. Une démonstration seule ne vaut pas validation (CDC §2).

| Domaine | Inclus en V1 | Exclu / reporté |
|---|---|---|
| Comptes | Inscription, connexion, récupération, rôles cumulables, MFA pour le personnel | Connexion sociale, organisations multi-membres |
| Profils | Compétences, ville, portfolio, disponibilité, statut de vérification | Vérification approfondie des qualifications |
| Catalogue | Publication, modération, recherche, favoris* | Offres multiples, options payantes |
| Missions | Publication, propositions versionnées, sélection d'un freelance | Attribution à plusieurs prestataires, lots, jalons, acomptes |
| Accord | Prix fixe, périmètre figé, délai, corrections | Avenants payants, jalons |
| Commandes | Brief, historique, échéance, livraison, validation, report | Contrat à l'heure, suivi de temps |
| Communication | Messagerie liée au besoin ou à la commande | Appels audio/vidéo, présence, saisie en cours |
| Paiements | Encaissement, remboursement, reversement suivis, registre, rapprochement | Plusieurs prestataires et devises |
| Confiance | Avis liés aux commandes, signalements, litiges | Badges avancés, classement avancé |
| Administration | Modération, support, transactions, paramètres, journal | Gestion commerciale avancée |
| Notifications | Site + courriels essentiels | SMS, notifications mobiles, campagnes |
| Exploitation | Sauvegardes, alertes, journaux, export financier | Application mobile native |

\* Favoris (F13, C08) et préférences de notification peuvent être reportés si le budget l'impose (CDC §2). **Ne jamais retirer** : contrôles d'accès, validation des paiements, litiges, traçabilité (CDC §2).

**Exclusions fermes de la V1** (CDC §1) : recrutement salarié, contrats à l'heure, équipes de plusieurs prestataires, marchés à plusieurs lots. Les professions réglementées exigent qualifications et responsabilités explicites (CDC §1, §6 exemples).

**Condition de lancement réel** (CDC §2, §18) : un prestataire acceptant le flux « encaissement → reversement après validation » et un contrat écrit. Sinon : démonstration privée à paiement simulé ; un lancement sans reversement différé exigerait une nouvelle décision et de nouveaux écrans.

## 4. Décisions explicites du porteur (INS)

| N° | Décision | Effet sur la conception |
|---|---|---|
| **D01** | Le produit s'appelle **FreeCI** | Remplace « Freelance CI » (captures), « Marketplace Freelance CI » (titres des documents), « nom neutre » (CDC §18). Le nom des fichiers sources reste inchangé. |
| **D02** | PHP + **Laravel** pour l'application et les règles métier | Remplace Next.js (voir C01). |
| **D03** | **PostgreSQL** pour les données | Confirme CDC §14 et ARC. |
| **D04** | Organisation **modulaire** : interface / actions métier / intégrations | Structure en `02` §3. |
| **D05** | `dgafrique-core` est une **référence à examiner**, pas un modèle à copier | `02` §2 : ce qui est repris, adapté, écarté. |
| **D06** | Le dossier client présentant **Next.js comme retenu est dépassé sur ce choix** | Exigences métier utiles conservées (C01). |
| **D07** | FreeCI a ses propres données ; aucune dépendance à l'identité, à la fédération ou au paiement de GAMAD sans décision explicite | Authentification locale ; port de paiement agnostique. |
| **D08** | Priorité produit : beauté, intuitivité, responsivité — traduites en choix vérifiables | `03`, `04`. |
| **D09** | Direction visuelle : conserver bleu marine, fonds clairs, cartes sobres, séparation des espaces ; améliorer identité, typographie, contraste, espacements, cohérence | `03`. |
| **D10** | Mobile d'abord ; 360 / 390 / 768 / 1024 / 1440 px ; cible tactile 44 × 44 px ; WCAG AA ; zoom 200 % | `04` §0. |
| **D11** | Pas de développement applicatif, de migration, d'installation de dépendances, de déploiement, de fusion automatique, ni d'activation de paiement à cette étape | Respecté : seuls des fichiers Markdown sont produits. |
| **D12** | *(supplantée par D19)* Dépôt public : documents client et dérivés en local tant que non autorisés | Levée par D19. |
| **D13** | Départ de zéro : **maquettes validées et SQL n'existent pas** ; trois documents = bases de réflexion ; quatre captures = références visuelles partielles | Clôt Q01, Q02 ; `01` §1, C03, C04. |
| **D14** | Modèle de données **conçu depuis les besoins et invariants métier** ; 55 tables **non** objectif ; migrations produites à l'implémentation | `02` §4. |
| **D15** | Pile **validée** : PHP/Laravel, PostgreSQL, Blade + Livewire + Tailwind + Alpine, monolithe modulaire (présentation / actions métier / intégrations) ; **autonomie vis-à-vis de GAMAD** | Clôt Q08 ; `02` §1, §5. |
| **D16** | **Conventions Laravel** pour les noms de tables ; **file de tâches sur PostgreSQL acceptable** pour démarrer, **sous réserve de documenter** concurrence, reprises et prévention des doubles effets financiers | Clôt Q11 (en partie) ; `02` §4.1, §8.3. |
| **D17** | **Versions exactes à vérifier** (compatibilité, support) avant installation ; **aucune version citée dans ces documents n'est approuvée** | `02` §8.1. |
| **D18** | **Direction visuelle validée** : marine et fonds clairs, orange en accent, actions prioritaires avant compteurs, administration distincte ; insistance sur beauté, intuitivité, responsivité, identité reconnaissable ; documents acceptés comme base de travail ; écrans à présenter et examiner visuellement ; règles métier côté serveur | `03`, `04`. |
| **D20** | **Format des maquettes (Q19)** : prototype statique haute fidélité, navigable et responsive, dans `design/prototype-v01/` (HTML, CSS, JavaScript de présentation minimal ; aucune dépendance ni framework ; **pas** le socle Laravel) | `design/prototype-v01/README.md`. |
| **D21** | **Versions, UUID, modèle détaillé de données** : traités avant migrations et développement ; **ne bloquent pas V01** | `05` §5.2. |
| **D22** | Les choix graphiques des documents (signatures, proportions, tokens) **restent ajustables après rendu** ; toute adaptation est **justifiée** par lisibilité, cohérence ou usage ; une règle décorative n'est pas une contrainte absolue | `03` §2.4–§2.6. |
| **D23** | **V01 retenu comme base de travail** ; révision ciblée **V01.1** sans refonte. **Conservés** : Inter, palette marine et claire, accent orange, trame discrète de l'accueil, carte de suivi comme explication du fonctionnement. Le monogramme et « FreeCI » sont une **identité de travail**, **non** un logo définitivement validé par le client | `03` §2.3, §2.7 ; `design/prototype-v01/` |
| **D24** | **Mobile allégé** : moins de répétitions, détails secondaires dépliables **sans réduire** la lisibilité des prix, délais, livrables, corrections, actions attendues et conséquences. Tableau de bord : actions, commandes, autres informations, **chiffres en dernier**. **Échéance d'action du client ≠ fin des candidatures**. Illustrations de prestations variées, **aucun** témoignage, note ni preuve de confiance | `04` §12 |
| **D25** | **Examen d'une livraison** : l'action principale mène aux **fichiers** de la dernière livraison ; « Valider » et « Demander une correction » ont le **même poids visuel**, avec leurs conséquences ; **aucune règle n'oblige à télécharger** pour valider ; versions précédentes repliées ; **contrôle de sécurité ≠ qualité du travail**. **Espace privé simplifié** (en-tête et pied allégés ; priorité commandes, missions, messages, compte, aide ; accès au catalogue). **Vérification de l'e-mail discrète**, jamais une certification | `04` §8.7, §12 |
| **D26** | **V01.1 = référence visuelle pour démarrer l'implémentation** (acceptation de travail : ni validation définitive du client, ni certification des comportements). Conservés : **trame de points** de l'accueil, **monogramme + FreeCI** (identité de travail), onglets actuels, colonne latérale **à partir de 1280 px** avec navigation accessible en dessous | Lot 1 ; `06` |
| **D27** | **Classement des actions par urgence réelle** : aucune position figée ; une action sans échéance ne précède pas automatiquement une livraison à examiner | `04` §6.2 |
| **D28** | **Examen de livraison** : ordre « Demander une correction » puis « Valider la livraison », avec confirmation et conséquences explicites | `04` §8.7 |
| **D29** | **Dépliants secondaires** repliés sur mobile, ouverts sur ordinateur ; les informations essentielles à une décision restent immédiatement accessibles | `03` §6.10 |
| **D30** | **Premier lot fonctionnel** : socle Laravel/PostgreSQL, accueil, catalogue (recherche, catégorie, pagination), fiche de service, inscription/connexion/déconnexion/récupération, espace client à l'état vide, données fictives reproductibles | `06` |
| **D31** | **Conventions techniques du lot 1** (UUID v7, schéma `public`, versions verrouillées, organisation des modules) : décisions internes réversibles, **sans nouvelle confirmation** | `06` |
| **D32** | **Déploiement sur VPS** `https://freeci.dgafrique.com` (sous-domaine **temporaire**), réalisé **par le porteur** ; FreeCI **autonome** de DG Afrique ; domaine **configurable** ; VPS **non supposé vierge** (autres sites préservés) ; catalogue public sans restriction globale ; aucun paiement réel ; données fictives optionnelles et identifiées | `07` ; `LIVRAISONS` |
| **D33** | **Chaque lot terminé et vérifié est poussé sur `main`, sans push forcé**, avec : SHA à déployer, nouvelles variables d'environnement, migrations à appliquer | `LIVRAISONS` |
| **D34** | **Administrateur désigné par le porteur depuis la console du serveur** (jamais par l'inscription) ; habilitation datée, motivée, révocable ; un compte administrateur reçoit aussi les rôles client et freelance ; **aucune fonction d'administration réelle ne s'ouvre avant : MFA, vérification d'adresse e-mail et courrier réel** (`02` §8.2, `01` C10) | `06` §6 ; `LIVRAISONS` |
| **D35** | **Lot 2 : brief textuel** (pas de fichiers tant que la chaîne de protection n'existe pas) ; une demande acceptée **ne démarre pas** sans paiement confirmé ni brief complet ; délais de réponse (48 h) et de paiement (24 h) **figés dans l'accord** | `09` |
| **D36** | **L'administrateur n'a aucun accès implicite aux commandes** ; l'administration métier garde ses exigences d'ouverture (MFA, vérification d'adresse, courrier réel, accès support journalisé) | `09` §4 |
| **D37** | **Genius Pay est le prestataire de paiement retenu** (décision du porteur ; sa documentation est disponible). Son **bac à sable sera intégré dans un lot dédié**, derrière le port `PaymentProvider` existant. **Avant cette intégration : aucun prestataire réel n'est branché, et le simulateur conserve toutes ses restrictions** (désactivé par défaut, commandes et comptes de démonstration autorisés seulement, aucun bouton public ni paramètre qui confirme un paiement, confirmation uniquement vérifiée côté serveur). Cette décision ne vaut pas qualification du prestataire pour la production : les phrases sur le paiement protégé restent soumises à cette qualification (docs/04 §2.6) | Porteur ; lot 6 |
| **D38** | **Genius Pay est l'unique passerelle de paiement de FreeCI, avec deux modes configurables : `sandbox` et `live`** (précision du porteur, lot 10.1). Le simulateur interne est retiré. Fournisseur, environnement et autorisation de créer de nouveaux paiements sont trois réglages distincts, modifiés **explicitement** dans la configuration serveur, sans bascule automatique. Le sandbox est ouvert à tout compte inscrit ; la séparation test / réel porte sur les **commandes et transactions** (`orders.environment`, fixé à la création, immuable), pas sur les personnes. Le live est développé mais **non activé** : son activation exige l'autorisation explicite du porteur et les confirmations listées dans `docs/18` §7. Le remboursement Genius Pay (`POST /payments/{reference}/refund`) est documenté et reste réservé au lot financier | Porteur ; lot 10.1 |
| **D19** | **Publication autorisée** des cinq documents et dérivés dans le dépôt public ; visibilité du dépôt inchangée ; **exclus** : secrets, identifiants, données personnelles réelles, pièces client originales | Clôt Q16. |

**Pile d'interface validée (D15).** Blade/Livewire, Tailwind et Alpine.js : leur place est expliquée en `02` §5. Les versions restent à vérifier avant installation (D17).

## 5. Contradictions, écarts et points de vigilance entre sources

Sévérité : **B** = bloque une décision, **M** = à corriger dans la conception, **i** = information.

| N° | Constat | Sources | Traitement | Sév. |
|---|---|---|---|---|
| C01 | Next.js/React/Route Handlers et authentification « Next.js » sont retenus par CDC §14 et ARC §1, §11, §12, réf. [1][5] ; INS impose Laravel. | CDC §14 ; ARC | **DEC prime.** On conserve : monolithe modulaire, PostgreSQL, worker + outbox, transactions locales, contrats d'actions, séparation public/privé/financier. On retire : App Router, Route Handlers, `/api/v1` comme architecture de pages. | M |
| C02 | Le nom « Freelance CI » apparaît dans les captures ; CDC §18 propose « nom neutre jusqu'au choix de la marque ». | IMG-1..4 ; CDC | D01 : **FreeCI**. Logo « f. » à redessiner (`03` §2). | i |
| C03 | ARC §16 affirme « maquettes principales validées le 3 octobre 2026 » ; ARB §15 et CDC N04 disent que l'identité visuelle et la charte restent à valider ; seules 4 captures existent. | ARC ; ARB ; CDC | **Résolu (D13)** : aucune maquette validée n'existe ; les captures sont des références partielles et les écrans seront présentés puis examinés visuellement. | i |
| C04 | « SQL de 55 tables » inexistant. 55 noms de tables sont listés en prose, avec des **besoins non couverts** : table de signalements (F37, F39, AD04), décision de modération avec motif (F10), préférences de notification (F38), réponse à un avis (F36), preuves de litige (F34), annulation après paiement (F33). | ARC §3–§6 ; CDC | **Résolu (D14)** : il n'y a pas de SQL ; ces absences sont des **besoins à couvrir** par le modèle conçu en `02` §4. | i |
| C05 | ARC §3 : identité déléguée (`auth_issuer`, `auth_subject`, fournisseur « maintenu ou délégué »). D07 interdit la dépendance à l'identité GAMAD. | ARC ; INS | Authentification locale Laravel. Colonnes de fédération **non reprises** en V1 (Q09). | M |
| C06 | ARB utilise des routes de style Next.js (`/services/[slug]`) ; ARC des chemins `/api/v1/...`. | ARB ; ARC | Routes web Laravel nommées (`/services/{slug}`) ; les « contrats d'API » deviennent des **actions métier** ; JSON réservé aux notifications du prestataire et au rafraîchissement ciblé. | M |
| C07 | **Menus des captures ≠ ARB §1.** Client : captures = Vue d'ensemble, Mes missions, Commandes, Paiements, Messages, Favoris (ARB : Mes missions / Commandes / Paiements / Favoris + messages communs). Freelance : captures = Vue d'ensemble, Mes services, Propositions, Commandes, Reversements, Messages, **Profil public** (ARB : Profil / Services / Propositions / Commandes / Reversements / **Bénéficiaire**). Admin : captures = 4 entrées (Publications, Commandes, Transactions, Support) ; ARB §6 = 10 écrans AD01–AD10. | IMG-2..4 ; ARB §1, §5, §6 | Les captures sont une **démonstration réduite**. Menus complets proposés en `04` §1. « Bénéficiaire » (FR08) est **absent des captures** alors qu'il conditionne le reversement : à rendre visible. | M |
| C08 | Les captures client/freelance affichent **trois compteurs avant** les actions attendues ; INS impose « actions et échéances avant les compteurs ». ARB §4 va déjà dans ce sens. | IMG-2, IMG-3 ; INS ; ARB §4 | Inversion en `04` §6–§7. | M |
| C09 | **Données de démonstration incohérentes** : IMG-3 (compte freelance « AK ») affiche, comme autre partie de la commande DEMO-26018, « Aminata Koné » — c'est-à-dire elle-même ; IMG-2 (client « AM ») affiche aussi « Aminata Koné » comme autre partie, ce qui est correct pour le client. | IMG-2, IMG-3 | Le freelance doit voir le **client** comme autre partie. À corriger dans tout jeu de démonstration. | i |
| C10 | IMG-4 (admin) conserve l'en-tête public (Services / Missions / Freelances / Fonctionnement) et un sélecteur de rôle « du haut » ; l'encart précise qu'il ne change que la vue de démonstration. ARB §6 : menu **distinct** ; INS : changement client/freelance **distinct** de l'accès administratif. | IMG-4 ; ARB §6 ; INS | En production : coque d'administration séparée, sans sélecteur de rôle, accès par habilitation et MFA. Le sélecteur n'existe qu'en mode démonstration identifié. | M |
| C11 | Le badge « Livrée » est teinté **orange** (IMG-2/3) alors que l'orange est aussi l'accent décoratif (surtitres « COMPTE CLIENT FICTIF », « DES COMPÉTENCES EN CÔTE D'IVOIRE ») ; décoration et statut se confondent, et le badge ne dit pas ce que le client doit faire. | IMG-1..3 ; INS §4 | Orange réservé à l'accent de marque ; statuts en couleurs sémantiques + libellé d'action (`03` §3). | M |
| C12 | CDC §2 : favoris reportables ; ARB inclut C08 Favoris dans les 46 gabarits ; ARC prévoit `favorite` mais pas de préférences de notification (F38, CDC §11). | CDC ; ARB ; ARC | C08 marqué « reportable » ; table des préférences ajoutée au modèle conceptuel. | i |
| C13 | CDC §11 : un seul niveau de notifications « essentielles » ; préférences pour limiter les « secondaires » sans les définir. | CDC §11 | À définir (Q12). | i |
| C14 | **Référence `dgafrique-core`** : `composer.json` exige PHP `^8.3` alors que l'ADR-001 et le README annoncent PHP 8.4 ; Livewire est chargé (import `livewire.esm` fournissant Alpine) mais **aucun composant Livewire** n'existe (`app/Livewire` absent) ; le dépôt est en « NO-GO production » et son propre `AGENTS.md` décrit une histoire de frontend retiré puis repris. | REF | La référence éclaire des **patterns**, elle ne prouve pas la maturité de Livewire dans ce contexte. Voir `02` §2. | i |
| C15 | « Réservée » (mission) : ARC distingue ouverte / réservée / attribuée ; CDC F18 mentionne « attribuée » après financement. Ce que le public voit pendant la réservation n'est pas dit. | CDC F17–F18 ; ARC §8 | Proposition : mission « en cours de sélection » visible, non proposable (`04` §5). | Q (non bloquante) |
| C16 | Les durées « 7 jours » désignent deux choses : validité d'une proposition (CDC §18) et délai d'examen client avant saisie du support (F32). | CDC | Paramètres séparés dans `policy_version`. | i |

## 6. Contenus fictifs et preuves publiques (SRC N04, ARB §7 ; INS §5)

- Aucun faux avis, faux volume d'activité, faux badge ou faux « vérifié ».
- Les contenus de démonstration sont **explicitement identifiés** (« Exemple » / « Démonstration »), visibles à l'œil (pas seulement en `aria-label`), absents des statistiques et exclus de l'indexation.
- Les noms de personnes des captures (Aminata Koné, Eric Kouassi, David Koffi) ressemblent à des identités réelles : **proposition** de les remplacer en démonstration par des profils manifestement fictifs et marqués, ou d'afficher des **exemples de prestations** sans nom de personne. L'accueil réel ne montre que des services et profils publiés et modérés (`04` §2).

## 7. Prochaine étape après revue

Examen des documents par le porteur → **présentation visuelle des écrans** (maquettes haute fidélité, `04` §11.1) → arbitrage des questions restantes (`05` §5) → vérification des versions → prompt de développement par lots (`02` §9). Aucun développement applicatif avant.
