#!/usr/bin/env bash
# Sauvegarde FreeCI : base PostgreSQL (format « custom »), dossier storage/, copie du .env (droits 600) et SHA déployé.
# Ne modifie rien dans l'application. Usage : deploy/backup.sh [étiquette]
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
require_install

label="${1:-manuel}"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
dir="$BACKUP_DIR/${stamp}_${label//[^A-Za-z0-9_-]/_}"
umask 077
mkdir -p "$dir"

export PGPASSWORD="$(env_get DB_PASSWORD)"
db_host="$(env_get DB_HOST)"; db_port="$(env_get DB_PORT)"; db_name="$(env_get DB_DATABASE)"; db_user="$(env_get DB_USERNAME)"

log "Sauvegarde de la base ${db_name} → $dir/db.dump"
pg_dump --host="$db_host" --port="${db_port:-5432}" --username="$db_user" --format=custom --no-owner --file="$dir/db.dump" "$db_name"
pg_restore --list "$dir/db.dump" >/dev/null || die "La sauvegarde de la base est illisible."

log "Sauvegarde de storage/ et du .env"
tar -C "$APP_DIR" --exclude='storage/logs' --exclude='storage/framework/cache' --exclude='storage/framework/sessions' --exclude='storage/framework/views' -czf "$dir/storage.tar.gz" storage
cp -p "$APP_DIR/.env" "$dir/env.copy"
(cd "$APP_DIR" && git rev-parse HEAD 2>/dev/null || echo "inconnu") > "$dir/SHA"
(cd "$dir" && sha256sum db.dump storage.tar.gz env.copy SHA > SHA256SUMS)

# Rotation : on ne supprime que les dossiers de sauvegarde de ce programme, au-delà de KEEP_BACKUPS.
mapfile -t old < <(ls -1d "$BACKUP_DIR"/[0-9]*T[0-9]*Z_* 2>/dev/null | sort | head -n "-${KEEP_BACKUPS}" || true)
for d in "${old[@]:-}"; do [ -n "$d" ] && [ -f "$d/SHA256SUMS" ] && rm -rf -- "$d"; done

log "Sauvegarde terminée : $dir ($(du -sh "$dir" | cut -f1))"
echo "$dir"
