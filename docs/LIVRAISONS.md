# Journal des livraisons à déployer

Pour chaque livraison : **SHA à déployer**, **nouvelles variables d'environnement**, **migrations à appliquer**, procédure = `docs/07-deploiement.md` §7. Le SHA exact est indiqué dans le message de livraison (un commit ne peut pas contenir son propre SHA) et se lit aussi par `git log -1 --format=%H` sur `main`.

| Lot | Contenu | Nouvelles variables | Migrations à appliquer |
|---|---|---|---|
| **1 — socle et découverte** (première installation) | Accueil, catalogue, fiche de service, comptes ; durcissement de production ; scripts de déploiement | **Toutes** : modèle complet dans `deploy/env.production.example` (dont `FREECI_HSTS_MAX_AGE`, `FREECI_NOINDEX`, `FREECI_DEMO_BANNER`, `FREECI_ALLOW_DEMO_SEED`, `FREECI_DEMO_CLIENT_PASSWORD`, `TRUSTED_PROXIES`, `SESSION_SECURE_COOKIE`, `MAIL_*`) | `0001_01_01_000000_create_users_table` · `0001_01_01_000001_create_cache_table` · `0001_01_01_000002_create_jobs_table` · `2026_10_06_000100_create_catalog_tables` (crée l'extension `unaccent` et la fonction `freeci_unaccent`) |
| **1.1 — administrateur** (mise à jour du lot 1) | Rôles `client`/`freelance` en données ; habilitation **administrateur** datée, motivée, révocable ; commandes `freeci:admin:grant`, `freeci:admin:revoke`, `freeci:admin:list` ; coquille `/admin` (aucune fonction, 404 pour les non-administrateurs) ; tout compte inscrit reçoit le rôle client | **Aucune** | `2026_10_07_000100_create_roles_and_staff_grants` (crée `account_roles` et `staff_grants` ; **les comptes existants reçoivent le rôle client**) |
