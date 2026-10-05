#!/usr/bin/env bash
# Retour arrière du CODE vers un SHA antérieur. La base n'est restaurée que sur demande explicite.
# Usage : deploy/rollback.sh <SHA> [--restore-db <dossier de sauvegarde>]
# ATTENTION : restaurer la base efface toutes les données écrites depuis la sauvegarde.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
require_install

target="${1:-}"; [ -n "$target" ] || die "Usage : deploy/rollback.sh <SHA> [--restore-db <dossier>]"
restore_dir=""
if [ "${2:-}" = "--restore-db" ]; then restore_dir="${3:-}"; [ -d "$restore_dir" ] || die "Dossier de sauvegarde introuvable."; fi

cd "$APP_DIR"
sha="$(git rev-parse --verify "${target}^{commit}")" || die "SHA inconnu : $target (git fetch d'abord ?)"
git diff --quiet && git diff --cached --quiet || die "Des fichiers suivis sont modifiés dans $APP_DIR."

if [ -n "$restore_dir" ]; then
  (cd "$restore_dir" && sha256sum -c SHA256SUMS >/dev/null) || die "Sauvegarde corrompue (SHA256SUMS)."
  warn "La base sera REMPLACÉE par $restore_dir ; les données écrites depuis seront PERDUES."
  read -r -p "Tapez RESTAURER pour confirmer : " ok; [ "$ok" = "RESTAURER" ] || die "Annulé."
fi

safety="$("$APP_DIR/deploy/backup.sh" "avant-retour-${sha:0:8}" | tail -n1)"
log "Sauvegarde de l'état actuel : $safety"
trap 'warn "Échec du retour arrière. Le site reste en MAINTENANCE. Sauvegarde de l'"'"'état actuel : '"$safety"'."' ERR

artisan down --retry=60 || true
git checkout --detach "$sha"
install_dependencies
if [ -n "$restore_dir" ]; then
  export PGPASSWORD="$(env_get DB_PASSWORD)"
  log "Restauration de la base depuis $restore_dir/db.dump (une seule transaction : tout ou rien)"
  # On supprime d'abord les objets appartenant au rôle FreeCI dans CETTE base (tables ajoutées depuis la sauvegarde
  # comprises), puis on rejoue la sauvegarde. Les autres bases du serveur ne sont pas concernées.
  { echo "DROP OWNED BY CURRENT_USER CASCADE;"; pg_restore --no-owner --file=- "$restore_dir/db.dump"; } \
    | psql --no-psqlrc --quiet --host="$(env_get DB_HOST)" --port="$(env_get DB_PORT)" --username="$(env_get DB_USERNAME)" \
        --dbname="$(env_get DB_DATABASE)" --set=ON_ERROR_STOP=1 --single-transaction --output=/dev/null
else
  warn "Base non restaurée. Si une migration de la version retirée a modifié le schéma, utilisez --restore-db."
fi
artisan optimize:clear
build_caches
artisan freeci:preflight || warn "Contrôle de production en échec : voir ci-dessus."
reload_php
artisan up
artisan queue:restart || true      # les processus de file rechargent le nouveau code (sans effet s'il n'y en a pas)
trap - ERR
log "Retour arrière terminé vers ${sha}"
