#!/usr/bin/env bash
# Recette volontaire du paiement simulé, SANS modifier le .env à la main.
# Usage (utilisateur propriétaire, pas root) :
#   deploy/recette.sh amorcer        crée/complète les comptes de recette (FREECI_ALLOW_DEMO_SEED passe à true le temps
#                                    de l'opération, même absent du .env, et est REMIS à false quoi qu'il arrive)
#   deploy/recette.sh simulateur-on  active le simulateur (génère le secret des notifications s'il est vide)
#   deploy/recette.sh simulateur-off désactive le simulateur (état normal de la production)
#   deploy/recette.sh etat           affiche l'état (aucun secret affiché)
# Le simulateur ne fonctionne de toute façon que pour des commandes de démonstration et des comptes de recette autorisés.
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
  simulateur-on)
    [ -n "$(env_get FREECI_SANDBOX_WEBHOOK_SECRET)" ] || env_set FREECI_SANDBOX_WEBHOOK_SECRET "$(openssl rand -hex 32)"
    env_set FREECI_PAYMENT_SANDBOX true
    artisan config:cache
    log "Simulateur ACTIVÉ pour la recette. Pensez à « deploy/recette.sh simulateur-off » ensuite."
    ;;
  simulateur-off)
    env_set FREECI_PAYMENT_SANDBOX false
    artisan config:cache
    log "Simulateur désactivé."
    ;;
  etat)
    printf 'FREECI_PAYMENT_SANDBOX=%s\nFREECI_ALLOW_DEMO_SEED=%s\nFREECI_FILE_SCANNER=%s\nsecret_notifications=%s\n' \
      "$(env_get FREECI_PAYMENT_SANDBOX)" "$(env_get FREECI_ALLOW_DEMO_SEED)" "$(env_get FREECI_FILE_SCANNER)" \
      "$([ -n "$(env_get FREECI_SANDBOX_WEBHOOK_SECRET)" ] && echo défini || echo vide)"
    artisan freeci:files:check || true
    ;;
  *) die "Usage : deploy/recette.sh amorcer | simulateur-on | simulateur-off | etat" ;;
esac
