#!/bin/bash
set -e

cd /var/www

echo "========================================"
echo "  AppAPI - Iniciando contenedor"
echo "========================================"

# Copiar .env si no existe
if [ ! -f .env ]; then
    echo "[1/5] Copiando .env.docker -> .env"
    cp .env.docker .env
else
    echo "[1/5] .env ya existe, omitiendo copia"
fi

# Sobreescribir variables de conexion con las del entorno Docker
if [ -n "$DB_HOST" ]; then
    sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=${DB_CONNECTION}/" .env
    sed -i "s/^DB_HOST=.*/DB_HOST=${DB_HOST}/" .env
    sed -i "s/^DB_PORT=.*/DB_PORT=${DB_PORT}/" .env
    sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE}/" .env
    sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME}/" .env
    sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" .env
fi

# Generar APP_KEY si no esta seteada
echo "[2/5] Verificando APP_KEY"
APP_KEY_VALUE=$(grep '^APP_KEY=' .env | cut -d'=' -f2)
if [ -z "$APP_KEY_VALUE" ]; then
    php artisan key:generate --force
    echo "      APP_KEY generada"
else
    echo "      APP_KEY ya existe"
fi

# Esperar PostgreSQL
echo "[3/5] Esperando PostgreSQL en ${DB_HOST}:${DB_PORT}..."
until php -r "new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
    echo "      PostgreSQL no disponible aun, reintentando..."
    sleep 2
done
echo "      PostgreSQL listo"

# Migraciones centrales
echo "[4/5] Ejecutando migraciones centrales"
php artisan migrate --force

# Seed (crea tenant demo1)
echo "[5/5] Ejecutando seeders (demo1)"
php artisan db:seed --force

echo ""
echo "========================================"
echo "  API lista en http://localhost:8000"
echo "  Tenant demo: POST /demo1/api/v1/login"
echo "  User: admin@demo1.com / password123"
echo "========================================"
echo ""

# Iniciar servidor
exec php artisan serve --host=0.0.0.0 --port=8000
