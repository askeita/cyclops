# PHP dependencies (composer, symfony)
FROM composer:2 AS composer_deps
WORKDIR /app
COPY composer.json composer.lock* symfony.lock* ./

# Copy bin/console before composer install to avoid cache:clear errors
COPY bin/ ./bin/
COPY config/ ./config/
COPY src/ ./src/
COPY public/ ./public/

# Copy only production and test env files (exclude .env.dev and .env.local)
COPY .env .env.test ./

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

# PHP-FPM runtime with Nginx (8.4)
FROM php:8.4-fpm-alpine AS runtime
WORKDIR /var/www/html

# Install Nginx, supervisor and other dependencies
RUN apk add --no-cache nginx supervisor fcgi bash procps

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

# PHP configuration
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
  sed -ri 's/^;?clear_env\s*=.*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf; \
  # Configure PHP-FPM to listen on TCP \
  sed -ri 's|^listen\s*=.*|listen = 127.0.0.1:9000|' /usr/local/etc/php-fpm.d/www.conf; \
  # Increase PHP-FPM process management settings \
  sed -ri 's/^;?pm\s*=.*/pm = dynamic/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?pm.max_children\s*=.*/pm.max_children = 20/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?pm.start_servers\s*=.*/pm.start_servers = 5/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?pm.min_spare_servers\s*=.*/pm.min_spare_servers = 5/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?pm.max_spare_servers\s*=.*/pm.max_spare_servers = 10/' /usr/local/etc/php-fpm.d/www.conf; \
  # Disable error log to avoid duplication with supervisord \
  sed -ri 's/^;?error_log\s*=.*/error_log = \/proc\/self\/fd\/2/' /usr/local/etc/php-fpm.conf

# Nginx configuration for Cloud Run
RUN mkdir -p /run/nginx /var/log/nginx && \
    cat > /etc/nginx/http.d/default.conf <<'EOF'
server {
    listen 8080;
    server_name _;
    root /var/www/html/public;
    index index.php;

    # Increase timeouts for Cloud Run
    fastcgi_read_timeout 60s;
    fastcgi_send_timeout 60s;

    # Access and error logs
    access_log /dev/stdout;
    error_log /dev/stderr warn;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $document_root;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~* \.(?:css|js|ico|gif|jpe?g|png|svg|woff2?|ttf)$ {
        expires 1y;
        access_log off;
        try_files $uri =404;
    }
}
EOF

# Supervisor configuration
RUN cat > /etc/supervisord.conf <<'EOF'
[supervisord]
nodaemon=true
user=root
logfile=/dev/stdout
logfile_maxbytes=0
loglevel=info
pidfile=/var/run/supervisord.pid

[unix_http_server]
file=/var/run/supervisor.sock

[supervisorctl]
serverurl=unix:///var/run/supervisor.sock

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[program:php-fpm]
command=php-fpm -F -R
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
priority=10
startsecs=0
stopwaitsecs=10
stopsignal=QUIT

[program:nginx]
command=/bin/sh -c 'sleep 3 && nginx -g "daemon off;"'
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
priority=20
startsecs=0
stopwaitsecs=10
stopsignal=QUIT
EOF

# Create entrypoint script
RUN cat > /entrypoint.sh <<'EOF'
#!/bin/sh
set -e

echo "Starting Cyclops application..."

# Test Nginx configuration
echo "Testing Nginx configuration..."
nginx -t

# Test PHP-FPM configuration
echo "Testing PHP-FPM configuration..."
php-fpm -t

# Warm up Symfony cache if needed
if [ "$APP_ENV" = "prod" ]; then
    echo "Warming up Symfony cache..."
    php bin/console cache:warmup --env=prod --no-debug || true
fi

echo "Starting services via Supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
EOF

RUN chmod +x /entrypoint.sh

ENV APP_ENV=prod APP_DEBUG=0
EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
