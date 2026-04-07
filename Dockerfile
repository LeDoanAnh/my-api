# Sử dụng Image có sẵn Nginx và PHP-FPM cực nhẹ và tương thích tốt với Render
FROM richarvey/nginx-php-fpm:latest

# 1. Cấu hình môi trường
ENV WEBROOT /var/www/html/public
ENV APP_ENV production
ENV APP_DEBUG false

# 2. SỬA LỖI TẠI ĐÂY: Dùng dấu chấm để copy toàn bộ nội dung hiện có
# Cách này giúp Docker không cần tìm tên thư mục MY-API cụ thể nữa
COPY . /var/www/html/

# 3. Cài đặt và phân quyền
RUN composer install --no-dev --optimize-autoloader && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 4. Không dùng lệnh CMD ở đây để tránh lỗi Supervisor
