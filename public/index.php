<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';

// Load environment variables from .env file, if it exists; otherwise, rely on the server environment
$envFile = dirname(__DIR__).'/.env';
if (is_readable($envFile)) {
    (new Dotenv())->loadEnv($envFile);
} else {
    // Fallback to loading from server environment
    $_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'dev';
    $_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? '1';
}

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
