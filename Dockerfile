# PHP dependencies (composer, symfony)
FROM composer:2 AS composer_deps
WORKDIR /app
COPY composer.json composer.lock* symfony.lock* ./
# Copy bin/console before composer install to avoid cache:clear errors
COPY bin/ ./bin/
COPY config/ ./config/
COPY src/ ./src/
COPY public/ ./public/
RUN composer install --prefer-dist --no-progress --no-interaction --no-scripts
COPY . .
# Now run post-install scripts with all files in place
RUN composer run-script post-install-cmd || true

# Build assets (node, yarn)
FROM node:20-alpine AS assets
WORKDIR /app
# ux-vue dependencies (node_modules)
COPY --from=composer_deps /app/vendor /app/vendor
COPY package.json yarn.lock* ./
COPY assets ./assets
COPY webpack.config.js ./
RUN corepack enable && yarn install --frozen-lockfile && yarn build

# PHP-FPM runtime (8.4)
FROM php:8.4-fpm-alpine AS runtime
WORKDIR /var/www/html

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

ENV APP_ENV=prod APP_DEBUG=0
EXPOSE 9000
CMD ["php-fpm"]
