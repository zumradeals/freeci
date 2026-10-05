#!/usr/bin/env bash
# Mise à jour de FreeCI vers un SHA précis de la branche main. Préserve les données :
# sauvegarde d'abord, jamais de migrate:fresh, jamais d'amorçage de données de démonstration.
# Usage : deploy/update.sh <SHA complet ou abrégé>
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
require_install

target="${1:-}"; [ -n "$target" ] || die "Usage : deploy/update.sh <SHA>"
cd "$APP_DIR"
[ -d .git ] || die "$APP_DIR n'est pas un dépôt Git."
git diff --quiet && git diff --cached --quiet || die "Des fichiers suivis sont modifiés dans $APP_DIR : annulez ou conservez-les ailleurs avant de mettre à jour."

log "Récupération des nouveautés (aucune fusion, aucun forçage)"
git fetch --prune "$GIT_REMOTE" main
sha="$(git rev-parse --verify "${target}^{commit}")" || die "SHA inconnu : $target"
git merge-base --is-ancestor "$sha" "$GIT_REMOTE/main" || die "$sha n'appartient pas à $GIT_REMOTE/main : refus."
previous="$(git rev-parse HEAD)"
[ "$sha" != "$previous" ] || warn "Le SHA demandé est déjà déployé ; les étapes sont rejouées sans effet de bord."

log "Déploiement : ${previous:0:12} → ${sha:0:12}"
backup_dir="$("$APP_DIR/deploy/backup.sh" "avant-${sha:0:8}" | tail -n1)"
log "Sauvegarde de sécurité : $backup_dir"

echo "$previous" > "$backup_dir/SHA_AVANT_MISE_A_JOUR"
trap 'warn "Échec. Le site reste en MAINTENANCE. Version précédente : '"$previous"'. Sauvegarde : '"$backup_dir"'. Voir docs/07-deploiement.md §8 (retour arrière)."' ERR

artisan down --retry=60 --refresh=15 || true
git checkout --detach "$sha"
install_dependencies
log "Migrations en attente :"; artisan migrate:status --pending || true
artisan migrate --force
artisan optimize:clear
build_caches
artisan freeci:preflight
reload_php
artisan up
trap - ERR
log "Mise à jour terminée : ${sha}"
log "Vérifiez https://$(env_get APP_URL | sed -E 's#^https?://##') puis, si besoin, deploy/rollback.sh ${previous}"
