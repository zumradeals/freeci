# 07 — Déploiement sur VPS (freeci.dgafrique.com)

> **Statut : préparé, non déployé.** Le porteur réalise lui-même le déploiement et les mises à jour. Rien ici n'a été exécuté sur le serveur ; les scripts ont été éprouvés dans un bac à sable local (installation, mise à jour avec migration, retour arrière avec restauration de base, refus de `migrate:fresh` et d'amorçage en production).
> **Autonomie** : FreeCI reste un produit distinct de DG Afrique. Le sous-domaine `freeci.dgafrique.com` est **temporaire** ; le domaine se règle par `APP_URL` (§4.5) et par la configuration du serveur web, jamais dans le code. Aucun cookie, aucune base, aucun fichier n'est partagé avec un autre site.
> **Principe** : le VPS n'est **pas** supposé vierge. Toute action ci-dessous s'ajoute à côté des autres sites (nouveau vhost, nouvelle base, nouveau rôle, nouveau dossier) et ne modifie ni la configuration globale, ni la version de PHP par défaut, ni les autres sites.

## 1. Ce que la version déployée fait et ne fait pas

- **Public sans restriction** : accueil, catalogue, fiches de service. Aucune authentification globale, aucun mot de passe de site.
- **Privé** : `/espace` exige une connexion ; réponses `private, no-store` ; `robots.txt` et balise `noindex` ; cookies de session `Secure`, `HttpOnly`, `SameSite=Lax`, propres à ce domaine.
- **Inachevé et signalé** : bandeau « Démonstration » (désactivable : `FREECI_DEMO_BANNER`), pastilles « Bientôt », pages d'information à la place de tout formulaire hors lot. **Aucun paiement, aucune commande, aucune livraison** n'existent dans le code : il n'y a rien à activer ni à désactiver.
- **Données fictives optionnelles** : jamais installées par `migrate` ni par une mise à jour ; l'amorçage est **refusé en production** sauf double geste volontaire (§9). Chaque élément fictif est marqué « Exemple fictif ».
- **Indexation** : `FREECI_NOINDEX=true` par défaut (moteurs de recherche priés de ne pas indexer) tant que la démonstration dure.

## 2. Inventaire du serveur avant toute action (lecture seule)

À exécuter et à me communiquer (ou à lire soi-même) avant d'adapter les chemins :

```bash
cat /etc/os-release | head -3; uname -m; nproc; free -h | head -2; df -h / /var /var/www 2>/dev/null
# Serveur web et sites existants
(nginx -v; nginx -T 2>/dev/null | grep -E "server_name|root |listen " | sort -u) 2>&1 | head -40
(apache2 -v; apachectl -S) 2>&1 | head -30
ss -ltnp | grep -E ":(80|443|5432|3306)\b"
# PHP : plusieurs versions peuvent coexister ; on n'utilise PAS celle par défaut sans vérifier
php -v | head -1; ls /usr/bin/php* /run/php/*.sock 2>/dev/null; systemctl list-units 'php*-fpm*' --no-legend
# PostgreSQL, Node, Composer, Git
psql --version; pg_lsclusters 2>/dev/null; node -v; npm -v; composer --version; git --version
# Pare-feu, certificats, DNS
sudo ufw status 2>/dev/null; sudo certbot certificates 2>/dev/null | grep -E "Name|Domains|Expiry"
dig +short freeci.dgafrique.com
```

Seuls ces éléments conditionnent les instructions : voir la liste courte en §12.

## 3. Prérequis

| Élément | Exigence | Si absent ou plus ancien |
|---|---|---|
| PHP | **8.3 à 8.5** (`composer.lock` : plateforme `php ^8.3` ; Laravel 13.34 exige 8.3 ; Livewire 4.4.7 ≥ 8.1). Extensions **exigées par le `composer.lock` publié** : `ctype`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `session`, `tokenizer` ; **exigée par l'application** : `pdo_pgsql` ; recommandées : `opcache`, `intl`, `curl`, `zip` | Installer `php8.3-fpm` **à côté** de l'existant (paquets Ondřej Surý sur Debian/Ubuntu, Remi sur RHEL) ; ne pas changer `update-alternatives` pour `php`. Appeler explicitement `php8.3` (variable `PHP_BIN=php8.3`). Contrôle : `php8.3 -m` puis `composer check-platform-reqs --no-dev` |
| PostgreSQL | ≥ 13 (testé 16) ; extension `unaccent` (contrib) | Installer `postgresql` + `postgresql-contrib` ; ne pas migrer ni arrêter un autre cluster |
| Serveur web | nginx ou Apache, avec TLS | Voir `deploy/nginx-freeci.conf.example`, `deploy/apache-freeci.conf.example` |
| Composer | 2.x | `composer --version` |
| Node | 22 (compilation des ressources) | **Facultatif** : compiler sur un poste et copier `public/build/` (§7, `SKIP_FRONTEND_BUILD=1`) |
| Git | ≥ 2.30 | |
| SMTP | Un compte d'envoi pour la récupération de mot de passe | Sans SMTP, la réinitialisation ne fonctionne pas (le contrôle de production le signale) |
| DNS | Enregistrement `A`/`AAAA` de `freeci.dgafrique.com` vers le VPS | |

