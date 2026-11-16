<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Serialization\Serializer;
use Symfony\Component\Process\Process;

/*
 * CI runs Unit and Feature in shards, each with its own coverage (--coverage-php). scripts/coverage-merge.php joins them
 * into the clover report that scripts/coverage-min.php reads: a line counts as covered when any shard covered it.
 */

/** A file of two executable lines and one shard's coverage of it, serialized as `--coverage-php` writes it. */
function shardCoverage(string $source, string $target, array $lines): void
{
    $driver = new class extends Driver
    {
        public function name(): string
        {
            return 'fake';
        }

        public function version(): string
        {
            return '1.0';
        }

        public function start(): void {}

        public function stop(): RawCodeCoverageData
        {
            return RawCodeCoverageData::fromLineCoverage([]);
        }
    };
    $filter = new Filter;
    $filter->includeFile($source);
    $coverage = new CodeCoverage($driver, $filter);
    $coverage->append(RawCodeCoverageData::fromLineCoverage([$source => $lines]), basename($target));

    (new Serializer)->serialize($target, $coverage);
}

it('counts a line as covered when any shard covered it', function (): void {
    $directory = sys_get_temp_dir().'/coverage-merge-'.bin2hex(random_bytes(4));
    mkdir($directory);
    // The real path, as a coverage driver reports it (macOS reaches the temporary directory through a link).
    $source = realpath($directory).'/Two.php';
    file_put_contents($source, "<?php\nfunction one(): int\n{\n    return 1;\n}\nfunction two(): int\n{\n    return 2;\n}\n");
    shardCoverage($source, "{$directory}/1.cov", [4 => 1, 8 => -1]);
    shardCoverage($source, "{$directory}/2.cov", [4 => -1, 8 => 1]);

    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/coverage-merge.php', "{$directory}/coverage.xml", "{$directory}/1.cov", "{$directory}/2.cov"]);
    $process->run();
    $clover = simplexml_load_file("{$directory}/coverage.xml");

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and((string) $clover->project->metrics['statements'])->toBe('2')
        ->and((string) $clover->project->metrics['coveredstatements'])->toBe('2');

    array_map(unlink(...), glob("{$directory}/*") ?: []);
    rmdir($directory);
});

it('fails without shards to join', function (): void {
    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/coverage-merge.php', sys_get_temp_dir().'/never.xml']);
    $process->run();

    expect($process->getExitCode())->toBe(1);
});
