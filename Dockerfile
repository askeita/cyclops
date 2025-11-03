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

# Symfony permissions
RUN mkdir -p var/cache var/log && chown -R www-data:www-data var

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
RUN set -eux; \
  mkdir -p /run/nginx /etc/nginx/http.d; \
  cat > /etc/nginx/http.d/default.conf <<'EOF'
server {
  listen 8080;
  server_name _;
  root /var/www/html/public;
  index index.php index.html;

  access_log /var/log/nginx/access.log;
  error_log /var/log/nginx/error.log;

  location / {
    try_files $uri /index.php$is_args$args;
  }

  location ~ \.php$ {
    try_files $uri = 404;
    include /etc/nginx/fastcgi_params;
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $realpath_root;
    fastcgi_read_timeout 60s;
  }

  location ~* \.(?:css|js|ico|gif|jpe?g|png|svg|woff2?|ttf)$ {
    expires 1y;
    access_log off;
    try_files $uri = 404;
  }
}
EOF

# PHP-FPM and Nginx startup script
RUN set -eux; \
  cat > /start.sh <<'EOF'
#!/usr/bin/env sh
set -e
php-fpm -D
exec nginx -g 'daemon off;'
EOF

# Make startup script executable
RUN chmod +x /start.sh

ENV APP_ENV=prod APP_DEBUG=0 PORT=8080
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=3s --start-period=20s CMD wget -q0 https://cyclops-api.online:8080 || exit 1
CMD ["/start.sh"]
