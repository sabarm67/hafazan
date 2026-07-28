#!/usr/bin/env bash
set -e

# Paste this into the Forge site's Zero-Downtime Deployment "Deployment
# Script" field. Forge has already cloned a fresh copy of the repo into
# this release directory and cd'd into it before this script runs — do NOT
# `cd` to the site root or `git pull` here; ZDD already did that.
#
# Also required, once, in Forge's Zero-Downtime Deployment settings for
# this site (see docs/02-system-architecture.md "Production Deployment"
# for why — composer.json/package.json live in backend/ and frontend/,
# not the release root, which Forge's own automation assumes by default):
#   - Uncheck "Install Composer Dependencies" — this script does it.
#   - Uncheck "Install NPM Dependencies & Build Assets" — this script does it.
#   - Set the shared/persistent file paths to backend/.env and
#     backend/storage (not the defaults of .env / storage).

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

# Forge's own ZDD activation step swaps the `current` symlink and reloads
# PHP-FPM after this script succeeds — no need to do that here. Queue
# workers/Horizon don't pick up new code automatically, though:
cd ../backend
$FORGE_PHP artisan queue:restart
