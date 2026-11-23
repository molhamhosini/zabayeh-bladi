#!/usr/bin/env bash
# Run on the server from the backend folder after pulling new code: bash deploy.sh
set -euo pipefail
cd "$(dirname "$0")"

php artisan down --retry=30 || true
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan storage:link || true
php artisan filament:assets
php artisan optimize:clear
php artisan optimize
php artisan up
echo "Deployed."
