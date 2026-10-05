# 06 — Lot 1 : socle applicatif et découverte des services

> Statut : **réalisé** (livraison fonctionnelle consultable). La référence visuelle V01.1 est acceptée **comme base de travail** ; cela ne vaut ni validation définitive du client ni certification de tous les comportements. Tout ce qui n'est pas listé en §2 est **à venir** et annoncé comme tel dans l'application.

## 1. Décisions (DEC du porteur) et conventions (PROP internes, réversibles)

| Réf. | Décision |
|---|---|
| D26 | V01.1 sert de référence visuelle. Conservés : trame de points sur l'accueil, monogramme + FreeCI (identité de travail, non logo validé), onglets actuels, colonne latérale à partir de 1280 px avec navigation accessible en dessous |
| D27 | Actions classées par urgence réelle, sans position figée (`04` §6.2) |
| D28 | « Demander une correction » avant « Valider la livraison », avec confirmation et conséquences (`04` §8.7) |
| D29 | Dépliants secondaires repliés sur mobile, ouverts sur ordinateur ; informations de décision toujours accessibles |
| D30 | Contenu du lot 1 (§2) |
| D31 | Conventions techniques ci-dessous, tranchées sans nouvelle demande car réversibles |

### 1.1 Versions (vérifiées le 2026-10-05)

| Élément | Choix | Source de vérification |
|---|---|---|
| PHP | 8.3 (installé : 8.3.6) — supporté par Laravel 13 (8.3 à 8.5) | Politique de support Laravel 13 (`laravel/docs`, branche 13.x) |
| Laravel | **13.34.0** (correctifs jusqu'au T3 2027, sécurité jusqu'au 17 mars 2028) | Packagist ; politique de support |
| Livewire | **4.4.7** (Alpine.js embarqué par Livewire, pas de second paquet) | Packagist |
| Tailwind CSS | **4.3.3** (`@tailwindcss/vite` 4.3.3) | Registre npm |
| Vite | **8.3.x** ; `laravel-vite-plugin` 3.x | Registre npm |
| PostgreSQL | **16** (testé) ; extension `unaccent` (extension de confiance depuis PG 13) | Documentation PostgreSQL |

Dépendances **verrouillées** : `composer.lock` et `package-lock.json`. Aucune version n'est « approuvée » au-delà de ce lot ; toute montée de version passe par une relecture des notes de version.

### 1.2 Conventions

