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

# Forge's own "Linking storage directories" step (which runs before this
# script) points storage/ at a shared, persistent directory outside the
# release so logs/cache survive across deploys. On a freshly provisioned
# site that shared directory starts empty — it doesn't have the
# framework/{cache,sessions,testing,views} subdirectories a plain git
# checkout gets for free via .gitignore placeholders. Recreate them
# defensively so `artisan optimize` (specifically view:cache) doesn't fail
# with "View path not found" on a brand-new site.
mkdir -p storage/framework/cache/data storage/framework/sessions \
         storage/framework/testing storage/framework/views \
         storage/app/public storage/logs

$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force

# Forge's own ZDD activation step swaps the `current` symlink and reloads
# PHP-FPM after this script succeeds — no need to do that here. Queue
# workers/Horizon don't pick up new code automatically, though:
$FORGE_PHP artisan queue:restart
