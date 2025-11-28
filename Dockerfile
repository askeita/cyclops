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

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
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

# PHP-FPM runtime with Nginx (8.4)
FROM php:8.4-fpm-alpine AS runtime
WORKDIR /var/www/html

# Install Nginx, supervisor and other dependencies
RUN apk add --no-cache nginx supervisor fcgi bash procps net-tools curl coreutils netcat-openbsd

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql opcache && docker-php-ext-enable opcache || true

# Copy application files and assets
COPY --from=composer_deps /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# Symfony cache clear and permissions - Critical for Cloud Run
RUN set -eux; \
    rm -rf var/cache/* var/log/* || true; \
    mkdir -p var/cache/prod var/cache/dev var/log; \
    chmod -R 777 var/cache var/log; \
    chown -R www-data:www-data var || true; \
    chmod -R 755 /var/www/html || true; \
    APP_ENV=prod php bin/console cache:warmup --no-debug || true; \
    chmod -R 777 var/cache var/log; \
    touch var/cache/test.txt && rm var/cache/test.txt && echo "✓ Cache directory is writable" || echo "⚠ Warning: cache not writable";

# PHP configuration - use TCP for PHP-FPM (simpler and more reliable)
RUN set -eux; \
  mkdir -p /run/nginx /var/log/nginx; \
  { \
    echo "memory_limit=256M"; \
    echo "zlib.output_compression=On"; \
    echo "expose_php=Off"; \
  } > /usr/local/etc/php/conf.d/symfony.ini; \
  { \
    echo "; Allow any user to write cache/log files"; \
    echo "sys_temp_dir=/tmp"; \
  } >> /usr/local/etc/php/conf.d/symfony.ini; \
  { \
    echo "opcache.enable=1"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.interned_strings_buffer=16"; \
  } > /usr/local/etc/php/conf.d/opcache.ini; \
  # Ensure env variables are visible to PHP-FPM \
  sed -ri 's/^;?clear_env\s*=.*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf; \
  # Configure PHP-FPM to listen on TCP (more reliable) \
  sed -ri 's|^listen\s*=.*|listen = 127.0.0.1:9000|' /usr/local/etc/php-fpm.d/www.conf; \
  # Configure user and group for PHP-FPM pool \
  sed -ri 's/^;?user\s*=.*/user = www-data/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?group\s*=.*/group = www-data/' /usr/local/etc/php-fpm.d/www.conf; \
  # Increase PHP-FPM process management settings \
  sed -ri 's/^;?pm\s*=.*/pm = dynamic/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?pm.max_children\s*=.*/pm.max_children = 20/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?pm.start_servers\s*=.*/pm.start_servers = 5/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?pm.min_spare_servers\s*=.*/pm.min_spare_servers = 5/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?pm.max_spare_servers\s*=.*/pm.max_spare_servers = 10/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?pm.max_requests\s*=.*/pm.max_requests = 500/' /usr/local/etc/php-fpm.d/www.conf; \
  # Enable status page for health checks \
  sed -ri 's/^;?pm.status_path\s*=.*/pm.status_path = \/php-fpm-status/' /usr/local/etc/php-fpm.d/www.conf; \
  # Enable ping endpoint \
  sed -ri 's/^;?ping.path\s*=.*/ping.path = \/php-fpm-ping/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?ping.response\s*=.*/ping.response = pong/' /usr/local/etc/php-fpm.d/www.conf; \
  # Redirect error log to stderr \
  sed -ri 's/^;?error_log\s*=.*/error_log = \/proc\/self\/fd\/2/' /usr/local/etc/php-fpm.conf; \
  # Enable access log for debugging \
  sed -ri 's/^;?access.log\s*=.*/access.log = \/proc\/self\/fd\/2/' /usr/local/etc/php-fpm.d/www.conf; \
  # Set request timeout \
  sed -ri 's/^;?request_terminate_timeout\s*=.*/request_terminate_timeout = 60s/' /usr/local/etc/php-fpm.d/www.conf

