#!/bin/bash
cp .env.example .env

./wait-for-it.sh mysql_db:3306 -t 60

# Install dependencies
composer install --no-interaction --prefer-dist

php artisan key:generate

php artisan migrate --force

service cron start

php artisan serve --host=0.0.0.0
