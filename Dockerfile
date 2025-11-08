# PHP dependencies (composer, symfony)
FROM composer:2 AS composer_deps
WORKDIR /app
COPY composer.json composer.lock* symfony.lock* ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-progress --no-interaction
COPY . .

# Build assets (node, yarn)
FROM node:20-alpine AS assets
WORKDIR /app
# ux-vue dependencies (node_modules)
COPY --from=composer_deps /app/vendor /app/vendor
COPY package.json yarn.lock* ./
COPY assets ./assets
COPY webpack.config.js ./
RUN corepack enable && yarn install --frozen-lockfile && yarn build

# Nginx + PHP-FPM runtime
FROM php:8.2-fpm-alpine AS runtime
WORKDIR /var/www/html

# Nginx + bash + curl
RUN apk add --no-cache nginx bash curl

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql opcache && docker-php-ext-enable opcache || true

# Copy application files and assets
COPY --from=composer_deps /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# Symfony cache clear
RUN rm -rf var/cache/* var/log/*

# Permissions and cache/log directories
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod -R 755 /var/www/html

RUN set -eux; \
  { \
    echo "memory_limit=256M"; \
    echo "zlib.output_compression=On"; \
    echo "expose_php=Off"; \
  } > /usr/local/etc/php/conf.d/symfony.ini; \
  { \
    echo "opcache.enable=1"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.interned_strings_buffer=16"; \
  } > /usr/local/etc/php/conf.d/opcache.ini; \
  # Ensure env variables are visible to PHP-FPM \
  sed -ri 's/^;?clear_env\s*=.*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf

# Nginx configuration
RUN mkdir -p /run/nginx /etc/nginx/http.d
COPY nginx.conf /etc/nginx/http.d/default.conf

# PHP-FPM and Nginx startup script
COPY start.sh /start.sh
RUN chmod +x /start.sh

ENV APP_ENV=prod APP_DEBUG=0 PORT=8080
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=3s --start-period=20s CMD wget --no-verbose --tries=1 --spider http://localhost:8080 || exit 1
CMD ["/start.sh"]
