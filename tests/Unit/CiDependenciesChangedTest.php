<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/ci-dependencies-changed.sh tells tests.yml whether a change touches the dependencies, so a pull request runs
 * composer audit and npm audit only then: an advisory published today does not turn red a change that did not touch them
 * (audit.yml audits main every day).
 */

/** @param  list<string>  $paths */
function dependenciesChangedFor(array $paths): string
{
    $process = new Process(['bash', dirname(__DIR__, 2).'/scripts/ci-dependencies-changed.sh']);
    $process->setInput(implode("\n", $paths));
    $process->mustRun();

    return trim($process->getOutput());
}

it('says true when a change touches what installs or audits the dependencies', function (string $path): void {
    expect(dependenciesChangedFor(['app/Models/User.php', $path]))->toBe('true');
})->with(['composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'scripts/npm-audit.sh']);

it('says false when a change leaves them alone', function (): void {
    expect(dependenciesChangedFor(['app/Models/User.php', 'resources/js/app.tsx', 'database/composer.lock', 'vendor/package.json']))->toBe('false');
});

it('says true when the change could not be read', function (): void {
    expect(dependenciesChangedFor([]))->toBe('true');
});
