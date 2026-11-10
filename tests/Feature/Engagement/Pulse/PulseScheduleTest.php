<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Engagement\Actions\SavePulseSchedule;
use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Http\Requests\PulseScheduleRequest;
use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Pulse\UpcomingPulse;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SavePulseSchedule::class, PulseScheduleRequest::class, UpcomingPulse::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-09 18:00:00');
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** @param array<string, mixed> $overrides */
function pulseSchedulePayload(array $overrides = []): array
{
    return ['frequency' => 'monthly', 'day_of_month' => 15, 'open_days' => 7, 'active' => true, ...$overrides];
}

function pulseSchedulePut(mixed $test, Tenant $tenant, array $payload): Illuminate\Testing\TestResponse
{
    return $test->put(route('tenant.pulse.schedule.update', $tenant), $payload);
}

it('saves a monthly schedule and works out the next run', function (): void {
    pulseSchedulePut($this->actingAs($this->hr), $this->tenant, pulseSchedulePayload())->assertRedirect();

    $schedule = PulseSchedule::query()->sole();
    expect($schedule->frequency)->toBe(PulseFrequency::Monthly)
        ->and($schedule->day_of_month)->toBe(15)
        ->and($schedule->open_days)->toBe(7)
        ->and($schedule->active)->toBeTrue()
        ->and($schedule->next_run_on->toDateString())->toBe('2026-11-15')
        ->and($schedule->next_mode)->toBeNull()
        ->and($schedule->created_by)->toBe($this->hr->id);
});

it('runs a quarterly schedule at the close of the calendar quarter', function (): void {
    pulseSchedulePut($this->actingAs($this->hr), $this->tenant, pulseSchedulePayload(['frequency' => 'quarterly', 'day_of_month' => 1]));

    expect(PulseSchedule::query()->sole()->next_run_on->toDateString())->toBe('2026-12-01');
});

it('works the next run out from the local day, not the UTC one', function (): void {
    // 2026-11-15 03:00 UTC is still the 14th in Mexico City: the 15th is still ahead.
    $this->travelTo('2026-11-15 03:00:00');

    pulseSchedulePut($this->actingAs($this->hr), $this->tenant, pulseSchedulePayload());

    expect(PulseSchedule::query()->sole()->next_run_on->toDateString())->toBe('2026-11-15');
});

it('validates the day, the days open and the frequency', function (array $bad, string $field): void {
    pulseSchedulePut($this->actingAs($this->hr), $this->tenant, pulseSchedulePayload($bad))->assertSessionHasErrors($field);

    expect(PulseSchedule::query()->count())->toBe(0);
})->with([
    'day 29' => [['day_of_month' => 29], 'day_of_month'],
    'day 0' => [['day_of_month' => 0], 'day_of_month'],
    'open 2' => [['open_days' => 2], 'open_days'],
    'open 15' => [['open_days' => 15], 'open_days'],
    'weekly' => [['frequency' => 'weekly'], 'frequency'],
    'always' => [['frequency' => 'always'], 'frequency'],
    'no frequency' => [['frequency' => null], 'frequency'],
    'no active' => [['active' => null], 'active'],
    'unknown mode' => [['next_mode' => 'always_open'], 'next_mode'],
]);

it('accepts the edges of the days open and of the day of the month', function (int $day, int $open): void {
    pulseSchedulePut($this->actingAs($this->hr), $this->tenant, pulseSchedulePayload(['day_of_month' => $day, 'open_days' => $open]))->assertSessionHasNoErrors();

    expect(PulseSchedule::query()->sole()->open_days)->toBe($open);
})->with([[1, 3], [28, 14]]);

it('keeps a single row per company', function (): void {
    $this->actingAs($this->hr);

    pulseSchedulePut($this, $this->tenant, pulseSchedulePayload());
    pulseSchedulePut($this, $this->tenant, pulseSchedulePayload(['frequency' => 'quarterly', 'day_of_month' => 3, 'open_days' => 10]));

    expect(PulseSchedule::query()->count())->toBe(1)
        ->and(PulseSchedule::query()->sole()->frequency)->toBe(PulseFrequency::Quarterly)
        ->and(PulseSchedule::query()->sole()->open_days)->toBe(10);
});

it('pauses without losing the row', function (): void {
    $this->actingAs($this->hr);
    pulseSchedulePut($this, $this->tenant, pulseSchedulePayload());

    pulseSchedulePut($this, $this->tenant, pulseSchedulePayload(['active' => false]));

    expect(PulseSchedule::query()->sole()->active)->toBeFalse();
});

it('lets RH choose the mode of the next pulse only among those available', function (): void {
    $this->actingAs($this->hr);

    pulseSchedulePut($this, $this->tenant, pulseSchedulePayload(['next_mode' => 'follow_up']))->assertSessionHasErrors(['next_mode' => 'Aún no hay prioridades de un clima para dar seguimiento.']);
    pulseSchedulePut($this, $this->tenant, pulseSchedulePayload(['next_mode' => 'manager']))->assertSessionHasNoErrors();

    expect(PulseSchedule::query()->sole()->next_mode)->toBe(PulseMode::Manager);
});

it('writes the log and the audit trail of the change', function (): void {
    Log::spy();

    pulseSchedulePut($this->actingAs($this->hr), $this->tenant, pulseSchedulePayload());

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'engagement.pulse_schedule_saved' && $context['frequency'] === 'monthly')->once();
    expect(AuditEntry::query()->where('event', 'engagement.pulse_schedule_saved')->count())->toBe(1);
});

it('shows every role the schedule card, and only RH may save it', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->actingAs($viewer)->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('schedule', null)
        ->has('frequencies', 2)
        ->where('canManage', false));

    pulseSchedulePut($this, $this->tenant, pulseSchedulePayload())->assertForbidden();
    expect(PulseSchedule::query()->count())->toBe(0);
});

it('tells what the next pulse will be: when, in which mode and how many questions', function (): void {
    $this->actingAs($this->hr);
    pulseSchedulePut($this, $this->tenant, pulseSchedulePayload());

    $this->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('schedule.frequency', 'monthly')
        ->where('schedule.frequencyLabel', 'Mensual')
        ->where('schedule.dayOfMonth', 15)
        ->where('schedule.openDays', 7)
        ->where('schedule.active', true)
        ->where('schedule.nextRunOn', '2026-11-15')
        ->where('schedule.nextMode', null)
        ->where('schedule.upcoming', ['mode' => 'tour', 'modeLabel' => 'Recorrido', 'items' => 9]));
});

it('counts the pulses already launched to pick the mode of the next one', function (): void {
    $this->actingAs($this->hr);
    pulseSchedulePut($this, $this->tenant, pulseSchedulePayload());
    Survey::factory()->pulse()->closed()->count(2)->create(['tenant_id' => $this->tenant->id, 'pulse_mode' => PulseMode::Tour]);

    $this->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('schedule.upcoming.mode', 'manager'));
});

it('honors the mode RH chose over the calendar', function (): void {
    $this->actingAs($this->hr);
    pulseSchedulePut($this, $this->tenant, pulseSchedulePayload(['next_mode' => 'manager']));

    $this->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('schedule.nextMode', 'manager')->where('schedule.upcoming.mode', 'manager'));
});

it('does not offer a schedule in the climate home', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.climate.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('schedule', null)->where('frequencies', null));
});
