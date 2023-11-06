FROM php:8.1-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev zip
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"
RUN docker-php-ext-install gd pdo pdo_mysql

COPY . .

EXPOSE 8000
CMD ["php-fpm"]
