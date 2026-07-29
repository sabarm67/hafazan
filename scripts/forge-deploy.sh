#!/usr/bin/env bash
set -e

# Paste this into the Forge site's Zero-Downtime Deployment "Deployment
# Script" field. $CREATE_RELEASE() / $ACTIVATE_RELEASE() / $RESTART_QUEUES()
# are Forge's ZDD macros — Forge does NOT run them for you around the
# script; the script must call them explicitly in this order, or the new
# release is built but `current` never gets repointed at it (see
# https://forge.laravel.com/docs/sites/deployments). That was the actual
# cause of `current` staying on the phantom releases/000000 forever despite
# every deploy reporting "Deployment complete" — this script previously
# never called $ACTIVATE_RELEASE() at all.
#
# Laravel lives at the repo root (composer.json, artisan, public/ etc. are
# all here directly), so Forge's Web Directory can stay at the default
# `/public` — no custom path config needed. The Vue frontend builds
# straight into public/ (see frontend/vite.config.ts).

$CREATE_RELEASE()

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

# Everything above runs against the new release before it's live. Only
# after this point does `current` get repointed at it:
$ACTIVATE_RELEASE()

# Queue workers/Horizon keep running on the old code until restarted —
# do this after activation so they pick up the new release.
$RESTART_QUEUES()
