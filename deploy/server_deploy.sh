#!/bin/sh
set -e

echo "Deploying application ..."

# Enter maintenance mode
(php artisan down ) || true
    # Update codebase
    git fetch origin deploy
    git reset --hard origin/deploy

    # Install dependencies based on lock file. --no-dev leaves out the test and
    # tooling packages (Faker, Pail, Sail, PHPUnit, ...); nothing the app runs
    # in production uses them.
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

    # Migrate database
    php artisan migrate --force

    # Expose storage/app/public (uploaded recipe photos) at public/storage
    php artisan storage:link

    # Count this deploy. The counter lives beside current/ so a reset never loses
    # it; the copy in current/ is read into config by `optimize` below.
    echo $(( $(cat ../deploy_number 2>/dev/null || echo 0) + 1 )) > ../deploy_number
    cp ../deploy_number DEPLOY_NUMBER

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
