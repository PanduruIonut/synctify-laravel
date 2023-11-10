#!/bin/bash
cp .env.example .env

./wait-for-it.sh mysql_db:3306 -t 60

php artisan key:generate

export $(grep -v '^#' /var/www/html/.env | xargs -0)

if [[ -z $(mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "SHOW DATABASES LIKE '$DB_DATABASE'";) ]]; then
    mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "CREATE DATABASE $DB_DATABASE;"
fi

php artisan migrate

php artisan serve --host=0.0.0.0





