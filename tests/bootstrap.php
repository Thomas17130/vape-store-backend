<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Load .env.test file
if (file_exists(dirname(__DIR__).'/.env.test')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env.test');
}

// Set test environment
if (!isset($_ENV['APP_ENV'])) {
    $_ENV['APP_ENV'] = 'test';
}

if (!isset($_SERVER['APP_ENV'])) {
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'];
}
