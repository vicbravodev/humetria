<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/*
 * The required check of main (tests.yml, job ci) now that a change of documentation alone skips the pipeline:
 * scripts/ci-verdict.sh decides from the results of the jobs, every other job waits for the job changes, and deploy.yml
 * follows the same decision. None of it can run here: these tests fail the day a job is added without the condition, or
 * the verdict starts passing a job that did not run.
 */

/**
 * Whether the ci job passes with these results, given as GitHub gives the `needs` context of the job.
 *
 * @param  array<string, string>  $results  each job the ci job waits for, with its result
 * @param  string|null  $code  what the job changes answered, or null when it answered nothing
 */
function ciPasses(array $results, ?string $code): bool
{
    $needs = array_map(fn (string $result): array => ['result' => $result, 'outputs' => new stdClass], $results);

    if ($code !== null) {
        $needs['changes']['outputs'] = ['code' => $code];
    }

    $process = new Process(['bash', dirname(__DIR__, 2).'/scripts/ci-verdict.sh']);
    $process->setInput((string) json_encode($needs));
    $process->run();

    return $process->isSuccessful();
}

/**
 * The jobs of a workflow, by name.
 *
 * @return array<string, array<string, mixed>>
 */
function workflowJobs(string $workflow): array
{
    $parsed = Yaml::parseFile(dirname(__DIR__, 2)."/.github/workflows/{$workflow}");
    $jobs = is_array($parsed) ? ($parsed['jobs'] ?? []) : [];

    return is_array($jobs) ? $jobs : [];
}

/** Every `run` of a job, joined. */
function jobRuns(array $job): string
{
    return implode("\n", array_map(fn (array $step): string => (string) ($step['run'] ?? ''), (array) ($job['steps'] ?? [])));
}

it('passes the required check when every job passed', function (): void {
    expect(ciPasses(['changes' => 'success', 'build' => 'success', 'checks' => 'success', 'tests' => 'success', 'coverage' => 'success', 'browser' => 'success', 'ssr' => 'success'], 'true'))->toBeTrue();
});

it('passes the required check when the change was only documentation and the jobs were skipped', function (): void {
    expect(ciPasses(['changes' => 'success', 'checks' => 'skipped', 'tests' => 'skipped', 'browser' => 'skipped', 'ssr' => 'skipped'], 'false'))->toBeTrue();
});

it('fails the required check', function (array $results, ?string $code): void {
    expect(ciPasses($results, $code))->toBeFalse();
})->with([
    'when a job failed' => [['changes' => 'success', 'checks' => 'success', 'tests' => 'failure', 'browser' => 'success', 'ssr' => 'success'], 'true'],
    'when a job was cancelled' => [['changes' => 'success', 'checks' => 'success', 'tests' => 'success', 'browser' => 'cancelled', 'ssr' => 'success'], 'true'],
    'when a job was skipped although the change needed the pipeline' => [['changes' => 'success', 'checks' => 'success', 'tests' => 'skipped', 'browser' => 'success', 'ssr' => 'success'], 'true'],
    'when a job failed in a change of documentation' => [['changes' => 'success', 'checks' => 'failure', 'tests' => 'skipped', 'browser' => 'skipped', 'ssr' => 'skipped'], 'false'],
    'when the job changes failed' => [['changes' => 'failure', 'checks' => 'skipped', 'tests' => 'skipped', 'browser' => 'skipped', 'ssr' => 'skipped'], null],
    'when the job changes was skipped' => [['changes' => 'skipped', 'checks' => 'skipped', 'tests' => 'skipped', 'browser' => 'skipped', 'ssr' => 'skipped'], 'false'],
    'when the job changes answered nothing' => [['changes' => 'success', 'checks' => 'skipped', 'tests' => 'skipped', 'browser' => 'skipped', 'ssr' => 'skipped'], null],
    'when the job changes answered something else' => [['changes' => 'success', 'checks' => 'skipped', 'tests' => 'skipped', 'browser' => 'skipped', 'ssr' => 'skipped'], 'maybe'],
]);

it('reads what a change brought without hiding a file that was moved', function (): void {
    $changes = workflowJobs('tests.yml')['changes'] ?? [];

    // --no-renames: a file of code moved into docs/ shows as a file of code deleted, not only as a new document.
    expect(jobRuns($changes))->toContain('git diff --no-renames --name-only')
        ->toContain('scripts/ci-code-changed.sh')
        ->and($changes['outputs'] ?? null)->toBe(['code' => '${{ steps.changed.outputs.code }}']);
});

it('runs every job of tests.yml only when the change needs the pipeline', function (): void {
    $jobs = workflowJobs('tests.yml');
    $gated = array_values(array_diff(array_keys($jobs), ['changes', 'ci']));

    expect($gated)->toContain('build', 'checks', 'tests', 'coverage', 'browser', 'ssr');

    foreach ($gated as $name) {
        $needs = (array) ($jobs[$name]['needs'] ?? []);

        // Besides changes, a job may wait only for other gated jobs (the build, the shards of the coverage).
        expect($needs[0] ?? null)->toBe('changes', "the job {$name} does not wait for the job changes")
            ->and(array_diff($needs, ['changes', ...$gated]))->toBe([], "the job {$name} waits for a job that is not gated")
            ->and($jobs[$name]['if'] ?? null)->toBe("needs.changes.outputs.code == 'true'", "the job {$name} runs whatever the change is");
    }
});

it('stops every job of tests.yml that hangs', function (): void {
    foreach (workflowJobs('tests.yml') as $name => $job) {
        expect($job['timeout-minutes'] ?? null)->toBeInt("the job {$name} has no timeout-minutes");
    }
});

it('runs on a merge queue as well', function (): void {
    $parsed = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/tests.yml');

    expect(is_array($parsed) && array_key_exists('merge_group', (array) ($parsed['on'] ?? [])))->toBeTrue();
});

it('keeps the 90% minimum over the shards of Unit and Feature together', function (): void {
    $jobs = workflowJobs('tests.yml');

    expect(jobRuns($jobs['tests']))->toContain('--shard=')->toContain('--coverage-php=')
        ->and(jobRuns($jobs['coverage']))->toContain('scripts/coverage-merge.php coverage.xml')
        ->toContain('scripts/coverage-min.php coverage.xml 90')
        ->and((array) $jobs['coverage']['needs'])->toContain('tests');
});

it('makes the required check wait for every job and decide with the verdict', function (): void {
    $jobs = workflowJobs('tests.yml');
    $others = array_values(array_diff(array_keys($jobs), ['ci']));
    $needs = (array) ($jobs['ci']['needs'] ?? []);
    sort($others);
    sort($needs);

    expect($needs)->toBe($others)
        ->and($jobs['ci']['if'] ?? null)->toBe('always()')
        ->and(jobRuns($jobs['ci']))->toContain('scripts/ci-verdict.sh');
});

it('does not deploy a change that skipped the pipeline, and still deploys by hand', function (): void {
    $jobs = workflowJobs('deploy.yml');

    expect(jobRuns($jobs['pipeline'] ?? []))->toContain('select(.name == "build") | .conclusion')
        ->and($jobs['pipeline']['permissions'] ?? null)->toBe(['actions' => 'read'])
        ->and($jobs['image']['needs'] ?? null)->toBe('pipeline')
        ->and($jobs['image']['if'] ?? null)->toBe("always() && (github.event_name == 'workflow_dispatch' || (github.event.workflow_run.conclusion == 'success' && needs.pipeline.outputs.tests != 'skipped'))");
});
