# 34 — Prompt à donner à Claude sur le VPS

À copier-coller tel quel dans une session Claude Code lancée **sur le serveur** (dossier `/var/www/freeci`). Il couvre ce qui ne peut pas être fait depuis l'environnement de développement : cartes de démonstration absentes de l'accueil, 504 intermittent, et les vérifications serveur de l'étape « Préparer l'exploitation ».

---

Tu travailles sur le VPS de FreeCI (https://freeci.dgafrique.com), dans `/var/www/freeci`, application Laravel 13 / PHP 8.3 / PostgreSQL derrière nginx + PHP-FPM. Les commandes `artisan` se lancent avec `sudo -u freeci -H php8.3 artisan …`. Je suis Koné Djakaridja, dirigeant du projet : je décide, tu diagnostiques, tu proposes, tu appliques seulement ce qui est sûr et réversible. Réponds en français, de façon concise et factuelle.

## Règles absolues
1. **Aucun secret** dans ta réponse, tes journaux ou une commande affichée : jamais le contenu de `.env`, de clés, de mots de passe, de jetons. Pour lire `.env`, filtre avec `sed -E 's/=.*/=***/'` ou ne lis que les clés non sensibles (`APP_ENV`, `APP_DEBUG`, `CACHE_STORE`, `QUEUE_CONNECTION`, `FREECI_*` booléens).
2. **Le paiement reste en sandbox.** N'active jamais le paiement réel, ne touche pas aux réglages Genius Pay.
3. **Préserve les données.** Jamais de `migrate:fresh`, `db:wipe`, `TRUNCATE`, `DROP`, ni de suppression de lignes sans ma validation. Avant toute écriture en base ou tout changement de configuration système, fais une sauvegarde : `sudo -u freeci -H /var/www/freeci/deploy/backup.sh avant-diagnostic` et note son chemin.
4. **Ne déploie pas de code** et ne modifie pas les fichiers de l'application (le code se déploie uniquement par `deploy/update.sh <SHA>` depuis `origin/main`). Si une correction de code est nécessaire, décris-la précisément (fichier, ligne, raison, test) : elle sera faite et testée dans l'environnement de développement.
5. **Autres sites sur ce serveur : ne les touche pas.** Avant tout `reload` : `nginx -t` ou `php-fpm8.3 -t` doit réussir ; pas de `restart` sans me prévenir ; sauvegarde le fichier de configuration modifié (`cp -a fichier fichier.avant-AAAAMMJJ`).
6. Pas de `git push --force`, pas de commit sur le serveur.
7. Commence toujours par des commandes **en lecture seule**. Avant chaque écriture, écris en une phrase ce que tu vas faire et comment l'annuler.

## Mission 1 : les huit cartes de démonstration ne s'affichent pas sur l'accueil
Contexte : la page d'accueil (`pages/home.blade.php`, contrôleur `HomeController`, `ListPublishedServices(8)`) affiche les 8 services publiés les plus récents (règle `Service::published()` : statut `published`, `published_at` non nul et passé, vendeur non suspendu). Le jeu de démonstration compte 17 services `is_demo` mais l'accueil n'en montre pas huit. Une carte admin (Administration › État et préparation › Données de démonstration) affiche « les visiteurs voient N carte(s) sur 8 » avec la cause par catégorie ; la commande `freeci:demo-install --yes` réinstalle et remet en ligne.

À faire, dans l'ordre :
1. Vérifie la version déployée : `git -C /var/www/freeci rev-parse HEAD` et `git log -1 --format=%cd`. Compare avec `origin/main` (`git fetch` autorisé). Si le serveur n'est pas au dernier commit, dis-le (je lancerai `update.sh`).
2. Base de données (lecture seule, via `psql` avec l'utilisateur de l'application, sans afficher le mot de passe) : pour les services `is_demo = true`, compte par `status`, `published_at` (nul / futur / passé), vendeur suspendu (`users.suspended_at`), profil (`freelance_profiles.published_at`, `slug`). Compte aussi les services publiés non démo.
3. Ce que voit réellement le visiteur : `curl -s https://freeci.dgafrique.com/ | grep -c 'class="svc'` (attendu : 8) et un `curl -s -D- -o /dev/null` pour lire les en-têtes (cache nginx, `Cache-Control`, `X-Cache`, `Age`). Vérifie aussi `curl -s http://127.0.0.1/` avec l'en-tête `Host:` pour contourner un éventuel cache ou CDN externe.
4. Caches : `config:cache` et `view:cache` à jour ? `public/build/manifest.json` récent ? OPcache (`opcache.validate_timestamps`) : après un déploiement, PHP-FPM a-t-il été rechargé ? Cherche un cache de page nginx (`fastcgi_cache`, `proxy_cache`) dans `/etc/nginx/sites-enabled/` pour ce site.
5. Journaux : `storage/logs/laravel.log` (dernières erreurs, sans données personnelles), journal nginx du site et `php8.3-fpm.log`.
6. Si la cause est dans les données : lance `sudo -u freeci -H php8.3 artisan freeci:demo-install --yes` (après sauvegarde), relis la sortie, puis refais l'étape 3. Elle doit annoncer « L'accueil affiche 8 carte(s) ».
7. Si l'accueil reste à moins de 8 cartes alors que la base en contient 8 visibles : reproduis la requête de `ListPublishedServices` (services publiés, tri `published_at` décroissant, limite 8), exécute-la, et rends-moi le résultat avec le HTML correspondant. C'est alors un défaut de code à corriger côté développement.

## Mission 2 : le 504 intermittent (et le 503 pendant les mises à jour)
Contexte : nginx a expiré en attendant l'en-tête de réponse PHP-FPM pour `POST /livewire-…/update` (compteurs de non-lus rafraîchis toutes les 30 s, depuis `/freelance/revenus`) le 6 octobre vers 23:55 (+0200). Hypothèses à confirmer ou écarter par des **preuves** : saturation des processus PHP-FPM (`pm.max_children`), requête lente en base, analyses de fichiers (ClamAV) exécutées dans les processus web, worker de file arrêté, manque de mémoire ou de CPU, mise à jour en cours (`artisan down` renvoie 503 « Service Unavailable »).
À faire :
1. Ressources : `uptime`, `free -h`, `df -h`, `vmstat 1 5`, processus les plus gourmands, présence de swap, événements OOM (`journalctl -k | grep -i oom`).
2. PHP-FPM : valeurs réelles du pool du site (`pm`, `pm.max_children`, `pm.start_servers`, `request_terminate_timeout`, `slowlog`, `request_slowlog_timeout`), état (`pm.status_path` s'il est activé), messages « server reached pm.max_children » dans `/var/log/php8.3-fpm.log`.
3. Active le **slowlog** s'il ne l'est pas (`request_slowlog_timeout = 5s`, fichier `/var/log/php8.3-fpm-freeci-slow.log`) : c'est un changement de configuration sûr et réversible. Après sauvegarde du fichier, `php-fpm8.3 -t` puis `systemctl reload php8.3-fpm`.
4. nginx : `fastcgi_read_timeout`, `client_max_body_size`, journal d'erreurs : cherche « upstream timed out » et rapproche les heures du slowlog et des mises à jour (`/var/www/freeci/storage/logs`, sauvegardes `avant-*` horodatées).
5. PostgreSQL : durées de requêtes. Si `pg_stat_statements` n'est pas actif, active `log_min_duration_statement = 500` (rechargement, sans redémarrage) et dis-moi combien de temps laisser tourner. Cherche des verrous longs (`pg_locks`, `pg_stat_activity` avec `state != 'idle'` depuis plus de 5 s).
6. File et tâches : le worker de file tourne-t-il (`systemctl status freeci-queue` ou équivalent) ? Le cron `schedule:run` est-il installé pour l'utilisateur `freeci` ? Dernières exécutions dans la carte « Tâches planifiées » (aujourd'hui toutes « Jamais exécutée » : c'est probablement le cron qui manque). Corrige en suivant `deploy/cron.example` et `deploy/freeci-queue.service.example` (après m'avoir montré les lignes exactes).
7. Conclusion attendue : **la cause démontrée** (avec l'extrait de journal ou la mesure qui la prouve), ou à défaut ce qu'il manque pour la démontrer et combien de temps surveiller. N'augmente jamais un délai ou une limite « pour que ça passe » sans cause démontrée.

## Mission 3 : vérifications d'exploitation (étape « Préparer l'exploitation »)
Pour chacun, donne **OK / À corriger** et la preuve :
1. `sudo -u freeci -H php8.3 artisan freeci:preflight` et `freeci:files:check` (ClamAV opérationnel, disque privé inscriptible, limites `upload_max_filesize` / `post_max_size` du **pool PHP-FPM web**, pas seulement la ligne de commande, et `client_max_body_size` de nginx ≥ taille maximale d'un fichier).
2. Cron `schedule:run` (toutes les minutes) et worker de file (redémarrage automatique, `queue:restart` pris en compte).
3. Sauvegardes : `deploy/backup.sh` fonctionne, dernière sauvegarde récente, rotation ; **copie hors serveur** (aujourd'hui « non activée ») : propose une solution (destination chiffrée, fréquence, rétention) sans me demander de secret dans la conversation : indique seulement où le déposer sur le serveur (fichier lisible par `freeci` seul, mode 600). Test de restauration : `deploy/restore-test.sh` sur une base **jetable**, jamais sur la production, avec le résultat.
4. `APP_KEY` : confirme qu'une copie hors serveur existe ou doit être faite (sans l'afficher). Rappelle-moi pourquoi c'est vital (secrets chiffrés en base).
5. HTTPS : validité et renouvellement automatique du certificat, redirection http→https, en-têtes de sécurité déjà présents.
6. Courriel : l'envoi réel passe par le SMTP configuré dans Paramètres (Gmail). Vérifie la file `jobs` et les courriels « en attente » (la carte indique 18 en attente depuis plus de 15 min : explique pourquoi, probablement parce que le worker ne tourne pas). Ne demande pas le mot de passe SMTP ; je le saisis moi-même dans Administration › Paramètres › Courrier.
7. Pare-feu, ports ouverts, `APP_DEBUG=false`, droits des dossiers `storage/` et `bootstrap/cache/`, rotation des journaux (`logrotate`).

## Ce que tu me rends
Un rapport en français, en trois parties : **1) Constats** (avec la preuve de chacun, sans secret), **2) Changements appliqués** (fichier, avant/après, comment annuler, sauvegarde faite), **3) À décider ou à corriger côté développement** (défauts de code précis, décisions qui m'appartiennent). Termine par la liste exacte des commandes que je dois lancer moi-même, s'il y en a. Si une étape est impossible ou dangereuse, arrête-toi et dis pourquoi plutôt que de contourner.
