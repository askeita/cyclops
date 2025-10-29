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

// Force loading of test environment variables
if (file_exists(dirname(__DIR__).'/.env.test')) {
    (new Dotenv())->load(dirname(__DIR__).'/.env.test');
}

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
