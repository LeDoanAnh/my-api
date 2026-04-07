# Sử dụng Image có sẵn Nginx và PHP-FPM cực nhẹ và tương thích tốt với Render
FROM richarvey/nginx-php-fpm:latest

# 1. Cấu hình môi trường cho Render
ENV WEBROOT /var/www/html/public
ENV APP_ENV production
ENV APP_DEBUG false

# 2. Copy code từ thư mục MY-API vào Docker
# Nếu Dockerfile nằm cùng cấp với app, config... thì đổi thành: COPY . /var/www/html
COPY MY-API/ /var/www/html/

# 3. Cài đặt thư viện và phân quyền (Gộp lệnh để VS Code không báo đỏ)
RUN composer install --no-dev --optimize-autoloader && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 4. Không dùng lệnh CMD ở đây để tránh lỗi Supervisor
