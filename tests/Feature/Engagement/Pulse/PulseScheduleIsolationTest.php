<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

it('does not read or change the schedule of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $ofB = PulseSchedule::factory()->create(['tenant_id' => $b->id, 'day_of_month' => 10]);

    workIn($a);
    expect(PulseSchedule::query()->count())->toBe(0)
        ->and(PulseSchedule::query()->find($ofB->id))->toBeNull()
        ->and(PulseSchedule::query()->whereKey($ofB->id)->update(['day_of_month' => 11]))->toBe(0)
        ->and(PulseSchedule::query()->whereKey($ofB->id)->delete())->toBe(0);

    workIn($b);
    expect(PulseSchedule::query()->sole()->day_of_month)->toBe(10);
});

it('saves the schedule of the own company and never touches the one of another, whatever the request says', function (): void {
    $a = Tenant::factory()->create(['slug' => 'acme']);
    $b = Tenant::factory()->create();
    $ofB = PulseSchedule::factory()->create(['tenant_id' => $b->id, 'day_of_month' => 10]);
    workIn($a);

    $this->actingAs(memberOf($a, TenantRole::Hr))->put(route('tenant.pulse.schedule.update', $a), ['frequency' => 'monthly', 'day_of_month' => 20, 'open_days' => 7, 'active' => true, 'tenant_id' => $b->id])->assertRedirect();

    expect(PulseSchedule::query()->sole()->tenant_id)->toBe($a->id);
    workIn($b);
    expect(PulseSchedule::query()->sole()->id)->toBe($ofB->id)->and($ofB->fresh()?->day_of_month)->toBe(10);
});

it('shows the own schedule only', function (): void {
    $a = Tenant::factory()->create(['slug' => 'acme']);
    $b = Tenant::factory()->create();
    PulseSchedule::factory()->create(['tenant_id' => $b->id, 'day_of_month' => 10]);
    workIn($a);

    $this->actingAs(memberOf($a, TenantRole::Hr))->get(route('tenant.pulse.index', $a))->assertInertia(fn (Assert $page) => $page->where('schedule', null));
});

it('rejects a second row for a company', function (): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);
    PulseSchedule::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => PulseSchedule::factory()->create(['tenant_id' => $tenant->id]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('rejects a day, a window or a frequency the form would not', function (array $bad): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);
    $schedule = PulseSchedule::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => Illuminate\Support\Facades\DB::table('engagement_pulse_schedules')->where('id', $schedule->id)->update($bad))->toThrow(Illuminate\Database\QueryException::class);
})->with([
    'day 29' => [['day_of_month' => 29]],
    'day 0' => [['day_of_month' => 0]],
    'open 2' => [['open_days' => 2]],
    'open 15' => [['open_days' => 15]],
    'frequency' => [['frequency' => 'weekly']],
    'mode' => [['next_mode' => 'always_open']],
]);
