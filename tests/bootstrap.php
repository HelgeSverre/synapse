<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env file if it exists
$envFile = __DIR__.'/../.env';

if (file_exists($envFile)) {
    $dotenv = Dotenv::createImmutable(__DIR__.'/..');
    $dotenv->load();
}
