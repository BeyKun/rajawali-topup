# ==============================================================================
# Stage 1: Vendor Dependencies (Composer)
# ==============================================================================
FROM composer:2 AS composer-builder

WORKDIR /app

# Leverage Docker cache: copy manifest files first
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --no-progress \
    --ignore-platform-reqs \
    --prefer-dist

# Copy application source code for authoritative autoload classmap generation
COPY . .

RUN composer dump-autoload \
    --optimize \
    --classmap-authoritative \
    --no-dev \
    --ignore-platform-reqs

# Generate Wayfinder TypeScript routes, actions, and helpers needed by Vite
RUN php artisan wayfinder:generate --with-form

# ==============================================================================
# Stage 2: Frontend Assets Builder (Node.js)
# ==============================================================================
FROM node:22-bookworm-slim AS frontend-builder

WORKDIR /app

# Copy package definitions first for optimal layer caching
COPY package*.json ./

# Use npm ci for deterministic, secure, and fast clean installs
RUN npm ci --prefer-offline --no-audit --progress=false

# Copy source code
COPY . .

# Copy generated Wayfinder artifacts from composer-builder (ensuring routes/actions exist in clean git clones)
COPY --from=composer-builder /app/resources/js/actions ./resources/js/actions
COPY --from=composer-builder /app/resources/js/routes ./resources/js/routes
COPY --from=composer-builder /app/resources/js/wayfinder ./resources/js/wayfinder

ENV SKIP_WAYFINDER=true \
    NODE_ENV=production

RUN npm run build

# ==============================================================================
# Stage 3: Production Runtime (PHP 8.4-FPM + Nginx)
# ==============================================================================
FROM php:8.4-fpm-bookworm AS production

LABEL maintainer="Rajawali Topup"

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=Asia/Jakarta

# Use official PHP production configuration as baseline
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Install system runtime dependencies, Nginx, Supervisor
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    git \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        pdo_mysql \
        gd \
        zip \
        intl \
        bcmath \
        exif \
        pcntl \
        opcache

# Install and enable Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear /tmp/* /var/tmp/*

# Copy Composer binary from official image for runtime artisan/package operations
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy custom configurations
COPY docker/nginx.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Copy application code with www-data ownership to eliminate layer duplication
COPY --chown=www-data:www-data . /var/www/html

# Copy prebuilt vendor and frontend assets from builder stages
COPY --chown=www-data:www-data --from=composer-builder /app/vendor /var/www/html/vendor
COPY --chown=www-data:www-data --from=frontend-builder /app/public/build /var/www/html/public/build

# Ensure storage and bootstrap directories exist with proper permissions
RUN mkdir -p /var/www/html/storage/app/public \
             /var/www/html/storage/app/private \
             /var/www/html/storage/framework/cache/data \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/storage/logs \
             /var/www/html/bootstrap/cache \
    && rm -f /var/www/html/bootstrap/cache/*.php \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && rm -rf /var/www/html/docker

# Healthcheck targeting Laravel's built-in /up endpoint
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://127.0.0.1/up || curl -f http://127.0.0.1/ || exit 1

EXPOSE 80

STOPSIGNAL SIGTERM

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
