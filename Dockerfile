# ==============================================================================
# Stage 1: Build Frontend Assets (Vite + Vue 3)
# ==============================================================================
FROM node:22-alpine AS frontend-builder

WORKDIR /app

COPY package*.json ./
RUN npm ci --prefer-offline --no-audit

COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.ts tsconfig.json components.json ./

ENV SKIP_WAYFINDER=true
RUN npm run build

# ==============================================================================
# Stage 2: Install Composer Dependencies (Production only)
# ==============================================================================
FROM composer:2 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# ==============================================================================
# Stage 3: Production Runtime (PHP 8.4 FPM Alpine + Nginx + Supervisord)
# ==============================================================================
FROM php:8.4-fpm-alpine AS runtime

# Install runtime utilities and libraries
RUN apk add --no-cache \
    nginx \
    supervisor \
    dcron \
    curl \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    icu-libs

# Install required PHP extensions via official installer (automatically cleans build deps)
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    opcache \
    pcntl \
    bcmath \
    gd \
    intl \
    zip \
    redis && \
    rm -rf /var/cache/apk/* /tmp/*

# Copy system & service configurations
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom-php.ini
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh && \
    mkdir -p /run/nginx /var/log/supervisor /etc/crontabs

WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . .

# Copy production PHP dependencies from Stage 2
COPY --from=composer-builder --chown=www-data:www-data /app/vendor ./vendor

# Copy compiled frontend assets from Stage 1
COPY --from=frontend-builder --chown=www-data:www-data /app/public/build ./public/build

# Discover packages and setup storage permissions
RUN php artisan package:discover --ansi && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache && \
    rm -rf /var/www/html/docker

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://127.0.0.1/ || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
