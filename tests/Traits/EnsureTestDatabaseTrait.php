<?php
namespace App\Tests\Traits;

/**
 * EnsureTestDatabaseTrait
 * Initialise et force la présence de DATABASE_URL pour les tests (MySQL).
 */
trait EnsureTestDatabaseTrait
{
    protected static function ensureTestDatabaseEnv(): void
    {
        // Detect if running inside Docker container or on host
        $isDocker = file_exists('/.dockerenv') || getenv('DOCKER_ENV') === 'true';

        // Use appropriate host and port based on environment
        if ($isDocker) {
            // Inside Docker: use service name
            $host = 'mysql';
            $port = 3306;
        } else {
            // On host: use localhost with exposed port
            $host = '127.0.0.1';
            $port = 3307;
        }

        // Force environment variables for tests
        $url = "mysql://user:password@{$host}:{$port}/cyclops_test?charset=utf8mb4&serverVersion=8.0";
        putenv('APP_ENV=test'); $_ENV['APP_ENV']='test'; $_SERVER['APP_ENV']='test';
        putenv('DATABASE_URL='.$url); $_ENV['DATABASE_URL']=$url; $_SERVER['DATABASE_URL']=$url;
        putenv('APP_SECRET=test_secret'); $_ENV['APP_SECRET']='test_secret'; $_SERVER['APP_SECRET']='test_secret';

        // Attempt to create the test database
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new \PDO($dsn, 'user', 'password', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS cyclops_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable $e) {
            // Signal to skip DB tests if creation fails
            putenv('SKIP_DB_TESTS=1'); $_ENV['SKIP_DB_TESTS']='1'; $_SERVER['SKIP_DB_TESTS']='1';
        }
    }
}
