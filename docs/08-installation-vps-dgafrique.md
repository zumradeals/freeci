# 08 — Première installation sur le VPS (diagnostic du 2026-10-05)

> Adapté au diagnostic en lecture seule du serveur. **Non exécuté par Claude** : le porteur lance chaque bloc. Complète `docs/07-deploiement.md`.

## Constats du diagnostic

| Sujet | Constat | Conséquence |
|---|---|---|
| Système | Ubuntu 24.04, 4 CPU, 7,8 Go, 136 Go libres, ufw inactif | Largement suffisant |
| Web | nginx 1.24 ; 4 sites : `aszinga.dgafrique.com`, `console.dgafrique.com`, `dgafrique.com`, `wasplex.com` ; aucun vhost `freeci` ; TLS par Certbot (certificats **un par nom**, pas de joker) | Nouveau vhost + **nouveau certificat** pour `freeci.dgafrique.com` seulement |
| PHP | 8.3.33 et 8.4.25 (CLI par défaut : 8.4) ; FPM 8.3 et 8.4, pool `www` (www-data) ; toutes les extensions exigées présentes (`opcache` signalé à tort : extension « Zend », non listée par `php -m`) | **PHP 8.3** (version testée) en **pool dédié `freeci`** ; `php` par défaut inchangé |
| PostgreSQL | 17.10, local uniquement, nombreuses bases d'autres sites ; `unaccent` disponible | Rôle et base `freeci` **nouveaux**, jamais dans une base existante |
| Outils | Node 24.19, npm 11, Composer 2.10.2, Git 2.43, Certbot 2.9.0 (minuteur actif) | Compilation des ressources sur le serveur possible |
| Accès | Connexion `root` par mot de passe ; utilisateurs `ubuntu`, `gamadrive-backup` | Création d'un utilisateur système **`freeci`** sans mot de passe ni connexion distante ; les scripts refusent root |
| DNS / proxy | `freeci.dgafrique.com` → 169.58.19.32, aucun proxy ni CDN | `TRUSTED_PROXIES` vide |
| Courrier | Aucun MTA local | SMTP externe à fournir ; en attendant `MAIL_MAILER=log` (les liens « mot de passe oublié » ne partent pas) |

## Blocs de commandes (en root, une session Termius ; commencer par `set +H`)

Voir le message de livraison de cette étape pour l'enchaînement exact ; chaque bloc se termine par un contrôle. Mise à jour ensuite : `sudo -u freeci -H /var/www/freeci/deploy/update.sh <SHA>`.
