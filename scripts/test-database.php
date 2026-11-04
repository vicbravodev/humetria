<?php

declare(strict_types=1);

/*
 * The test database of this checkout. Parallel worktrees share one Postgres, and RefreshDatabase runs migrate:fresh,
 * so two sessions on `testing` drop each other's tables (deadlocks, "relation does not exist"). The main checkout
 * keeps `testing`; a worktree gets `testing_<directory>_<hash of its path>`. tests/bootstrap.php applies it.
 */

/**
 * Short enough for the `_test_N` suffix parallel workers add (Postgres cuts identifiers at 63 characters).
 */
function testDatabaseName(string $basePath, bool $isWorktree): string
{
    if (! $isWorktree) {
        return 'testing';
    }

    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(basename($basePath))), '_');

    return 'testing_'.substr($slug, 0, 30).'_'.hash('crc32b', $basePath);
}

/**
 * Creates the database when it does not exist yet, so a fresh worktree needs no manual step.
 *
 * @param  array{host: string, port: string, username: string, password: string}  $server
 */
function ensureTestDatabase(string $name, array $server): void
{
    $pdo = new PDO("pgsql:host={$server['host']};port={$server['port']};dbname=postgres", $server['username'], $server['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $exists = $pdo->prepare('select 1 from pg_database where datname = ?');
    $exists->execute([$name]);

    if ($exists->fetchColumn() === false) {
        $pdo->exec('create database "'.$name.'"');
    }
}
