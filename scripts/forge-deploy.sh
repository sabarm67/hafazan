#!/usr/bin/env bash
set -e

# Paste this into the Forge site's Zero-Downtime Deployment "Deployment
# Script" field. Forge has already cloned a fresh copy of the repo into
# this release directory and cd'd into it before this script runs — do NOT
# `cd` to the site root or `git pull` here; ZDD already did that.
#
# Laravel lives at the repo root (composer.json, artisan, public/ etc. are
# all here directly), so Forge's Web Directory can stay at the default
# `/public` — no custom path config needed. The Vue frontend builds
# straight into public/ (see frontend/vite.config.ts).

cd "$FORGE_RELEASE_DIRECTORY"

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

cd frontend

export NVM_DIR="$HOME/.nvm"
# shellcheck disable=SC1091
[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"

npm ci
npm run build

cd "$FORGE_RELEASE_DIRECTORY"

$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force

# Forge's own ZDD activation step swaps the `current` symlink and reloads
# PHP-FPM after this script succeeds — no need to do that here. Queue
# workers/Horizon don't pick up new code automatically, though:
$FORGE_PHP artisan queue:restart
