# 1. Chọn môi trường PHP 8.2 có sẵn Nginx (rất nhẹ và nhanh)
FROM richarvey/nginx-php-fpm:latest

# 2. Copy toàn bộ code Laravel vào trong máy ảo Docker
COPY . /var/www/html

# 3. Cấu hình các biến môi trường cơ bản
ENV WEBROOT /var/www/html/public
ENV APP_ENV production

# 4. Chạy file build.sh mà bạn đã tạo
RUN chmod +x /var/www/html/build.sh
RUN /var/www/html/build.sh

# 5. Cấp quyền cho các thư mục cache của Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