## 4. Première installation

Hypothèses modifiables : dossier `/var/www/freeci`, utilisateur de déploiement `deploy`, groupe du serveur web `www-data`, PHP 8.3-FPM. Adapter selon l'inventaire (§2).

### 4.1 Base de données dédiée (n'affecte aucune autre base)

```bash
sudo -u postgres psql <<'SQL'
CREATE ROLE freeci LOGIN PASSWORD 'REMPLACER-PAR-UN-MOT-DE-PASSE-LONG-ET-UNIQUE';
CREATE DATABASE freeci OWNER freeci ENCODING 'UTF8';
SQL
# L'extension « unaccent » est « de confiance » (PG ≥ 13) : le propriétaire de la base peut la créer.
# Sinon, en superutilisateur, une seule fois :  sudo -u postgres psql -d freeci -c "CREATE EXTENSION IF NOT EXISTS unaccent;"
```

Vérifier que `listen_addresses` reste local et que `pg_hba.conf` n'ouvre pas `freeci` à l'extérieur.

### 4.2 Code

```bash
sudo mkdir -p /var/www/freeci /var/backups/freeci
sudo chown deploy:www-data /var/www/freeci && sudo chown deploy:deploy /var/backups/freeci && sudo chmod 700 /var/backups/freeci
sudo -u deploy git clone https://github.com/zumradeals/freeci.git /var/www/freeci
cd /var/www/freeci
git checkout --detach <SHA-A-DEPLOYER>          # le SHA indiqué dans la livraison (docs/LIVRAISONS.md)
```

### 4.3 Dépendances et ressources

```bash
composer install --no-dev --prefer-dist --optimize-autoloader     # (PHP 8.3 : « php8.3 $(which composer) install … » si besoin)
npm ci --ignore-scripts && npm run build                          # ou copier public/build/ depuis un poste (§7)
```

### 4.4 Configuration

```bash
cp deploy/env.production.example .env
php artisan key:generate --force                 # écrit APP_KEY dans .env : À SAUVEGARDER (§8)
chmod 640 .env && chown deploy:www-data .env
nano .env                                        # remplacer chaque <…> : DB_PASSWORD, MAIL_*, TRUSTED_PROXIES si proxy
```

### 4.5 Domaine (configurable)

Le domaine ne figure **que** dans : `APP_URL` (`.env`), le vhost (`server_name`) et le certificat. Pour changer de domaine plus tard : modifier ces trois éléments, puis `php artisan config:cache`. Les anciens cookies de session cessent simplement de s'appliquer.

### 4.6 Droits d'écriture

```bash
sudo chgrp -R www-data storage bootstrap/cache && sudo chmod -R ug+rwX storage bootstrap/cache
```

### 4.7 Schéma (sans données de démonstration)

```bash
php artisan migrate --force          # ne crée aucune donnée fictive
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```

### 4.8 Serveur web et HTTPS

```bash
sudo cp deploy/nginx-freeci-http.conf /etc/nginx/sites-available/freeci.dgafrique.com   # adapter hôte, chemins, socket PHP-FPM
sudo ln -s /etc/nginx/sites-available/freeci.dgafrique.com /etc/nginx/sites-enabled/
sudo nginx -t                                    # DOIT réussir avant tout rechargement : il valide aussi les autres sites
sudo certbot --nginx -d freeci.dgafrique.com     # certificat Let's Encrypt, ne touche que ce vhost
sudo nginx -t && sudo systemctl reload nginx
```

Renouvellement : le minuteur `certbot` installé avec le paquet suffit ; tester par `sudo certbot renew --dry-run`.

### 4.9 Contrôle et ouverture

```bash
php artisan freeci:preflight        # doit se terminer par « aucun point bloquant »
curl -sI https://freeci.dgafrique.com | head -12            # 200, Strict-Transport-Security, X-Frame-Options
curl -sI http://freeci.dgafrique.com | head -3              # 301 vers https
curl -s -o /dev/null -w "%{http_code}\n" https://freeci.dgafrique.com/espace      # 302 vers /connexion
curl -s -o /dev/null -w "%{http_code}\n" https://freeci.dgafrique.com/.env        # 403 ou 404, jamais 200
```

