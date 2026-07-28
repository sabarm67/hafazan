#!/usr/bin/env bash
set -e

# Paste this into the Forge site's "App / Deployment Script" field
# (or point that field at this file — Forge runs it as-is either way).
#
# Assumes: Forge site root is the repo root, cloned at
# /home/forge/hafazan.rcaquacycle.com, with the site's "Web Directory" set
# to /backend/public (Laravel lives in backend/, not the repo root — see
# docs/02-system-architecture.md "Production Deployment").

cd /home/forge/hafazan.rcaquacycle.com

git pull origin "$FORGE_SITE_BRANCH"

# --- Backend ---
cd backend

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
    $FORGE_PHP artisan event:cache
fi

# --- Frontend (builds directly into backend/public — see frontend/vite.config.ts) ---
cd ../frontend

export NVM_DIR="$HOME/.nvm"
# shellcheck disable=SC1091
[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"

npm ci
npm run build

# --- Restart PHP-FPM and queue workers so new code takes effect ---
( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service "$FORGE_PHP_FPM" reload ) 9>/tmp/fpmlock

cd ../backend
$FORGE_PHP artisan queue:restart
