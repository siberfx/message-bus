#!/usr/bin/env bash
set -euo pipefail

cd /var/www/app

php artisan down --retry=15
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Cache #[AsMessageHandler] / #[AsMessage] discovery (no directory scanning per request)
php artisan message-bus:cache

# Ask running workers to exit after their current message; Supervisor restarts them
php artisan message-bus:stop-workers
php artisan queue:restart

php artisan up
