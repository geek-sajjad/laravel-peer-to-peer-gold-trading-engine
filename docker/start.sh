#!/bin/bash

# Exit on error
set -e

echo "🧹 Coping .env file ..."
cp .env.example .env


echo "🚀 Building containers..."

docker compose up -d --build app webserver db



echo "🔐 Running migrations..."
docker exec -it laravel-app php artisan migrate --force || true


echo "🔐 Running seeders ..."
docker exec -it laravel-app php artisan db:seed --force || true


#echo "🔐 Running api  install..."
#docker exec -it laravel-app php artisan install:api --force || true


echo "🚀 Building queue container..."
docker compose up -d --build queue

#echo "Waiting for services to be ready..."
#sleep 10 # Adjust sleep time if needed to ensure DB is ready


echo "🧹 Setting file permissions..."
sudo chown -R $USER:$USER .


echo "✅ Laravel is ready at: http://localhost:8000"



