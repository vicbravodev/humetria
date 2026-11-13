<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRestriction;
use App\Modules\Tenancy\Support\AuditPages;

/** Every event name the code writes to the audit trail: the first argument of each `->handle('x.y', …)` that audits. */
function auditedEvents(): array
{
    $events = array_map(fn (TenantRestriction $restriction): string => $restriction->event(), TenantRestriction::cases());

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all('/(?:[aA]udit|recordAudit)->handle\(((?:[^,()]|\([^()]*\))*)/', (string) file_get_contents($file->getPathname()), $calls);

        foreach ($calls[1] as $argument) {
            preg_match_all("/'([a-z0-9_]+\\.[a-z_]+)'/", $argument, $names);
            $events = [...$events, ...$names[1]];
        }
    }

    return array_values(array_unique($events));
}

it('gives every event the code audits a label a person can read, in Spanish and different from its key', function (): void {
    preg_match_all("/^\\s+'([a-z0-9_.]+)':\\s+'(.+)',$/m", (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/audit.ts'), $found);
    $labels = array_combine($found[1], $found[2]);
    $events = auditedEvents();

    expect($events)->not->toBeEmpty();

    $missing = array_values(array_filter($events, fn (string $event): bool => ! isset($labels[$event])));

    expect($missing)->toBe([]);
    expect(array_filter($labels, fn (string $label, string $event): bool => $label === $event, ARRAY_FILTER_USE_BOTH))->toBe([]);
});

it('offers every family of audited events in the console filter', function (): void {
    $families = array_values(array_unique(array_map(fn (string $event): string => explode('.', $event)[0], auditedEvents())));

    expect(array_values(array_diff($families, array_keys(AuditPages::FAMILIES))))->toBe([]);
});

/** Every key the code puts in the metadata of an audit entry: those written in the call itself and in the arrays it hands over by name. */
function auditedMetadataKeys(): array
{
    $keys = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        if (! preg_match('/(?:[aA]udit|recordAudit)->handle\(/', $source)) {
            continue;
        }

        // The call's own arguments, and the arrays the files build for it ($record, $change, $context…).
        preg_match_all('/(?:[aA]udit|recordAudit)->handle\(/', $source, $starts, PREG_OFFSET_CAPTURE);
        $calls = [[]];

        foreach ($starts[0] as [$match, $offset]) {
            $from = $offset + strlen($match);
            $depth = 1;
            $at = $from;

            for (; $depth > 0 && $at < strlen($source); $at++) {
                $depth += match ($source[$at]) {
                    '(' => 1,
                    ')' => -1,
                    default => 0,
                };
            }

            $calls[0][] = substr($source, $from, $at - $from - 1);
        }

        preg_match_all('/\$(?:record|change|context|details|counts|facts|removed|metadata|changes)\s*=\s*\[(.*?)\];/s', $source, $arrays);

        foreach ([...$calls[0], ...$arrays[1]] as $text) {
            preg_match_all("/'([a-z][a-z_0-9]*)'\\s*=>/", $text, $found);
            $keys = [...$keys, ...$found[1]];
        }
    }

    return array_values(array_unique($keys));
}

it('names in Spanish every key of the metadata the code audits', function (): void {
    $keys = auditedMetadataKeys();

    expect($keys)->toContain('catalog_version', 'negotiated_price_cents', 'account_id');

    $missing = array_values(array_filter($keys, fn (string $key): bool => $key !== 'tenant_id' && ! isset(AuditPages::KEYS[$key])));

    expect($missing)->toBe([]);
    expect(array_filter(AuditPages::KEYS, fn (string $label): bool => str_contains($label, '_') || $label === ''))->toBe([]);
});
