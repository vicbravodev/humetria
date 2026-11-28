<?php

declare(strict_types=1);

/*
 * Writes the time of each test class into tests/.pest/shards.json, which Pest reads to split the CI shards (`--shard`),
 * from the JUnit reports the shards of the CI keep (`--log-junit`, the artifacts `timings-*` of tests.yml). A class the
 * reports do not bring keeps its time: Unit/Feature and Browser can be measured apart. Pest's own `--update-shards`
 * rewrites the whole file from one run, and a run of both suites at once measures their fight for the CPU.
 *
 * Usage: php scripts/shard-timings.php tests/.pest/shards.json junit-1.xml [junit-2.xml …]
 */

$arguments = $argv ?? [];
$target = $arguments[1] ?? '';
$reports = array_values(array_filter(array_slice($arguments, 2), fn (string $path): bool => $path !== ''));

if ($target === '' || $reports === []) {
    fwrite(STDERR, "Usage: php scripts/shard-timings.php tests/.pest/shards.json junit-1.xml [junit-2.xml …]\n");
    exit(1);
}

$current = is_file($target) ? json_decode((string) file_get_contents($target), true) : null;
$timings = [];

foreach (is_array($current) && is_array($current['timings'] ?? null) ? $current['timings'] : [] as $class => $time) {
    $timings[(string) $class] = is_numeric($time) ? (float) $time : 0.0;
}

$measured = [];

foreach ($reports as $report) {
    $xml = simplexml_load_file($report);

    if ($xml === false) {
        fwrite(STDERR, "Cannot read the JUnit report {$report}.\n");
        exit(1);
    }

    foreach ($xml->xpath('//testcase') ?: [] as $case) {
        $class = (string) $case['class'];
        $measured[$class] = ($measured[$class] ?? 0.0) + (float) $case['time'];
    }
}

$timings = array_map(fn (float $time): float => round($time, 3), [...$timings, ...$measured]);
ksort($timings);

file_put_contents($target, json_encode([
    'timings' => $timings,
    'checksum' => md5(implode("\n", array_keys($timings))),
    'updated_at' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n");

echo sprintf("Wrote the time of %d class(es) from %d report(s) into %s.\n", count($measured), count($reports), $target);
