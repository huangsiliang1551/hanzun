FROM m.daocloud.io/docker.io/php:8.1-fpm
WORKDIR /var/www/html

RUN docker-php-ext-install pdo_mysql

COPY . /var/www/html

RUN mkdir -p /var/www/html/backend/runtime /var/www/html/backend/public/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R u+rwX,g+rwX /var/www/html

EXPOSE 9000
