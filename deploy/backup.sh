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
install -m 600 "$APP_DIR/.env" "$dir/env.copy"
(cd "$APP_DIR" && git rev-parse HEAD 2>/dev/null || echo "inconnu") > "$dir/SHA"
(cd "$dir" && sha256sum db.dump storage.tar.gz env.copy SHA > SHA256SUMS)

# Rotation : on ne supprime que les dossiers de sauvegarde de ce programme, au-delà de KEEP_BACKUPS.
mapfile -t old < <(ls -1d "$BACKUP_DIR"/[0-9]*T[0-9]*Z_* 2>/dev/null | sort | head -n "-${KEEP_BACKUPS}" || true)
for d in "${old[@]:-}"; do [ -n "$d" ] && [ -f "$d/SHA256SUMS" ] && rm -rf -- "$d"; done

[ -n "$(env_get APP_KEY)" ] || warn "APP_KEY est vide dans .env : les données chiffrées (destinations de reversement, secrets MFA) seraient irrécupérables."
status_set local_ok_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
status_set local_dir "$(basename "$dir")"

# Copie HORS VPS : facultative et désactivée par défaut. Son résultat n'est JAMAIS présumé : il est écrit dans STATUS, l'échec n'interrompt pas la mise à jour.
offsite() {
  if [ -z "$BACKUP_OFFSITE_RCLONE" ] && [ -z "$BACKUP_OFFSITE_RSYNC" ]; then status_set offsite disabled; return 0; fi
  if [ -z "$BACKUP_GPG_PASSPHRASE_FILE" ] || [ ! -r "$BACKUP_GPG_PASSPHRASE_FILE" ]; then
    warn "Copie hors VPS non faite : BACKUP_GPG_PASSPHRASE_FILE absent ou illisible (la copie contient APP_KEY : elle doit être chiffrée)."; status_set offsite failed_no_passphrase; return 1
  fi
  local bundle="$BACKUP_DIR/$(basename "$dir").tar.gpg"
  tar -C "$BACKUP_DIR" -cf - "$(basename "$dir")" | gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_GPG_PASSPHRASE_FILE" --symmetric --cipher-algo AES256 -o "$bundle" \
    || { status_set offsite failed_encrypt; return 1; }
  if [ -n "$BACKUP_OFFSITE_RCLONE" ]; then rclone copy "$bundle" "$BACKUP_OFFSITE_RCLONE" || { rm -f "$bundle"; status_set offsite failed_transfer; return 1; }
  else rsync -e ssh --chmod=F600 "$bundle" "$BACKUP_OFFSITE_RSYNC" || { rm -f "$bundle"; status_set offsite failed_transfer; return 1; }; fi
  rm -f "$bundle"
  status_set offsite ok; status_set offsite_ok_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
}
offsite || warn "La copie hors VPS a échoué : la sauvegarde LOCALE existe, mais aucune copie distante n'est confirmée (voir $BACKUP_DIR/STATUS)."

log "Sauvegarde terminée : $dir ($(du -sh "$dir" | cut -f1))"
echo "$dir"
