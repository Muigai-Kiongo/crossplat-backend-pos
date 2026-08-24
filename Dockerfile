FROM php:8.2-fpm

# Install system dependencies and required libraries for PostgreSQL, GD (images), and Zip
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql gd zip \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy application files into the image
COPY . /var/www/html

# Ensure proper file permissions for application and upload directories
RUN mkdir -p public/assets/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 public/assets/uploads

EXPOSE 9000

CMD ["php-fpm"]
