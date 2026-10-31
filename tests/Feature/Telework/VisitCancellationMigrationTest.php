<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** @return list<string> The CHECK constraints of visit_authorizations. */
function visitChecks(): array
{
    return array_map(fn (object $row): string => $row->conname, DB::select("SELECT conname FROM pg_constraint WHERE conrelid = 'visit_authorizations'::regclass AND contype = 'c' ORDER BY conname"));
}

it('reverses and reapplies the cancellation migration, restoring the confirmation check', function (): void {
    $migration = require database_path('migrations/2026_11_11_100000_add_cancellation_to_visit_authorizations.php');

    expect(visitChecks())->toContain('visit_authorizations_ip_needs_confirmation', 'visit_authorizations_cancellation_pair')->not->toContain('visit_authorizations_confirmation_pair');

    $migration->down();

    expect(visitChecks())->toContain('visit_authorizations_confirmation_pair')->not->toContain('visit_authorizations_ip_needs_confirmation', 'visit_authorizations_cancellation_pair')
        ->and(Schema::hasColumns('visit_authorizations', ['cancelled_at', 'cancelled_by']))->toBeFalse();

    $migration->up();

    expect(visitChecks())->toContain('visit_authorizations_ip_needs_confirmation', 'visit_authorizations_cancellation_pair')->not->toContain('visit_authorizations_confirmation_pair')
        ->and(Schema::hasColumns('visit_authorizations', ['cancelled_at', 'cancelled_by']))->toBeTrue();
});