# Nginx configuration for Cloud Run
RUN cat > /etc/nginx/http.d/default.conf <<'EOF'
server {
    listen 8080;
    server_name _;
    root /var/www/html/public;
    index index.php;

    # Increase timeouts for Cloud Run
    fastcgi_connect_timeout 5s;
    fastcgi_read_timeout 60s;
    fastcgi_send_timeout 60s;

    # Access and error logs
    access_log /dev/stdout;
    error_log /dev/stderr warn;

    # Health check endpoints
    location ~ ^/(php-fpm-status|php-fpm-ping)$ {
        fastcgi_pass 127.0.0.1:9000;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        allow 127.0.0.1;
        allow 169.254.0.0/16;
        deny all;
    }

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
childlogdir=/tmp

[unix_http_server]
file=/var/run/supervisor.sock

[supervisorctl]
serverurl=unix:///var/run/supervisor.sock

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[program:php-fpm]
command=php-fpm -F
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
autostart=true
priority=10
startsecs=15
stopwaitsecs=20
stopsignal=QUIT
killasgroup=true
stopasgroup=true

[program:nginx]
command=/bin/bash -c 'echo "Waiting for PHP-FPM on 127.0.0.1:9000..."; for i in $(seq 1 80); do if nc -z 127.0.0.1 9000 2>/dev/null; then echo "✓ PHP-FPM ready (attempt $i)"; exec nginx -g "daemon off;"; fi; echo "Attempt $i/80: php-fpm not ready"; sleep 0.25; done; echo "✗ ERROR: PHP-FPM socket not ready after 20s"; ps aux | grep php-fpm; netstat -tlnp || ss -tlnp; exit 1'
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
autostart=true
priority=20
startsecs=0
stopwaitsecs=10
stopsignal=QUIT
killasgroup=true
stopasgroup=true
EOF

# Create entrypoint script
RUN cat > /entrypoint.sh <<'EOF'
#!/bin/bash
set -e

# Export environment variables (defaults are set via ENV in Dockerfile)
export APP_ENV APP_DEBUG TRUSTED_HOSTS TRUSTED_PROXIES

echo "=========================================="
echo "Starting Cyclops application..."
echo "=========================================="

echo "Environment: $APP_ENV"
echo "Debug mode: $APP_DEBUG"
echo "Trusted hosts: $TRUSTED_HOSTS"
echo "Trusted proxies: $TRUSTED_PROXIES"
echo "PHP version: $(php -v | head -n 1)"
echo ""

# Test Nginx configuration
echo "Testing Nginx configuration..."
if nginx -t 2>&1; then
    echo "✓ Nginx configuration is valid"
else
    echo "✗ Nginx configuration test failed!"
    exit 1
fi
echo ""

# Test PHP-FPM configuration
echo "Testing PHP-FPM configuration..."
if php-fpm -t 2>&1; then
    echo "✓ PHP-FPM configuration is valid"
else
    echo "✗ PHP-FPM configuration test failed!"
    exit 1
fi
echo ""

# Verify PHP-FPM listen configuration
echo "Verifying PHP-FPM listen configuration..."
if grep -q "listen = 127.0.0.1:9000" /usr/local/etc/php-fpm.d/www.conf; then
    echo "✓ PHP-FPM is configured to listen on 127.0.0.1:9000"
else
    echo "✗ PHP-FPM listen configuration is incorrect!"
    grep "^listen" /usr/local/etc/php-fpm.d/www.conf || true
    exit 1
fi
echo ""

# Ensure directories exist with correct permissions (CRITICAL for Cloud Run)
echo "Checking directories and permissions..."
mkdir -p var/cache/prod var/cache/dev var/log /run/nginx /var/run 2>/dev/null || true

# CRITICAL: Force 777 permissions recursively on cache and log
# Cloud Run may run as arbitrary UID, so we need world-writable dirs
echo "Setting permissions on var/cache and var/log..."
chmod -R 777 var/cache var/log 2>/dev/null || true

# Try to set ownership, but don't fail if we can't (Cloud Run restrictions)
chown -R www-data:www-data var 2>/dev/null || true

# Verify write permissions by testing
echo "Verifying write permissions..."
if touch var/cache/.test 2>/dev/null && rm var/cache/.test 2>/dev/null; then
    echo "✓ var/cache is writable"
else
    echo "✗ ERROR: var/cache is NOT writable!"
    ls -la var/ 2>/dev/null || true
    ls -la var/cache/ 2>/dev/null || true
    # Try to fix permissions one more time
    chmod -R 777 var/cache 2>/dev/null || true
fi

if touch var/log/.test 2>/dev/null && rm var/log/.test 2>/dev/null; then
    echo "✓ var/log is writable"
else
    echo "✗ ERROR: var/log is NOT writable!"
    ls -la var/log/ 2>/dev/null || true
    chmod -R 777 var/log 2>/dev/null || true
fi
echo ""

# Check if cache is already warmed up (from build time)
if [ "$APP_ENV" = "prod" ] && [ ! -f var/cache/prod/.warmup-done ]; then
    echo "Production cache not found or incomplete, warming up..."

    # Ensure prod cache directory exists with full permissions
    mkdir -p var/cache/prod 2>/dev/null || true
    chmod -R 777 var/cache/prod 2>/dev/null || true

    # Warm up cache
    if php bin/console cache:warmup --env=prod --no-debug 2>&1; then
        echo "✓ Cache warmed up successfully"
        touch var/cache/prod/.warmup-done 2>/dev/null || true
    else
        echo "⚠ Cache warmup failed, but continuing..."
    fi

    # CRITICAL: Re-apply full permissions after warmup
    chmod -R 777 var/cache var/log 2>/dev/null || true
    echo ""
else
    echo "✓ Production cache already warmed up"
fi

echo "=========================================="
echo "Starting services via Supervisord..."
echo "=========================================="
# Display basic pool config
echo "PHP-FPM pool configuration summary:"
grep -E "^(listen|pm|user|group)" /usr/local/etc/php-fpm.d/www.conf | head -20 || true
echo ""

echo "Launching supervisord (php-fpm then nginx)..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
EOF

RUN chmod +x /entrypoint.sh

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    TRUSTED_HOSTS="^(cyclops-api\.online|.*\.run\.app|localhost|127\.0\.0\.1)$" \
    TRUSTED_PROXIES="10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,169.254.0.0/16"
EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
