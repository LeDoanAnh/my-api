FROM richarvey/nginx-php-fpm:latest

# Trỏ thẳng vào thư mục public hiện tại
ENV WEBROOT /var/www/html/public
ENV APP_ENV production
ENV APP_DEBUG false

# Copy mọi thứ từ vị trí file Dockerfile vào server
COPY . /var/www/html/

# Cài đặt và phân quyền
RUN composer install --no-dev --optimize-autoloader && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

CMD php artisan serve --host=0.0.0.0 --port=10000
