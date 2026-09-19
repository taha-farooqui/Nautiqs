#!/usr/bin/env bash
#
# Deploy Nautiqs on the production VPS.
#
#   cd /var/www/nautiqs && sudo bash deploy.sh
#
# Replaces the hand-run sequence of artisan commands. Three of the steps here
# exist because their absence has already caused an incident:
#
#   * the .env cluster check — running config:cache against a .env pointing at
#     the wrong cluster silently moved the whole app to an empty database and
#     took logins down for everyone (June 2026);
#   * npm run build — public/build is gitignored and built in place, so a
#     deploy that skips it serves a stale stylesheet and every CSS class added
#     since the last build is silently missing;
#   * the chown — artisan run under sudo leaves root-owned compiled views that
#     www-data cannot overwrite, so the next template change 500s.
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_USER="www-data"
FPM_SERVICE="php8.5-fpm"
EXPECTED_CLUSTER="${DEPLOY_EXPECTED_CLUSTER:-nautiqs-prod.weqivcf}"
HEALTH_URL="${DEPLOY_HEALTH_URL:-https://app.nautiqs.fr/login}"

cd "$APP_DIR"
step() { printf '\n\033[1m==> %s\033[0m\n' "$1"; }

# --------------------------------------------------------------------------
step "Checking .env points at the intended cluster"
if [ "$(grep -c "$EXPECTED_CLUSTER" .env || true)" -lt 1 ]; then
    echo "ABORT: .env does not reference $EXPECTED_CLUSTER."
    echo "Caching config now would point the app at a different database."
    exit 1
fi
echo "ok — $EXPECTED_CLUSTER"

# --------------------------------------------------------------------------
step "Pulling"
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
BEFORE="$(git rev-parse --short HEAD)"
git pull --ff-only
AFTER="$(git rev-parse --short HEAD)"
echo "$BEFORE -> $AFTER"

# --------------------------------------------------------------------------
step "PHP dependencies"
if ! git diff --quiet "$BEFORE" "$AFTER" -- composer.lock 2>/dev/null; then
    composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "composer.lock unchanged — skipping"
fi

# --------------------------------------------------------------------------
# Always rebuild. The cost is a few seconds and the failure mode of skipping it
# is invisible: the page renders, just without the styles it asks for.
step "Front-end assets"
if ! git diff --quiet "$BEFORE" "$AFTER" -- package-lock.json 2>/dev/null; then
    npm ci
fi
npm run build

# --------------------------------------------------------------------------
step "Caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --------------------------------------------------------------------------
# Everything above ran as root; hand back what the web user has to write to.
step "Ownership"
chown -R "$WEB_USER:$WEB_USER" \
    storage/framework/views \
    storage/framework/cache \
    storage/framework/sessions \
    storage/logs \
    bootstrap/cache \
    public/build
echo "ok"

# --------------------------------------------------------------------------
step "Reloading PHP-FPM"
systemctl reload "$FPM_SERVICE"

# --------------------------------------------------------------------------
step "Smoke test"
CODE="$(curl -s -o /dev/null -w '%{http_code}' "$HEALTH_URL")"
if [ "$CODE" != "200" ]; then
    echo "FAILED: $HEALTH_URL returned $CODE"
    exit 1
fi

# A stale stylesheet is the failure this script exists to prevent, so confirm
# the page really is linking the bundle that was just built.
CSS_URL="$(curl -s "$HEALTH_URL" | grep -oE 'https?://[^"]*app-[A-Za-z0-9_-]+\.css' | head -1)"
CSS_CODE="$(curl -s -o /dev/null -w '%{http_code}' "$CSS_URL")"
echo "login page : $CODE"
echo "stylesheet : $CSS_CODE  $(basename "$CSS_URL")"
[ "$CSS_CODE" = "200" ] || { echo "FAILED: stylesheet not served"; exit 1; }

printf '\n\033[1mDeployed %s\033[0m\n' "$AFTER"
