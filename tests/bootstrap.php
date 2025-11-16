<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Polyfill for #[\Deprecated] attribute on PHP < 8.3
if (!class_exists('Deprecated')) {
    #[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::TARGET_FUNCTION)]
    class Deprecated
    {
        public function __construct(public ?string $message = null)
        {
        }
    }
}

// Load .env.test if it exists
if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
if (file_exists(dirname(__DIR__).'/.env.test')) {
    (new Dotenv())->load(dirname(__DIR__).'/.env.test');
}

// Fallback ENCRYPTION_KEY si absent (exécution isolée du test sans phpunit.xml.dist)
if (!($_ENV['ENCRYPTION_KEY'] ?? $_SERVER['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY'))) {
    $defaultKey = 'test_encryption_key_for_tests';
    putenv('ENCRYPTION_KEY='.$defaultKey); $_ENV['ENCRYPTION_KEY']=$defaultKey; $_SERVER['ENCRYPTION_KEY']=$defaultKey;
}

// Fallback APP_SECRET si absent
if (!($_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? getenv('APP_SECRET'))) {
    $defaultSecret = 's$cretf0rt3st';
    putenv('APP_SECRET='.$defaultSecret); $_ENV['APP_SECRET']=$defaultSecret; $_SERVER['APP_SECRET']=$defaultSecret;
}

// Forcer APP_ENV test si non défini
if (!($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null)) {
    putenv('APP_ENV=test'); $_ENV['APP_ENV']='test'; $_SERVER['APP_ENV']='test';
}

// If in test environment, ensure DATABASE_URL is set for tests
if (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null) === 'test') {
    if (!($_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null)) {
        // Detect if running inside Docker container or on host
        $isDocker = file_exists('/.dockerenv') || getenv('DOCKER_ENV') === 'true';

        if ($isDocker) {
            // Inside Docker: use service name
            $host = 'mysql';
            $port = 3306;
        } else {
            // On host: use localhost with exposed port
            $host = '127.0.0.1';
            $port = 3307;
        }

        $url = "mysql://user:password@{$host}:{$port}/cyclops_test?charset=utf8mb4&serverVersion=8.0";
        putenv('DATABASE_URL='.$url); $_ENV['DATABASE_URL']=$url; $_SERVER['DATABASE_URL']=$url;
    }

    // Create test database if possible
    if (!getenv('SKIP_DB_TESTS')) {
        try {
            // Detect if running inside Docker container or on host
            $isDocker = file_exists('/.dockerenv') || getenv('DOCKER_ENV') === 'true';

            if ($isDocker) {
                $dbHost = 'mysql';
                $dbPort = 3306;
            } else {
                $dbHost = '127.0.0.1';
                $dbPort = 3307;
            }

            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new \PDO($dsn, 'user', 'password', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS cyclops_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable $e) {
            putenv('SKIP_DB_TESTS=1'); $_ENV['SKIP_DB_TESTS']='1'; $_SERVER['SKIP_DB_TESTS']='1';
        }
    }
}

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
}
