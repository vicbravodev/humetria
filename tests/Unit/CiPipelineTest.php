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
        ->and($changes['outputs'] ?? null)->toBe(['code' => '${{ steps.changed.outputs.code }}', 'dependencies' => '${{ steps.changed.outputs.dependencies }}']);
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

it('cancels the whole run when the build or the fast checks fail, before the long jobs spend their minutes', function (): void {
    $jobs = workflowJobs('tests.yml');

    foreach (['build', 'checks'] as $name) {
        $steps = (array) ($jobs[$name]['steps'] ?? []);
        $last = is_array(end($steps)) ? end($steps) : [];

        expect($jobs[$name]['permissions'] ?? null)->toBe(['contents' => 'read', 'actions' => 'write'], "the job {$name} cannot cancel the run")
            ->and($last['if'] ?? null)->toBe('failure()', "the job {$name} does not end with a step that runs on failure")
            ->and($last['run'] ?? null)->toBe('gh run cancel "$GITHUB_RUN_ID"', "the job {$name} does not cancel the run when it fails")
            ->and($last['continue-on-error'] ?? null)->toBeTrue("the job {$name} would hide its own failure if the cancel is refused");
    }
});

it('lets every shard finish when one fails, so a run shows every failure and only the red shard is run again', function (): void {
    $jobs = workflowJobs('tests.yml');

    foreach (['tests', 'coverage', 'browser', 'ssr'] as $name) {
        expect(jobRuns($jobs[$name]))->not->toContain('gh run cancel', "the job {$name} cancels the run")
            ->and($jobs[$name]['permissions'] ?? null)->toBe(['contents' => 'read'], "the job {$name} may write to Actions");
    }
});

it('stops a hung test step at 15 minutes, while the job still uploads what it measured and saw', function (string $name): void {
    $steps = array_values(array_filter((array) (workflowJobs('tests.yml')[$name]['steps'] ?? []), fn (mixed $step): bool => is_array($step) && str_contains((string) ($step['run'] ?? ''), '--shard=')));

    expect($steps)->toHaveCount(1)
        ->and($steps[0]['timeout-minutes'] ?? null)->toBe(15);
})->with(['tests', 'browser']);

it('keeps the build and the coverage of the shards for a week, so a job run again days later still finds them', function (): void {
    $jobs = workflowJobs('tests.yml');
    $uploads = array_merge(...array_map(fn (string $name): array => array_values(array_filter((array) ($jobs[$name]['steps'] ?? []), fn (mixed $step): bool => is_array($step) && in_array($step['with']['name'] ?? null, ['assets', 'coverage-${{ matrix.shard }}'], true))), ['build', 'tests']));

    expect($uploads)->toHaveCount(2);

    foreach ($uploads as $upload) {
        expect($upload['with']['retention-days'] ?? null)->toBe(7);
    }
});

it('checks out only the scripts in the jobs that read nothing else', function (string $name): void {
    $checkout = ((array) (workflowJobs('tests.yml')[$name]['steps'] ?? []))[0] ?? [];

    expect($checkout['with']['sparse-checkout'] ?? null)->toBe('scripts')
        ->and($checkout['with']['filter'] ?? null)->toBe('blob:none');
})->with(['changes', 'ci']);

it('splits the browser tests into six shards', function (): void {
    $browser = workflowJobs('tests.yml')['browser'];

    expect($browser['strategy']['matrix']['shard'] ?? null)->toBe([1, 2, 3, 4, 5, 6])
        ->and(jobRuns($browser))->toContain('--shard=${{ matrix.shard }}/6');
});

it('audits the dependencies of a pull request only when it changes them, and main every day', function (): void {
    $jobs = workflowJobs('tests.yml');
    $audit = array_values(array_filter((array) ($jobs['checks']['steps'] ?? []), fn (mixed $step): bool => is_array($step) && str_contains((string) ($step['run'] ?? ''), 'composer audit')));
    $daily = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/audit.yml');
    $dailyJobs = workflowJobs('audit.yml');

    expect($jobs['changes']['outputs']['dependencies'] ?? null)->toBe('${{ steps.changed.outputs.dependencies }}')
        ->and(jobRuns($jobs['changes']))->toContain('scripts/ci-dependencies-changed.sh')
        ->and($audit)->toHaveCount(1)
        ->and($audit[0]['if'] ?? null)->toBe("needs.changes.outputs.dependencies == 'true'")
        ->and(is_array($daily) ? ($daily['on']['schedule'] ?? null) : null)->toBeArray()
        ->and(jobRuns(reset($dailyJobs) ?: []))->toContain('composer audit')->toContain('scripts/npm-audit.sh');
});

