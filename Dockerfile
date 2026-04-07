# Sử dụng Image có sẵn Nginx và PHP-FPM cực nhẹ và tương thích tốt với Render
FROM richarvey/nginx-php-fpm:latest

# Thiết lập biến môi trường bắt buộc cho Laravel
ENV WEBROOT /var/www/html/public
ENV APP_ENV production
ENV APP_DEBUG false

# Copy toàn bộ mã nguồn vào thư mục làm việc của web server
COPY . /var/www/html

# Cài đặt các thư viện PHP cần thiết thông qua Composer
# Bước này sẽ chạy tự động khi Render build image
RUN composer install --no-dev --optimize-autoloader

# Phân quyền cho các thư mục quan trọng để Laravel có thể ghi log và cache
# Nếu không có bước này, app sẽ báo lỗi 500 khi chạy
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Lưu ý: Image richarvey tự động chạy Nginx và PHP nên KHÔNG CẦN thêm lệnh CMD ở đây.
# Việc thêm CMD sai cú pháp thường là nguyên nhân gây lỗi 127.
RUN php artisan config:clear
RUN php artisan route:clear

# Lệnh khởi chạy của em thường ở cuối, ví dụ:
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
