<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/worktree-env.sh writes a worktree's .env from the main checkout's: same settings, but PostHog neutral for tests
 * (no project token, consent denied), so CSP, consent and attribution tests do not depend on the owner's real keys.
 */

/**
 * @return array{int, string}
 */
function runWorktreeEnv(string $source): array
{
    $dir = sys_get_temp_dir().'/worktree-env-'.bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents($dir.'/source', $source);

    $process = new Process(['bash', dirname(__DIR__, 2).'/scripts/worktree-env.sh', $dir.'/source', $dir.'/dest']);
    $process->run();

    $written = is_file($dir.'/dest') ? (string) file_get_contents($dir.'/dest') : '';
    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);

    return [$process->getExitCode() ?? -1, $written];
}

it('empties the PostHog token and denies consent', function (): void {
    [$code, $env] = runWorktreeEnv("APP_NAME=Humetria\nPOSTHOG_PROJECT_TOKEN=phc_secret\nPOSTHOG_CONSENT_DEFAULT=granted\n");

    expect($code)->toBe(0)
        ->and($env)->toContain("POSTHOG_PROJECT_TOKEN=\n")
        ->and($env)->toContain("POSTHOG_CONSENT_DEFAULT=denied\n")
        ->and($env)->not->toContain('phc_secret');
});

it('keeps every other setting as it was', function (): void {
    [, $env] = runWorktreeEnv("APP_NAME=Humetria\nDB_HOST=pgsql\nPOSTHOG_PROJECT_TOKEN=phc_secret\nLOG_STACK=stderr\n");

    expect($env)->toContain("APP_NAME=Humetria\n")
        ->and($env)->toContain("DB_HOST=pgsql\n")
        ->and($env)->toContain("LOG_STACK=stderr\n");
});

it('neutralises quoted and spaced values too', function (): void {
    [, $env] = runWorktreeEnv("POSTHOG_PROJECT_TOKEN=\"phc_secret\"\nPOSTHOG_CONSENT_DEFAULT = 'granted'\n");

    expect($env)->not->toContain('phc_secret')
        ->and($env)->toContain("POSTHOG_PROJECT_TOKEN=\n")
        ->and($env)->toContain("POSTHOG_CONSENT_DEFAULT=denied\n");
});

it('sets both even when the main .env does not have them', function (): void {
    [$code, $env] = runWorktreeEnv("APP_NAME=Humetria\n");

    expect($code)->toBe(0)
        ->and($env)->toContain("POSTHOG_PROJECT_TOKEN=\n")
        ->and($env)->toContain("POSTHOG_CONSENT_DEFAULT=denied\n");
});

it('leaves comments alone', function (): void {
    [, $env] = runWorktreeEnv("# POSTHOG_PROJECT_TOKEN=phc_old\nPOSTHOG_PROJECT_TOKEN=phc_secret\n");

    expect($env)->toContain("# POSTHOG_PROJECT_TOKEN=phc_old\n")
        ->and($env)->toContain("\nPOSTHOG_PROJECT_TOKEN=\n")
        ->and($env)->not->toContain('phc_secret');
});
