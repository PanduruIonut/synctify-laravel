#!/bin/bash
cp .env.example .env

./wait-for-it.sh mysql_db:3306 -t 60

php artisan key:generate

php artisan migrate

php artisan serve --host=0.0.0.0