### 4.10 Sauvegardes automatiques

```bash
crontab -e     # (utilisateur deploy) coller le contenu de deploy/cron.example en adaptant les chemins
```

## 5. HTTPS, debug et protection des espaces privés — récapitulatif

| Exigence | Réalisation | Contrôle |
|---|---|---|
| HTTPS partout | Redirection 301 depuis le port 80 ; `APP_URL` en `https://` force le schéma des liens ; HSTS 30 jours (`FREECI_HSTS_MAX_AGE`, **sans** `includeSubDomains` ni `preload`) | `freeci:preflight` ; `curl -I` |
| Débogage désactivé | `APP_ENV=production`, `APP_DEBUG=false` | `freeci:preflight` (bloquant) |
| Espaces privés | Middleware `auth` + `no-store` sur `/espace` ; sessions en base ; cookie `Secure`/`HttpOnly`/`SameSite=Lax`/chiffré ; limitation des tentatives de connexion ; message d'échec unique | Tests automatisés (`AuthenticationTest`) |
| Secrets | Dans `.env` seulement (640), jamais dans Git ; `.env` hors de la racine web | `curl …/.env` |
| Commandes destructrices | `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe` **refusées en production** | Test automatisé ; essai dans le bac à sable |
| En-têtes | `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy` | `curl -I` |
| Limites connues | Pas de politique `Content-Security-Policy` (Livewire/Alpine demandent un réglage dédié : lot ultérieur) ; pas d'authentification à deux facteurs ; pas de pare-feu applicatif | — |

## 6. Variables d'environnement

Voir `.env.example` (développement, complet) et `deploy/env.production.example` (production). Variables propres à FreeCI : `FREECI_HSTS_MAX_AGE`, `FREECI_NOINDEX`, `FREECI_DEMO_BANNER`, `FREECI_ALLOW_DEMO_SEED`, `FREECI_DEMO_CLIENT_PASSWORD`, `TRUSTED_PROXIES`. Les nouvelles variables de chaque livraison sont listées dans `docs/LIVRAISONS.md`.

## 7. Mise à jour (données préservées)

Chaque livraison indique un **SHA**, les **nouvelles variables d'environnement** et les **migrations** (`docs/LIVRAISONS.md`).

```bash
cd /var/www/freeci
# 1. Ajouter d'éventuelles nouvelles variables au .env (nano .env), AVANT le script.
# 2. Lancer :
PHP_BIN=php8.3 PHP_FPM_SERVICE=php8.3-fpm deploy/update.sh <SHA>
#    Si Node n'est pas installé sur le serveur : compiler sur un poste (npm ci && npm run build),
#    copier public/build/ vers /var/www/freeci/public/build/, puis ajouter SKIP_FRONTEND_BUILD=1.
```

Le script, dans cet ordre : refuse un SHA qui n'est pas dans `origin/main` ; **sauvegarde** (base, storage, .env, SHA) ; passe en maintenance ; `git checkout --detach <SHA>` ; `composer install --no-dev` ; compile les ressources ; **affiche les migrations en attente** puis `migrate --force` ; reconstruit les caches ; `freeci:preflight` ; recharge PHP-FPM ; rouvre le site. **Il n'exécute jamais** `migrate:fresh`, `db:seed` ni `freeci:demo-purge`. En cas d'échec, le site **reste en maintenance** et le message donne la sauvegarde et la version précédente.

Pour voir ce qu'une migration fera avant de lancer : `php artisan migrate --pretend`.

## 8. Sauvegardes et retour arrière

**Contenu d'une sauvegarde** (`/var/backups/freeci/<date>_<étiquette>/`, droits 700/600) : `db.dump` (format custom, vérifié par `pg_restore --list`), `storage.tar.gz`, `env.copy` (contient `APP_KEY` : **la copier hors du serveur**), `SHA`, `SHA256SUMS`. Rotation : les 14 dernières (`KEEP_BACKUPS`). Sauvegarde manuelle : `deploy/backup.sh avant-essai`.

**À faire en plus** : copier régulièrement `/var/backups/freeci` hors du VPS (autre serveur, stockage objet) ; une sauvegarde qui n'existe que sur la machine ne protège pas de la perte de la machine. Tester une restauration sur une base jetable au moins une fois (`createdb freeci_essai && pg_restore -d freeci_essai db.dump`).