- **Identifiants** : UUID **v7** (ordonné dans le temps) pour tous les objets métier, générés par l'application (`HasUuids`) ; clés étrangères en `uuid`. Identifiants croissants réservés aux historiques volumineux (aucun dans ce lot).
- **Schéma** : schéma `public` pour le lot 1. Un schéma dédié, et des comptes de base distincts (migration / application / lecture), seront décidés avec l'hébergement (`02` §4.3). Tout accès passe par la configuration `DB_*` : changer de schéma ne touche pas au code.
- **Noms** : tables au pluriel snake_case (conventions Laravel, D16) ; colonnes `*_id`, `*_at` en `timestamptz` (UTC) ; montants `price_xof` en `bigint` (francs entiers) ; contraintes `CHECK` sur les états et montants.
- **Organisation** : `app/Modules/{Accounts,Catalog}/{Actions,Models,Data,Enums,Exceptions}`, `app/Shared` (Money), interface dans `app/Http` et `app/Livewire`, vues `resources/views`. Les contrôleurs et Livewire n'écrivent ni ne lisent via les modèles : ils appellent des **Actions** qui renvoient des **projections publiques** (DTO) à liste de champs explicite. Un test d'architecture le vérifie.
- **Recherche** : plein texte PostgreSQL (configuration `french`) sur titre, résumé et périmètre, accents ignorés (`freeci_unaccent`, enveloppe immuable de `unaccent`), plus correspondance de préfixe sur le titre ; critères validés côté serveur, 12 résultats par page (≤ 20).
- **Authentification** : sessions Laravel locales (pilote base de données) ; mots de passe hachés ; message d'échec unique ; limitation des tentatives ; jeton de récupération à usage unique, valable 60 minutes ; pages privées en `Cache-Control: private, no-store`. Pas de kit d'authentification : écrans propres à V01.1.
- **Courrier** : pilote `log` par défaut (les messages, dont le lien de récupération, s'écrivent dans `storage/logs/laravel.log`). Un vrai fournisseur d'envoi est à choisir avant toute utilisation réelle.
- **Données de démonstration** : jamais de mot de passe dans le dépôt ; voir §3.
- **Hors lot, prévu par le schéma** : `services.row_version` (refus d'une action sur version périmée) ; les **versions immuables de service** et l'accord figé arrivent avec le lot « commande ».

## 2. Fonctionnalités réellement utilisables

Accueil fidèle à V01.1 (recherche, catégories, services publiés récents) · catalogue avec recherche, filtre par catégorie, tri et pagination, état dans l'URL (fonctionne aussi sans JavaScript) · fiche de service alimentée depuis PostgreSQL · inscription, connexion, déconnexion, mot de passe oublié et réinitialisation · espace client initial (état vide honnête).

**Annoncé « Bientôt »** (page d'information, aucun formulaire, aucun faux effet) : demande de prestation, commandes, paiements, livraisons, messages, missions, freelances, profil freelance, favoris, compte, aide, textes juridiques. Un service **retiré** (suspendu, archivé) affiche « Ce service n'est plus disponible » (HTTP 410) sans donnée privée ; un brouillon, un service en contrôle ou programmé répond 404.

## 3. Données fictives et comptes de démonstration

- `php artisan db:seed` installe 8 catégories, 9 profils de vendeurs fictifs, 15 services publiés et 1 service archivé, tous marqués « Exemple fictif ». Le jeu est **reproductible et idempotent**.
- **Vendeurs** : adresses `*@demo.freeci.invalid`, mot de passe aléatoire jeté à la création : **aucune connexion possible**.
- **Client de démonstration** `client@demo.freeci.invalid` : mot de passe pris dans `FREECI_DEMO_CLIENT_PASSWORD` si défini, sinon **généré au hasard et affiché une seule fois** dans la console. Rien n'est écrit dans le dépôt.
- Refus d'amorçage en production sauf `FREECI_ALLOW_DEMO_SEED=true`.

## 4. Vérifications

Voir le rapport `design/lot-1/RAPPORT-LOT-1.md`; déploiement : `docs/07-deploiement.md` : tests automatisés (PostgreSQL), installation propre, rendus sur 5 largeurs comparés à V01.1, contrôle axe-core, et **limites** (pas d'appareil réel, un seul moteur de navigation, pas de lecteur d'écran, courrier non envoyé).

## 5. Lot suivant recommandé

**Lot 2 — demande de prestation et commande (sans paiement réel)** : versions immuables de service, brief et pièces jointes (stockage privé, contrôle de type), accord figé, états de commande, acceptation ou refus du freelance, tableau de bord client avec actions classées par urgence, rôle et profil freelance. Le paiement (port `PaymentProvider` simulé) ne vient qu'après. **Points à trancher avant** : fournisseur de courrier ; politique de stockage de fichiers ; vérification d'adresse e-mail (lien à usage unique, `02` §8.2).

## 6. Administrateur (lot 1.1, D34)

- **Modèle** : rôles commerciaux `client` et `freelance` dans `account_roles` ; habilitation du personnel dans `staff_grants` (capacité `administrator`, motif, date, expiration facultative, révocation, auteur). Il n'existe **aucun indicateur « admin » sur le compte**. Une seule habilitation active par personne (index unique partiel).
- **Désignation** : uniquement par la console du serveur, `php artisan freeci:admin:grant <adresse> --create` (compte neuf, mot de passe aléatoire **affiché une seule fois**) ou `--existing` (compte déjà inscrit, avec `--reset-password` si l'on doute de son propriétaire). Sans l'un de ces drapeaux, **aucun compte n'est promu** : l'adresse n'étant pas vérifiée par courriel, quiconque peut s'inscrire avec n'importe quelle adresse.
- **Accès** : `/admin` (404 pour tout autre que les administrateurs en vigueur, pages `no-store`). Coquille sans fonction. Les espaces freelance et client resteront accessibles à ce compte quand ils ouvriront.
- **Avant toute fonction d'administration réelle** (à respecter) : authentification multifacteur, vérification d'adresse par lien à usage unique, courrier réel configuré.
- **Audit** : `php artisan freeci:admin:list` ; événements consignés dans les journaux.

