# Fonctions communes aux scripts de déploiement FreeCI. À « sourcer », pas à exécuter.
# Variables (toutes facultatives, valeurs par défaut entre parenthèses) :
#   APP_DIR (dossier parent de deploy/)  BACKUP_DIR (/var/backups/freeci)  KEEP_BACKUPS (14)
#   PHP_BIN (php)  COMPOSER_BIN (composer ; peut contenir des espaces, ex. « php8.3 /usr/local/bin/composer »)  NPM_BIN (npm)  SKIP_FRONTEND_BUILD (0)
#   PHP_FPM_SERVICE (vide = ne pas recharger ; ex. php8.3-fpm)  GIT_REMOTE (origin)
#   Copie HORS VPS (facultative, désactivée par défaut) : BACKUP_OFFSITE_RCLONE (« remote:chemin ») ou BACKUP_OFFSITE_RSYNC (« utilisateur@hôte:/chemin »),
#   avec BACKUP_GPG_PASSPHRASE_FILE (fichier droits 600 contenant la phrase de passe : la copie hors VPS est TOUJOURS chiffrée, car elle contient la clé APP_KEY).

set -Eeuo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
# Réglages propres au serveur (non versionnés) : deploy/local.env, voir deploy/local.env.example.
# shellcheck disable=SC1091
[ -f "$APP_DIR/deploy/local.env" ] && . "$APP_DIR/deploy/local.env"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/freeci}"
BACKUP_OFFSITE_RCLONE="${BACKUP_OFFSITE_RCLONE:-}"
BACKUP_OFFSITE_RSYNC="${BACKUP_OFFSITE_RSYNC:-}"
BACKUP_GPG_PASSPHRASE_FILE="${BACKUP_GPG_PASSPHRASE_FILE:-}"
KEEP_BACKUPS="${KEEP_BACKUPS:-14}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
SKIP_FRONTEND_BUILD="${SKIP_FRONTEND_BUILD:-0}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-}"
GIT_REMOTE="${GIT_REMOTE:-origin}"

log()  { printf '\033[1;34m[freeci]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[freeci] ATTENTION :\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m[freeci] ERREUR :\033[0m %s\n' "$*" >&2; exit 1; }

artisan() { (cd "$APP_DIR" && "$PHP_BIN" artisan "$@"); }

# Lit une variable du .env sans l'exécuter (pas de « source » : le fichier peut contenir des caractères spéciaux).
env_get() {
  local key="$1" line
  line="$(grep -E "^${key}=" "$APP_DIR/.env" | tail -n1 || true)"
  line="${line#*=}"; line="${line%\"}"; line="${line#\"}"
  printf '%s' "$line"
}

# Fichier d'état des sauvegardes (lecture seule pour l'application) : « clé=valeur », une par ligne, aucune donnée sensible.
status_set() {
  local key="$1" value="$2" f="$BACKUP_DIR/STATUS" tmp
  mkdir -p "$BACKUP_DIR"; tmp="$(mktemp "$BACKUP_DIR/.status.XXXXXX")"
  { [ -f "$f" ] && grep -v "^${key}=" "$f" || true; echo "${key}=${value}"; } > "$tmp"
  chmod 644 "$tmp"; mv -f "$tmp" "$f"
}

require_install() {
  [ -f "$APP_DIR/artisan" ] || die "artisan introuvable dans $APP_DIR (APP_DIR incorrect ?)"
  [ -f "$APP_DIR/.env" ] || die ".env absent dans $APP_DIR : première installation non terminée."
  [ "$(env_get APP_ENV)" = "production" ] || die "APP_ENV n'est pas « production » dans .env."
  [ "$(env_get DB_CONNECTION)" = "pgsql" ] || die "DB_CONNECTION doit valoir pgsql."
  [ "$(id -u)" -ne 0 ] || die "Ne pas exécuter en root : utiliser l'utilisateur propriétaire de $APP_DIR."
}

reload_php() {
  if [ -n "$PHP_FPM_SERVICE" ]; then
    log "Rechargement de $PHP_FPM_SERVICE (vide le cache d'opcodes)"
    sudo -n systemctl reload "$PHP_FPM_SERVICE" || warn "Rechargement impossible sans mot de passe : exécutez « sudo systemctl reload $PHP_FPM_SERVICE »."
  else
    warn "PHP_FPM_SERVICE non défini : rechargez PHP-FPM vous-même (ex. « sudo systemctl reload php8.3-fpm »)."
  fi
}

build_caches() {
  artisan config:cache
  artisan route:cache
  artisan view:cache
  artisan event:cache
}

install_dependencies() {
  log "Dépendances PHP (production, sans outils de test)"
  (cd "$APP_DIR" && $COMPOSER_BIN install --no-dev --prefer-dist --no-interaction --optimize-autoloader)
  if [ "$SKIP_FRONTEND_BUILD" = "1" ]; then
    [ -f "$APP_DIR/public/build/manifest.json" ] || die "SKIP_FRONTEND_BUILD=1 mais public/build/manifest.json est absent : copiez les ressources compilées."
    log "Ressources compilées fournies (SKIP_FRONTEND_BUILD=1)"
  else
    command -v "$NPM_BIN" >/dev/null || die "npm introuvable : installez Node 22 ou compilez ailleurs et utilisez SKIP_FRONTEND_BUILD=1."
    log "Compilation des ressources"
    (cd "$APP_DIR" && "$NPM_BIN" ci --ignore-scripts && "$NPM_BIN" run build)
  fi
}