**Retour arrière du code seul** (pas de changement de schéma, ou migration compatible) :

```bash
deploy/rollback.sh <SHA-PRECEDENT>
```

**Retour arrière avec la base** (migration non réversible ou données corrompues) — **les données écrites depuis la sauvegarde sont perdues** :

```bash
deploy/rollback.sh <SHA-PRECEDENT> --restore-db /var/backups/freeci/<dossier-de-la-sauvegarde-avant-mise-à-jour>
# confirmation à taper : RESTAURER ; une sauvegarde de l'état actuel est prise d'abord ; restauration en une seule transaction
```

`migrate:rollback` est volontairement refusé en production : la base se rétablit par restauration. Le SHA précédent est affiché à la fin de `update.sh` et enregistré dans `SHA_AVANT_MISE_A_JOUR`.

## 9. Données de démonstration (optionnelles, volontaires)

- **Jamais** automatiques : `migrate` et `update.sh` n'en installent pas, et ne les réinstallent pas après suppression.
- Pour une démonstration **volontaire** sur ce serveur : mettre `FREECI_ALLOW_DEMO_SEED=true` dans `.env`, puis `php artisan config:cache && php artisan db:seed --force` (le mot de passe du client de démonstration est affiché **une seule fois** ; ou le fixer dans `FREECI_DEMO_CLIENT_PASSWORD`). **Remettre ensuite `FREECI_ALLOW_DEMO_SEED=false`** (le contrôle de production avertit tant que c'est `true`).
- Retirer les données fictives (et elles seules, via `is_demo`) : sauvegarde, puis `php artisan freeci:demo-purge`.
- Comptes de démonstration : `client@demo.freeci.invalid` (mot de passe voir plus haut) ; vendeurs `*@demo.freeci.invalid` non connectables.

## 10. Préserver les autres sites

- Ne **jamais** exécuter `nginx -s reload` / `systemctl reload nginx` sans `nginx -t` réussi.
- Ne pas modifier `nginx.conf`, les autres vhosts, le pool PHP-FPM d'un autre site, ni la version PHP par défaut.
- Ne pas redémarrer PostgreSQL (un `reload` suffit pour `pg_hba.conf`) ; ne pas créer `freeci` dans une base existante.
- Un pool PHP-FPM **dédié** est recommandé (`/etc/php/8.3/fpm/pool.d/freeci.conf`, utilisateur `deploy`, socket distincte) pour isoler FreeCI ; sinon utiliser le pool existant en vérifiant `open_basedir`.
- `certbot --nginx -d <hôte>` limité à cet hôte ; ne jamais lancer `certbot` sans `-d`.
- Aucun cookie partagé : `SESSION_DOMAIN=null`.
- Pas de `includeSubDomains` dans HSTS : les autres sous-domaines de `dgafrique.com` ne sont pas engagés.

## 11. Dépannage rapide

| Symptôme | Piste |
|---|---|
| Page blanche / 500 | `storage/logs/laravel-AAAA-MM-JJ.log` ; droits de `storage/` et `bootstrap/cache` ; `APP_KEY` vide |
| « Vite manifest not found » | `public/build/manifest.json` absent : compiler ou copier `public/build/` |
| Redirection en boucle | Proxy devant le serveur : renseigner `TRUSTED_PROXIES` |
| Liens en `http://` | `APP_URL` doit commencer par `https://` ; `php artisan config:cache` |
| `CREATE EXTENSION unaccent` refusé | Exécuter l'extension en superutilisateur (§4.1) puis relancer la migration |
| Mot de passe oublié sans effet | `MAIL_MAILER` encore à `log` : renseigner le SMTP |

## 12. Informations nécessaires pour adapter ces instructions

1. Distribution et version du serveur (`/etc/os-release`), architecture.
2. Serveur web utilisé (nginx ou Apache) et **version(s) de PHP** déjà installées, avec le pool/socket PHP-FPM à utiliser.
3. PostgreSQL déjà présent ? (version ; sinon autorisation d'en installer un à côté).
4. Node 22 disponible sur le serveur, ou compilation sur un poste ?
5. Utilisateur système de déploiement, dossier d'installation souhaité, droits `sudo` (au moins `reload` de PHP-FPM et nginx).
6. DNS de `freeci.dgafrique.com` déjà pointé ? Un proxy/CDN (Cloudflare…) est-il devant le VPS ? (`TRUSTED_PROXIES`.)
7. Compte SMTP à utiliser pour l'envoi (serveur, port, expéditeur autorisé).
8. Destination des copies de sauvegarde hors du VPS.
