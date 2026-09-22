#!/bin/sh
set -e

echo "Deploying application ..."

# Enter maintenance mode
(php artisan down ) || true
    # Update codebase
    git fetch origin deploy
    git reset --hard origin/deploy

    # Install dependencies based on lock file
    composer install --no-interaction --prefer-dist --optimize-autoloader

    # Migrate database
    php artisan migrate --force

    # Expose storage/app/public (uploaded recipe photos) at public/storage
    php artisan storage:link

    # Clear cache
    php artisan optimize

    php artisan config:clear

    # Reload PHP to update opcache
    #echo "" | sudo -S service php7.4-fpm reload
# Exit maintenance mode
php artisan up

# Restart the hosting provider's queue worker so it picks up the new code
uapi QueueWorkers update action=restart 0

echo "Application deployed!"
