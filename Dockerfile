# FROM php:8.4-fpm

# # Install system dependencies
# RUN apt-get update && apt-get install -y \
#     build-essential \
#     libpng-dev \
#     libjpeg-dev \
#     libonig-dev \
#     libxml2-dev \
#     zip \
#     unzip \
#     curl \
#     git \
#     nano \
#     libzip-dev \
#     && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl bcmath gd

# # Install Composer
# COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# WORKDIR /var/www

# # Copy existing application directory
# COPY . .

# # Set permissions
# RUN chown -R www-data:www-data /var/www \
#     && chmod -R 755 /var/www

# EXPOSE 9000

# CMD ["php-fpm"]









# Build stage
FROM composer:latest AS composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader

# Runtime stage
FROM php:8.4-fpm
# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    git \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy Composer binary
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Copy application code from build stage
COPY --from=composer /app /var/www

WORKDIR /var/www

# Set permissions
# RUN chown -R www-data:www-data /var/www \
#     && chmod -R 755 /var/www
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]