it('keeps the result cache of PHPStan between runs', function (): void {
    $cache = array_values(array_filter((array) (workflowJobs('tests.yml')['checks']['steps'] ?? []), fn (mixed $step): bool => is_array($step) && ($step['with']['path'] ?? null) === '/tmp/phpstan'));

    expect($cache)->toHaveCount(1)
        ->and($cache[0]['with']['restore-keys'] ?? null)->toBe('phpstan-');
});

it('turns off autovacuum on the CI Postgres, so it never analyzes a table while a test holds its rows uncommitted', function (): void {
    $setup = Yaml::parseFile(dirname(__DIR__, 2).'/.github/actions/setup/action.yml');
    $tune = array_values(array_filter(is_array($setup) ? (array) ($setup['runs']['steps'] ?? []) : [], fn (mixed $step): bool => is_array($step) && ($step['name'] ?? null) === 'Tune Postgres for tests'));

    expect($tune)->toHaveCount(1)
        ->and((string) ($tune[0]['run'] ?? ''))->toContain("-c 'ALTER SYSTEM SET autovacuum = off'");
});

it('caches only the layers of the images the deploy ships, so its cache does not push the CI caches out', function (): void {
    $builds = array_values(array_filter((array) (workflowJobs('deploy.yml')['image']['steps'] ?? []), fn (mixed $step): bool => is_array($step) && isset($step['with']['cache-to'])));

    expect($builds)->toHaveCount(2);

    foreach ($builds as $build) {
        expect($build['with']['cache-to'])->toContain('mode=min')->not->toContain('mode=max');
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

it('keeps the time of every test class each shard measured, for scripts/shard-timings.php', function (string $name): void {
    $job = workflowJobs('tests.yml')[$name];
    $uploads = array_values(array_filter((array) ($job['steps'] ?? []), fn (mixed $step): bool => is_array($step) && ($step['with']['name'] ?? null) === "timings-{$name}-\${{ matrix.shard }}"));

    expect(jobRuns($job))->toContain("--log-junit=junit-{$name}-\${{ matrix.shard }}.xml")
        ->and($uploads)->toHaveCount(1)
        ->and($uploads[0]['with']['path'] ?? null)->toBe("junit-{$name}-\${{ matrix.shard }}.xml");
})->with(['tests', 'browser']);

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
        ->and($jobs['image']['if'] ?? null)->toBe("always() && (github.event_name == 'workflow_dispatch' || (github.event.workflow_run.conclusion == 'success' && github.event.workflow_run.event == 'push' && github.event.workflow_run.head_repository.full_name == github.repository && needs.pipeline.outputs.tests != 'skipped'))");
});

it('only builds and deploys from a push run in this repository, never from a fork or a pull request', function (): void {
    $jobs = workflowJobs('deploy.yml');
    $trusted = "github.event.workflow_run.event == 'push' && github.event.workflow_run.head_repository.full_name == github.repository";

    expect($jobs['pipeline']['if'] ?? '')->toContain($trusted)
        ->and($jobs['image']['if'] ?? '')->toContain($trusted);
});

it('gives the Postgres of the weekly mutation run room for the locks of dropping every table at once', function (): void {
    $steps = array_values((array) (workflowJobs('mutation.yml')['mutation']['steps'] ?? []));
    $raise = array_search(true, array_map(fn (array $step): bool => str_contains((string) ($step['run'] ?? ''), 'max_locks_per_transaction'), $steps), true);
    $setup = array_search('./.github/actions/setup', array_map(fn (array $step): string => (string) ($step['uses'] ?? ''), $steps), true);

    // max_locks_per_transaction is read only when Postgres starts: a reload does not apply it, the restart does. Without it
    // the parallel databases die with "out of shared memory" on migrate:fresh. 256, as in the local Postgres.
    expect($raise)->toBeInt('no step raises max_locks_per_transaction')
        ->and($setup)->toBeInt()
        ->and($raise)->toBeLessThan($setup, 'the restart must come before the migration of the setup')
        ->and($steps[$raise]['run'])->toContain('ALTER SYSTEM SET max_locks_per_transaction = 256')
        ->toContain('docker restart "$POSTGRES_CONTAINER"')
        ->toContain('pg_isready')
        ->and($steps[$raise]['env']['POSTGRES_CONTAINER'] ?? null)->toBe('${{ job.services.postgres.id }}');
});
