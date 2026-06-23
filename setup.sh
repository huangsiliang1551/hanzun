#!/bin/bash
# 涵尊 CMS - 服务器收尾脚本
# 在服务器项目目录下执行: bash setup.sh

set -e

SITE="/www/wwwroot/bagelsmachinery.com"
PHP_FPM_SERVICE="php-fpm"  # 如果 php8.1-fpm 就改这里

echo "=========================================="
echo "  涵尊 CMS 部署收尾"
echo "=========================================="

# 1. 配置 .env
echo "[1/6] 配置 .env..."
cp -f backend/.env.production backend/.env

# 替换 JWT 密钥中的特殊字符（如果 AUTH_JWT_SECRET 含 $date 的话）
JWT_SECRET="$(openssl rand -hex 32 2>/dev/null || php -r 'echo bin2hex(random_bytes(32));')"
sed -i "s/AUTH_JWT_SECRET=.*/AUTH_JWT_SECRET=${JWT_SECRET}/" backend/.env

# 2. 创建目录
echo "[2/6] 创建 runtime & uploads 目录..."
mkdir -p backend/runtime backend/public/uploads

# 3. 设置权限（www 是宝塔/常见 PHP 用户）
echo "[3/6] 设置目录权限..."
chown -R www:www backend/runtime backend/public/uploads 2>/dev/null || \
chown -R www-data:www-data backend/runtime backend/public/uploads 2>/dev/null || true
chmod -R 755 backend/runtime backend/public/uploads 2>/dev/null || true

# 4. 安装 Composer 依赖
echo "[4/6] 安装 Composer 依赖..."
cd backend
if command -v composer &>/dev/null; then
    composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "  composer 未安装，尝试 php composer.phar..."
    if [ -f composer.phar ]; then
        php composer.phar install --no-dev --optimize-autoloader --no-interaction
    else
        curl -sS https://getcomposer.org/installer | php
        php composer.phar install --no-dev --optimize-autoloader --no-interaction
    fi
fi
cd "$SITE"

# 5. 重启 PHP-FPM
echo "[5/6] 重启 PHP-FPM..."
systemctl restart "$PHP_FPM_SERVICE" 2>/dev/null || \
service "$PHP_FPM_SERVICE" restart 2>/dev/null || \
echo "  ⚠ 请手动重启 PHP-FPM"

# 6. Nginx 配置
echo "[6/6] Nginx 配置..."
NGINX_CONF="/etc/nginx/conf.d/hanzun-cms.conf"
if [ -f hanzun-cms.nginx.conf ]; then
    cp hanzun-cms.nginx.conf "$NGINX_CONF"
    nginx -t && nginx -s reload 2>/dev/null || \
    systemctl reload nginx 2>/dev/null || \
    echo "  ⚠ 请手动重载 Nginx"
fi

echo ""
echo "=========================================="
echo "  Deploy completed"
echo "=========================================="
echo ""
echo "  Frontend: https://bagelsmachinery.com"
echo "  Admin Login: https://bagelsmachinery.com/login"
echo "  Admin App: https://bagelsmachinery.com/admin-app/#/dashboard"
echo ""
