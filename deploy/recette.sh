#!/usr/bin/env bash
# Aides de recette, SANS modifier le .env à la main. Il n'existe plus de simulateur de paiement : Genius Pay (sandbox ou live) est la seule passerelle,
# configurée par FREECI_PAYMENT_MODE, FREECI_PAYMENTS_ENABLED et les clés GENIUSPAY_* (voir docs/18). Aucune clé n'est lue ni affichée ici.
# Usage (utilisateur propriétaire, pas root) :
#   deploy/recette.sh amorcer        crée/complète les comptes de démonstration (FREECI_ALLOW_DEMO_SEED passe à true le temps
#                                    de l'opération et est REMIS à false quoi qu'il arrive). Facultatif : le sandbox est ouvert à tout compte inscrit.
#   deploy/recette.sh etat           affiche l'état des paiements (aucun secret affiché)
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
require_install
cd "$APP_DIR"
ENV_FILE="$APP_DIR/.env"

# Pose ou remplace KEY=VALUE dans .env (ajoute la ligne si absente). Valeurs simples uniquement (true/false/hex).
env_set() {
  local key="$1" value="$2" tmp
  tmp="$(mktemp "$APP_DIR/.env.XXXXXX")"
  if grep -qE "^${key}=" "$ENV_FILE"; then
    sed -E "s|^${key}=.*|${key}=${value}|" "$ENV_FILE" > "$tmp"
  else
    cat "$ENV_FILE" > "$tmp"; printf '\n%s=%s\n' "$key" "$value" >> "$tmp"
  fi
  chmod --reference="$ENV_FILE" "$tmp"; mv "$tmp" "$ENV_FILE"
}

case "${1:-}" in
  amorcer)
    original="$(env_get FREECI_ALLOW_DEMO_SEED)"
    restore() { env_set FREECI_ALLOW_DEMO_SEED false; artisan config:cache >/dev/null 2>&1 || true; log "FREECI_ALLOW_DEMO_SEED remis à false."; }
    trap restore EXIT
    log "Amorçage volontaire (valeur d'origine : ${original:-absente})"
    env_set FREECI_ALLOW_DEMO_SEED true
    artisan config:cache
    artisan freeci:demo:recette --yes
    ;;
  etat)
    printf 'FREECI_PAYMENT_MODE=%s\nFREECI_PAYMENTS_ENABLED=%s\nFREECI_LIVE_PAYMENTS_AUTHORIZED=%s\nFREECI_ALLOW_DEMO_SEED=%s\nFREECI_FILE_SCANNER=%s\n' \
      "$(env_get FREECI_PAYMENT_MODE)" "$(env_get FREECI_PAYMENTS_ENABLED)" "$(env_get FREECI_LIVE_PAYMENTS_AUTHORIZED)" "$(env_get FREECI_ALLOW_DEMO_SEED)" "$(env_get FREECI_FILE_SCANNER)"
    artisan freeci:genius:status || true
    artisan freeci:files:check || true
    ;;
  *) die "Usage : deploy/recette.sh amorcer | etat" ;;
esac
