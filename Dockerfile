# Monstein API - Minimal PHP Docker Image
# Multi-stage build for optimized production image

# ============================================================================
# Stage 1: Composer dependencies
# ============================================================================
FROM composer:2 AS composer

WORKDIR /app

# Copy composer files first for better caching
COPY composer.json ./

# Install dependencies (no dev for production)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application source
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# ============================================================================
# Stage 2: Production image (Alpine-based minimal PHP)
# ============================================================================
FROM php:8.2-fpm-alpine AS production

LABEL maintainer="Lahiru <lahirunirmalx@gmail.com>"
LABEL description="Monstein REST API - Lightweight PHP framework"
LABEL version="1.0.0"

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    # MySQL/MariaDB client libraries
    mariadb-client \
    # Required for pdo_mysql
    && docker-php-ext-install pdo pdo_mysql \
    # Clean up
    && rm -rf /var/cache/apk/*

# Create application user
RUN addgroup -g 1000 monstein && \
    adduser -u 1000 -G monstein -s /bin/sh -D monstein

# Create required directories
RUN mkdir -p /var/run/nginx /var/log/nginx /var/log/supervisor \
    /app/logs /app/storage/ratelimit \
    && chown -R monstein:monstein /app

WORKDIR /app

# Copy application from composer stage
COPY --from=composer --chown=monstein:monstein /app/vendor ./vendor
COPY --chown=monstein:monstein . .

# Create logs and storage directories with proper permissions
RUN mkdir -p logs storage/ratelimit \
    && chown -R monstein:monstein logs storage \
    && chmod -R 755 logs storage

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

# Expose port (configurable via docker-compose)
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ============================================================================
# Stage 3: Development image (includes dev tools)
# ============================================================================
FROM production AS development

# Install development dependencies
RUN apk add --no-cache \
    git \
    vim \
    bash

# Install Xdebug for debugging
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

# Copy dev php.ini
COPY docker/php-dev.ini /usr/local/etc/php/conf.d/dev.ini

# Install composer for development
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Re-install with dev dependencies
WORKDIR /app
RUN composer install --prefer-dist
