# Dockerfile for Laravel Advisor System (Backend Only)
# PHP 8.2 with MySQL, MongoDB, Redis support

FROM php:8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    autoconf \
    g++ \
    make \
    openssl-dev \
    mysql-client \
    bash

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Install MongoDB extension (specific version to match composer.lock)
RUN pecl install mongodb-1.21.0 \
    && docker-php-ext-enable mongodb

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (without dev dependencies for production)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

# Copy application files
COPY . .

# Generate optimized autoload files (skip scripts to avoid dev dependency issues)
RUN composer dump-autoload --optimize --no-scripts

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Switch to non-root user
USER www-data

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]

# Development stage
FROM base AS development

USER root

# Install development dependencies
RUN composer install --prefer-dist

USER www-data

# Production stage (default)
FROM base AS production

# Production is already optimized in base stage
