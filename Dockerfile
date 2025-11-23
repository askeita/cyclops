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
RUN apk add --no-cache nginx supervisor fcgi bash procps net-tools curl

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
  # Configure PHP-FPM to listen on UNIX socket (more reliable than TCP) \
  sed -ri 's|^listen\s*=.*|listen = /var/run/php-fpm.sock|' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?listen.owner\s*=.*/listen.owner = www-data/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?listen.group\s*=.*/listen.group = www-data/' /usr/local/etc/php-fpm.d/www.conf; \
  sed -ri 's/^;?listen.mode\s*=.*/listen.mode = 0660/' /usr/local/etc/php-fpm.d/www.conf; \
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
RUN mkdir -p /run/nginx /var/log/nginx && \
    cat > /etc/nginx/http.d/default.conf <<'EOF'
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
        fastcgi_pass unix:/var/run/php-fpm.sock;
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
        fastcgi_pass unix:/var/run/php-fpm.sock;
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
startsecs=5
stopwaitsecs=10
stopsignal=QUIT
killasgroup=true
stopasgroup=true

[program:nginx]
command=/bin/bash -c 'echo "Waiting for PHP-FPM socket to be ready..."; for i in $(seq 1 80); do if [ -S /var/run/php-fpm.sock ]; then echo "PHP-FPM socket found! Testing connection..."; if cgi-fcgi -bind -connect /var/run/php-fpm.sock 2>/dev/null || [ -w /var/run/php-fpm.sock ]; then echo "PHP-FPM is ready!"; nginx -g "daemon off;"; exit 0; fi; fi; echo "Attempt $i/80: PHP-FPM not ready yet..."; sleep 0.25; done; echo "ERROR: PHP-FPM failed to start after 20 seconds"; exit 1'
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
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

echo "=========================================="
echo "Starting Cyclops application..."
echo "=========================================="

# Display environment info
echo "Environment: $APP_ENV"
echo "Debug mode: $APP_DEBUG"
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
if grep -q "listen = /var/run/php-fpm.sock" /usr/local/etc/php-fpm.d/www.conf; then
    echo "✓ PHP-FPM is configured to listen on /var/run/php-fpm.sock"
else
    echo "✗ PHP-FPM listen configuration is incorrect!"
    grep "^listen" /usr/local/etc/php-fpm.d/www.conf
    exit 1
fi
echo ""

# Test PHP-FPM startup manually first
echo "Pre-starting PHP-FPM to verify it works..."
php-fpm -t && echo "✓ PHP-FPM configuration test passed"
timeout 5s php-fpm -F &
PHP_FPM_PID=$!
sleep 2

# Check if PHP-FPM started successfully
if kill -0 $PHP_FPM_PID 2>/dev/null; then
    echo "✓ PHP-FPM started successfully (PID: $PHP_FPM_PID)"
    kill $PHP_FPM_PID 2>/dev/null || true
    wait $PHP_FPM_PID 2>/dev/null || true
    sleep 1
else
    echo "✗ PHP-FPM failed to start!"
    exit 1
fi
echo ""

# Ensure directories exist with correct permissions
echo "Checking directories and permissions..."
mkdir -p var/cache var/log /run/nginx /var/run
chown -R www-data:www-data var/cache var/log || true
chmod -R 775 var/cache var/log || true
echo "✓ Directories and permissions are set"
echo ""

# Warm up Symfony cache if needed
if [ "$APP_ENV" = "prod" ]; then
    echo "Warming up Symfony cache for production..."
    php bin/console cache:warmup --env=prod --no-debug || {
        echo "⚠ Cache warmup failed, but continuing..."
    }
    echo ""
fi

echo "=========================================="
echo "Starting services via Supervisord..."
echo "=========================================="
exec /usr/bin/supervisord -c /etc/supervisord.conf
EOF

RUN chmod +x /entrypoint.sh

ENV APP_ENV=prod APP_DEBUG=0
EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
