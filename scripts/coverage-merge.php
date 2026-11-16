<?php

declare(strict_types=1);

/*
 * Joins the coverage of the CI shards (each one runs `pest --shard=i/n --coverage-php=…`) into one clover report, the
 * one scripts/coverage-min.php reads. php-code-coverage brings the merger; the shards run on the same commit, so the git
 * information is not compared (each checkout carries its own untracked files).
 *
 * Usage: php scripts/coverage-merge.php coverage.xml shard-1.cov shard-2.cov shard-3.cov
 */

use SebastianBergmann\CodeCoverage\Report\Facade;
use SebastianBergmann\CodeCoverage\Serialization\Merger;

require __DIR__.'/../vendor/autoload.php';

$arguments = $argv ?? [];
$target = $arguments[1] ?? '';
$shards = array_values(array_filter(array_slice($arguments, 2), fn (string $path): bool => $path !== ''));

if ($target === '' || $shards === []) {
    echo "Usage: php scripts/coverage-merge.php coverage.xml shard-1.cov [shard-2.cov …]\n";
    exit(1);
}

Facade::fromSerializedData((new Merger)->merge($shards, false))->renderClover($target);

echo sprintf("Merged %d coverage report(s) into %s.\n", count($shards), $target);
