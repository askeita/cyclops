<?php
// Fallback environment setup when tests run without phpunit.xml configuration
if (!getenv('APP_ENV')) {
    putenv('APP_ENV=test');
}
if (!getenv('DATABASE_URL')) {
    putenv('DATABASE_URL=mysql://user:password@127.0.0.1:3307/cyclops_test?charset=utf8mb4&serverVersion=8.0');
}
