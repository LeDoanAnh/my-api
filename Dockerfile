# Sử dụng Image có sẵn Nginx và PHP-FPM cực nhẹ và tương thích tốt với Render
FROM richarvey/nginx-php-fpm:latest

# Thiết lập các biến môi trường để Image tự hiểu cấu trúc Laravel
ENV WEBROOT /var/www/html/public
ENV APP_ENV production
ENV APP_DEBUG false

# Copy toàn bộ mã nguồn vào thư mục làm việc
COPY . /var/www/html

# Cài đặt các thư viện PHP (Sử dụng --no-dev để nhẹ và bảo mật hơn)
RUN composer install --no-dev --optimize-autoloader

# Phân quyền cho các thư mục Laravel cần quyền ghi
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Xóa cache cũ để tránh lỗi nhận nhầm host 127.0.0.1
RUN php artisan config:clear
RUN php artisan route:clear

# LƯU Ý QUAN TRỌNG:
# Không thêm dòng CMD ở đây. Image này đã có sẵn script khởi chạy
# Nginx và PHP-FPM tự động rồi. Thêm CMD vào sẽ gây lỗi Supervisor.
