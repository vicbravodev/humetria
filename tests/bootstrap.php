<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/../scripts/test-database.php';

/*
 * Each checkout tests against its own database (see scripts/test-database.php), created on first use. An explicit
 * DB_DATABASE in the shell wins, which is how CI and one-off runs choose theirs.
 */
$base = dirname(__DIR__);
$dotenv = Dotenv::createArrayBacked($base)->safeLoad();

$database = getenv('DB_DATABASE') ?: testDatabaseName($base, is_file($base.'/.git'));

putenv("DB_DATABASE={$database}");
$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $database;

ensureTestDatabase($database, [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '5433',
    'username' => getenv('DB_USERNAME') ?: ($dotenv['DB_USERNAME'] ?? 'sail'),
    'password' => getenv('DB_PASSWORD') ?: ($dotenv['DB_PASSWORD'] ?? 'password'),
]);
