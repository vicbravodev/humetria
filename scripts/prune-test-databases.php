<?php

declare(strict_types=1);

require __DIR__.'/test-database.php';

/*
 * Lists, or with --drop deletes, the test databases of worktrees that no longer exist.
 *
 * Usage: composer worktree:prune [-- --drop]
 */

$paths = [];
preg_match_all('/^worktree (.+)$/m', (string) shell_exec('git worktree list --porcelain'), $matches);

foreach ($matches[1] as $path) {
    $paths[] = testDatabaseName($path, is_file($path.'/.git'));
}

$pdo = new PDO(
    'pgsql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';port='.(getenv('DB_PORT') ?: '5433').';dbname=postgres',
    getenv('DB_USERNAME') ?: 'sail',
    getenv('DB_PASSWORD') ?: 'password',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$statement = $pdo->prepare("select datname from pg_database where datname like 'testing\\_%' order by datname");
$statement->execute();

$databases = [];

foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $database) {
    if (is_string($database)) {
        $databases[] = $database;
    }
}

$stale = staleTestDatabases($databases, $paths);
$drop = array_key_exists('drop', getopt('', ['drop']) ?: []);

foreach ($stale as $database) {
    if ($drop) {
        $pdo->exec('drop database "'.$database.'" with (force)');
    }

    echo ($drop ? 'dropped ' : 'stale   ').$database."\n";
}

echo $stale === [] ? "No stale test databases.\n" : ($drop ? '' : "Run with --drop to delete them.\n");
