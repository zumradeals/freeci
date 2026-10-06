#!/usr/bin/env bash
# Test de RESTAURATION d'une sauvegarde dans une base TEMPORAIRE et isolée. Ne touche jamais à la base de production, ni aux fichiers du site.
# Vérifie : empreintes (SHA256SUMS), lisibilité de l'archive, restauration complète dans une base jetable, présence des tables et lignes essentielles,
# lisibilité de storage.tar.gz, présence de APP_KEY dans la copie du .env (sa valeur n'est JAMAIS affichée). Supprime la base jetable ensuite.
# Usage : deploy/restore-test.sh [dossier de sauvegarde]   (défaut : la plus récente). À lancer idéalement sur une AUTRE machine que la production.
# Le rôle PostgreSQL doit pouvoir créer une base (CREATEDB), sinon exécuter avec un rôle d'administration de test.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
[ -f "$APP_DIR/.env" ] || die ".env absent dans $APP_DIR"

dir="${1:-$(ls -1d "$BACKUP_DIR"/[0-9]*T[0-9]*Z_* 2>/dev/null | sort | tail -n1)}"
[ -n "$dir" ] && [ -d "$dir" ] || die "Aucune sauvegarde trouvée dans $BACKUP_DIR."
log "Sauvegarde testée : $dir"
(cd "$dir" && sha256sum -c SHA256SUMS >/dev/null) || die "Empreintes invalides : sauvegarde corrompue."
pg_restore --list "$dir/db.dump" >/dev/null || die "Archive de base illisible."
tar -tzf "$dir/storage.tar.gz" >/dev/null || die "Archive storage illisible."
grep -q '^APP_KEY=.\+' "$dir/env.copy" || die "APP_KEY absente de la copie du .env : les données chiffrées ne seraient pas récupérables."

export PGPASSWORD="$(env_get DB_PASSWORD)"
host="$(env_get DB_HOST)"; port="$(env_get DB_PORT)"; user="$(env_get DB_USERNAME)"; prod_db="$(env_get DB_DATABASE)"
scratch="freeci_restore_test_$(date -u +%Y%m%d%H%M%S)"
[ "$scratch" != "$prod_db" ] || die "Refus : la base de test ne peut pas être la base de production."
cleanup() { dropdb --if-exists --host="$host" --port="${port:-5432}" --username="$user" "$scratch" >/dev/null 2>&1 || true; }
trap cleanup EXIT

createdb --host="$host" --port="${port:-5432}" --username="$user" "$scratch" || die "Création de la base jetable impossible (droit CREATEDB requis)."
log "Restauration dans la base jetable $scratch"
pg_restore --no-owner --exit-on-error --host="$host" --port="${port:-5432}" --username="$user" --dbname="$scratch" "$dir/db.dump" || die "Restauration en échec."
q() { psql --no-psqlrc -At --host="$host" --port="${port:-5432}" --username="$user" --dbname="$scratch" -c "$1"; }
for t in users orders payments financial_operations reviews; do
  q "select 1 from information_schema.tables where table_name='$t'" | grep -q 1 || die "Table essentielle absente : $t"
done
tables="$(q "select count(*) from information_schema.tables where table_schema='public'")"
log "Restauration réussie : $tables tables ; comptes=$(q 'select count(*) from users') commandes=$(q 'select count(*) from orders')"
status_set restore_test_ok_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" 2>/dev/null || true
status_set restore_test_dir "$(basename "$dir")" 2>/dev/null || true
log "Test de restauration RÉUSSI (base jetable supprimée). La production n'a pas été touchée."
