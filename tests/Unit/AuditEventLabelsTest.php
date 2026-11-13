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
            preg_match_all("/'([a-z_]+\\.[a-z_]+)'/", $argument, $names);
            $events = [...$events, ...$names[1]];
        }
    }

    return array_values(array_unique(array_filter($events, fn (string $event): bool => array_key_exists(explode('.', $event)[0], AuditPages::FAMILIES))));
}

it('gives every event the code audits a label a person can read, in Spanish and different from its key', function (): void {
    preg_match_all("/^\\s+'([a-z_.]+)':\\s+'(.+)',$/m", (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/audit.ts'), $found);
    $labels = array_combine($found[1], $found[2]);
    $events = auditedEvents();

    expect($events)->not->toBeEmpty();

    $missing = array_values(array_filter($events, fn (string $event): bool => ! isset($labels[$event])));

    expect($missing)->toBe([]);
    expect(array_filter($labels, fn (string $label, string $event): bool => $label === $event, ARRAY_FILTER_USE_BOTH))->toBe([]);
});
