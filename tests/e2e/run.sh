#!/usr/bin/env bash
# Parcours navigateur sur une base JETABLE (freeci_e2e) : jamais la base de développement ni de production.
# Prérequis : PostgreSQL, Chromium + Playwright (PLAYWRIGHT_MODULE, CHROMIUM). Ne déploie rien.
set -euo pipefail
cd "$(dirname "$0")/../.."
export DB_DATABASE=freeci_e2e APP_ENV=local APP_DEBUG=false CACHE_STORE=database
PORT="${PORT:-8099}"; EMAIL="client@$(grep -oP "const DOMAIN = '\K[^']+" database/seeders/DemoCatalogSeeder.php)"
PASSWORD="E2e-$(head -c 12 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')9"
php artisan migrate:fresh --seed --force >/dev/null
# Le seeder génère un mot de passe aléatoire affiché une fois : on le remplace par un secret jetable local.
HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$PASSWORD")
PGPASSWORD="${DB_PASSWORD:-$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2-)}" psql -q -h 127.0.0.1 -U "${DB_USERNAME:-freeci}" -d freeci_e2e -c "UPDATE users SET password = '$HASH' WHERE email = '$EMAIL'"
php artisan serve --host=127.0.0.1 --port="$PORT" >/dev/null 2>&1 &
SERVER=$!; trap 'kill $SERVER 2>/dev/null || true' EXIT
for _ in $(seq 1 30); do curl -fs "http://127.0.0.1:$PORT/up" >/dev/null && break; sleep 1; done
BASE_URL="http://127.0.0.1:$PORT" E2E_EMAIL="$EMAIL" E2E_PASSWORD="$PASSWORD" node tests/e2e/browser-smoke.mjs
