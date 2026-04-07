#!/usr/bin/env bash
# Thoát ngay nếu có lỗi
set -o errexit

composer install --no-dev --optimize-autoloader

# Gom các lệnh tối ưu vào đây
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Nếu có dùng database của Render thì bỏ dấu # ở dòng dưới
# php artisan migrate --force
