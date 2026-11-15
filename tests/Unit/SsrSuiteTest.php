<?php

declare(strict_types=1);

/*
 * tests/Ssr needs a real Node server and its bundle: it has its own command (composer test:ssr) and stays out of
 * `composer test`, the coverage, the fast run and the mutation, which never start Node.
 */

/** @return array<string, list<string>|string> */
function composerScripts(): array
{
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    return $composer['scripts'];
}

it('has an Ssr suite of its own', function (): void {
    $phpunit = (string) file_get_contents(__DIR__.'/../../phpunit.xml');

    expect($phpunit)->toContain('<testsuite name="Ssr">')->toContain('<directory>tests/Ssr</directory>');
});

it('runs the Ssr suite with Pest, like Feature and Browser', function (): void {
    expect((string) file_get_contents(__DIR__.'/../../tests/Pest.php'))->toContain("->in('Feature', 'Browser', 'Ssr')");
});

it('keeps the Ssr suite out of every script that runs Pest', function (string $script): void {
    $commands = implode("\n", (array) composerScripts()[$script]);

    expect($commands)->toMatch('/--exclude-testsuite=(\S+,)?Ssr(\s|$)/');
})->with(['test:coverage', 'test:fast', 'mutate', 'mutate:retry', 'mutate:full']);

// PHPUnit keeps only the last --exclude-testsuite: repeating the option brings the first suite back into the run.
it('names every excluded suite in one option', function (string $script): void {
    $commands = implode("\n", (array) composerScripts()[$script]);

    expect($commands)->toMatch('/--exclude-testsuite=Browser,Ssr(\s|$)/')
        ->and($commands)->not->toMatch('/--exclude-testsuite=\S+[^\n]*--exclude-testsuite=/');
})->with(['test:coverage', 'mutate', 'mutate:retry', 'mutate:full']);

it('keeps the Ssr suite out of the weekly mutation run', function (): void {
    $script = (string) file_get_contents(__DIR__.'/../../scripts/mutate-critical.sh');

    expect($script)->toMatch('/--exclude-testsuite=Browser,Ssr(\s|$)/')
        ->and($script)->not->toMatch('/--exclude-testsuite=\S+[^\n]*--exclude-testsuite=/');
});

it('runs the Ssr suite and the Node tests with composer test:ssr', function (): void {
    expect(implode("\n", (array) composerScripts()['test:ssr']))->toContain('scripts/ssr/test.sh');
});
