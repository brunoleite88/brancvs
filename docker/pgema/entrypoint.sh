#!/bin/bash
set -e

echo "=== [SGPE PGE-MA] Inicializando container da aplicação ==="

# 1. Garante estrutura de diretórios e permissões do storage e cache
mkdir -p /var/www/storage/app/public \
         /var/www/storage/framework/cache/data \
         /var/www/storage/framework/cache/laravel-excel \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/storage/logs \
         /var/www/storage/fonts \
         /var/www/bootstrap/cache

touch /var/www/storage/logs/laravel.log /var/www/storage/logs/mysql-slow.log
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 2. Aguarda o banco de dados MariaDB responder
if [ -n "${DB_HOST}" ]; then
    echo "Aguardando banco de dados em ${DB_HOST}:${DB_PORT:-3306}..."
    for i in $(seq 1 60); do
        if mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent >/dev/null 2>&1; then
            echo "Banco de dados online!"
            break
        fi
        sleep 2
    done
fi

# 3. Limpa caches antigos e recria link simbólico do storage
php /var/www/artisan config:clear || true
php /var/www/artisan cache:clear || true
php /var/www/artisan route:clear || true
php /var/www/artisan view:clear || true
php /var/www/artisan storage:link || true

# 4. Inicia o serviço de cron em background para rotinas agendadas
service cron start || true

echo "=== [SGPE PGE-MA] Aplicação pronta na porta 80 interna (7077 externa) ==="
exec "$@"

