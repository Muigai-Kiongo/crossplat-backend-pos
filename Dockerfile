FROM php:8.2-fpm

# Install required PostgreSQL libraries and dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Ensure proper file permissions for public/assets/uploads
RUN mkdir -p public/assets/uploads \
    && chown -R www-data:www-data public/assets/uploads \
    && chmod -R 775 public/assets/uploads
