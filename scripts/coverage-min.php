<?php

declare(strict_types=1);

/*
 * Fails when the coverage in a clover report is below the minimum. Pest does not apply `--min` when tests run in
 * parallel, so CI and `composer test` read the report instead. The percentage is the one Pest prints: covered lines
 * over executable lines of the whole project.
 *
 * Usage: php scripts/coverage-min.php coverage.xml 90
 */

$path = $argv[1] ?? '';
$minimum = (float) ($argv[2] ?? 0);

$report = is_file($path) ? simplexml_load_file($path) : false;
$metrics = $report === false ? null : $report->project->metrics;

$statements = (int) ($metrics['statements'] ?? 0);

if ($statements === 0) {
    echo "No coverage data in {$path}: the tests did not run with coverage.\n";
    exit(1);
}

$coverage = (int) $metrics['coveredstatements'] / $statements * 100;

echo sprintf("Coverage %.2f%% (minimum %s%%).\n", $coverage, rtrim(rtrim(number_format($minimum, 2, '.', ''), '0'), '.'));
exit($coverage >= $minimum ? 0 : 1);
