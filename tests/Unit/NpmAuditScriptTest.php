<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/npm-audit.sh runs `npm audit --omit=dev --json` and fails on any high or critical advisory except the ones
 * it lists on purpose. Here it reads the audit from a file (NPM_AUDIT_JSON_FILE) instead of the network.
 */

/**
 * @param  list<array{string, string}>  $advisories  [GHSA id, severity]
 * @return array{int, string}
 */
function runNpmAudit(array $advisories): array
{
    $vulnerabilities = [];
    foreach ($advisories as $i => [$ghsa, $severity]) {
        $vulnerabilities["pkg{$i}"] = ['severity' => $severity, 'via' => [
            ['source' => $i, 'severity' => $severity, 'url' => "https://github.com/advisories/{$ghsa}"],
        ]];
        $vulnerabilities["dependent{$i}"] = ['severity' => $severity, 'via' => ["pkg{$i}"]];
    }

    $file = tempnam(sys_get_temp_dir(), 'audit');
    file_put_contents($file, json_encode(['vulnerabilities' => (object) $vulnerabilities], JSON_THROW_ON_ERROR));

    $process = new Process(['bash', dirname(__DIR__, 2).'/scripts/npm-audit.sh'], null, ['NPM_AUDIT_JSON_FILE' => $file]);
    $process->run();
    unlink($file);

    return [$process->getExitCode() ?? -1, $process->getOutput().$process->getErrorOutput()];
}

it('passes with no advisories', function (): void {
    expect(runNpmAudit([])[0])->toBe(0);
});

it('ignores only the allowed picomatch advisory and says so', function (): void {
    [$code, $output] = runNpmAudit([['GHSA-fc5q-39xc-vjpf', 'high']]);

    expect($code)->toBe(0)->and($output)->toContain('GHSA-fc5q-39xc-vjpf');
});

it('fails when another high advisory comes with the allowed one', function (): void {
    [$code, $output] = runNpmAudit([['GHSA-fc5q-39xc-vjpf', 'high'], ['GHSA-aaaa-bbbb-cccc', 'high']]);

    expect($code)->toBe(1)->and($output)->toContain('GHSA-aaaa-bbbb-cccc');
});

it('fails on a new critical advisory', function (): void {
    expect(runNpmAudit([['GHSA-dddd-eeee-ffff', 'critical']])[0])->toBe(1);
});

it('does not fail on a moderate advisory', function (): void {
    expect(runNpmAudit([['GHSA-gggg-hhhh-iiii', 'moderate']])[0])->toBe(0);
});
