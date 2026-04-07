FROM richarvey/nginx-php-fpm:latest

ENV WEBROOT /var/www/html/public
ENV APP_ENV production
ENV APP_DEBUG false

COPY . /var/www/html/

RUN composer install --no-dev --optimize-autoloader && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
