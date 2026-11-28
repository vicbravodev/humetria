<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * Pest splits the CI shards by the time of each test class in tests/.pest/shards.json. scripts/shard-timings.php writes
 * those times from the JUnit reports the CI shards keep (`--log-junit`), so the split follows what CI measured and not
 * a local run where Browser and Feature fought for the same CPU (that run gave the demo's stories 1270 s for 146 s).
 */

/** A JUnit report as Pest writes it: a class is a testsuite of testcases, each with its time. */
function junitReport(string $path, array $cases): void
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?><testsuites><testsuite name="">';

    foreach ($cases as [$class, $time]) {
        $xml .= sprintf('<testsuite name="%1$s"><testcase name="it works" class="%1$s" time="%2$s"/></testsuite>', $class, $time);
    }

    file_put_contents($path, $xml.'</testsuite></testsuites>');
}

/** @return array{Process, string} the script's run and the directory of its files */
function shardTimings(array $reports, ?array $current = null): array
{
    $directory = sys_get_temp_dir().'/shard-timings-'.bin2hex(random_bytes(4));
    mkdir($directory);
    $paths = [];

    foreach ($reports as $index => $cases) {
        junitReport($paths[] = "{$directory}/junit-{$index}.xml", $cases);
    }

    if ($current !== null) {
        file_put_contents("{$directory}/shards.json", json_encode(['timings' => $current]));
    }

    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/shard-timings.php', "{$directory}/shards.json", ...$paths]);
    $process->run();

    return [$process, $directory];
}

/** @return array<string, mixed> */
function shardsFile(string $directory): array
{
    $data = json_decode((string) file_get_contents("{$directory}/shards.json"), true);

    return is_array($data) ? $data : [];
}

it('adds up the time of every case of a class, across reports', function (): void {
    [$process, $directory] = shardTimings([
        [['Tests\Feature\BTest', '1.25'], ['Tests\Feature\ATest', '0.5']],
        [['Tests\Feature\BTest', '0.75']],
    ]);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(shardsFile($directory)['timings'])->toBe(['Tests\Feature\ATest' => 0.5, 'Tests\Feature\BTest' => 2.0]);
});

it('writes the file as Pest does: classes in order, with a checksum and the date', function (): void {
    [, $directory] = shardTimings([[['Tests\Unit\ZTest', '1'], ['Tests\Browser\ATest', '2']]]);
    $file = shardsFile($directory);

    expect(array_keys($file))->toBe(['timings', 'checksum', 'updated_at'])
        ->and(array_keys($file['timings']))->toBe(['Tests\Browser\ATest', 'Tests\Unit\ZTest'])
        ->and($file['checksum'])->toBe(md5("Tests\\Browser\\ATest\nTests\\Unit\\ZTest"))
        ->and($file['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

it('keeps the time of a class the reports do not bring, so Browser and Feature can be measured apart', function (): void {
    [, $directory] = shardTimings([[['Tests\Feature\ATest', '3']]], ['Tests\Browser\PagesTest' => 40.0, 'Tests\Feature\ATest' => 1270.0]);

    expect(shardsFile($directory)['timings'])->toBe(['Tests\Browser\PagesTest' => 40.0, 'Tests\Feature\ATest' => 3.0]);
});

it('refuses to run without a report', function (): void {
    [$process, $directory] = shardTimings([]);

    expect($process->getExitCode())->toBe(1)
        ->and(file_exists("{$directory}/shards.json"))->toBeFalse();
});
