#!/usr/bin/env bash
# Manual update on a server that has git + composer: run from the repo root after `git pull`.
#   bash backend/deploy/update.sh
set -euo pipefail
cd "$(dirname "$0")/.."
PHP="${PHP:-php}"

$PHP artisan down --retry=30 || true
composer install --no-dev --optimize-autoloader --no-interaction
$PHP artisan migrate --force
$PHP artisan storage:link || true
$PHP artisan filament:assets
$PHP artisan optimize:clear
$PHP artisan optimize
$PHP artisan up
echo "Deployed."
