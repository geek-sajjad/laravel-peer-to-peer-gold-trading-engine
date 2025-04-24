#!/bin/bash

# Exit on error
set -e

echo "🚀 Building containers..."
docker compose up -d --build


echo "🧹 Coping .env file ..."
cp .env.example .env

echo "🔐 Running migrations..."
docker exec -it laravel-app php artisan migrate --force || true

echo "🔐 Running seeders ..."
docker exec -it laravel-app php artisan db:seed --force || true

# echo "🧹 Setting file permissions..."
# sudo chown -R $USER:$USER .


echo "✅ Laravel is ready at: http://localhost:8000"


