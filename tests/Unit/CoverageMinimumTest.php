<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * Coverage runs in parallel (several times faster) and `--min` is not applied there, so scripts/coverage-min.php reads
 * the clover report and fails the run below the minimum. The percentage is the one Pest prints: covered / executable lines.
 */

function cloverWith(int $statements, int $covered): string
{
    return '<?xml version="1.0"?><coverage><project><file name="a.php"><metrics statements="1" coveredstatements="1"/></file>'
        ."<metrics files=\"1\" statements=\"{$statements}\" coveredstatements=\"{$covered}\"/></project></coverage>";
}

function runCoverageMin(string $clover, string $min): Process
{
    $path = tempnam(sys_get_temp_dir(), 'clover');
    file_put_contents($path, $clover);

    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/coverage-min.php', $path, $min], dirname(__DIR__, 2));
    $process->run();
    unlink($path);

    return $process;
}

it('passes when the coverage is above the minimum', function (): void {
    $process = runCoverageMin(cloverWith(1000, 950), '90');

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('95.00%');
});

it('passes at exactly the minimum', function (): void {
    expect(runCoverageMin(cloverWith(1000, 900), '90')->getExitCode())->toBe(0);
});

it('fails one line below the minimum', function (): void {
    $process = runCoverageMin(cloverWith(1000, 899), '90');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('89.90%')
        ->and($process->getOutput())->toContain('minimum 90');
});

it('fails when the report has no executable lines, instead of passing silently', function (): void {
    expect(runCoverageMin(cloverWith(0, 0), '90')->getExitCode())->toBe(1);
});

it('fails on a report it cannot read', function (): void {
    expect(runCoverageMin('not xml', '90')->getExitCode())->toBe(1);
});
