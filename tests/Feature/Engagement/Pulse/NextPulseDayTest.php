<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Engagement\Support\Pulse\SchedulePage;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(SchedulePage::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    PulseSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'next_run_on' => '2026-12-24']);
});

/** The days the home of the pulse says are left for the next one, at the given instant (UTC). */
function pulseDaysLeftAt(string $utc): int
{
    test()->travelTo($utc);

    $props = test()->actingAs(test()->hr)->get(route('tenant.pulse.index', test()->tenant))->assertOk()->inertiaProps();

    return $props['schedule']['daysUntil'];
}

it('counts the days to the next pulse by the day people live in Mexico City, at the edge of the UTC day', function (string $utc, int $days): void {
    expect(pulseDaysLeftAt($utc))->toBe($days);
})->with([
    // 05:59 UTC is still 23:59 of the 19th in Mexico City (UTC-6): five days to the 24th, not four.
    'just before local midnight' => ['2026-12-20 05:59:00', 5],
    'just after local midnight' => ['2026-12-20 06:00:00', 4],
    'at noon' => ['2026-12-20 18:00:00', 4],
    'the day it goes out, late in the evening there' => ['2026-12-25 03:00:00', 0],
]);
