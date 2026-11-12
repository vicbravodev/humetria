<?php

declare(strict_types=1);

use App\Modules\Leave\Http\Controllers\VacationsController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\SummaryPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(VacationsController::class, SummaryPages::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    // Wednesday 11 November 2026, noon in Mexico City.
    $this->travelTo('2026-11-11 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->boss = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz', 'hired_on' => '2020-01-15']);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function overviewRequest(string $name, string $state, string $startsOn, string $endsOn, array $attributes = []): LeaveRequest
{
    $person = Employee::factory()->reportingTo(test()->boss)->create(['name' => $name, 'hired_on' => '2020-01-15']);
    $factory = LeaveRequest::factory();
    $factory = match ($state) {
        'approved' => $factory->approved(),
        'escalated' => $factory->escalated(),
        default => $factory,
    };

    return $factory->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'approver_employee_id' => test()->boss->id, 'starts_on' => $startsOn, 'ends_on' => $endsOn, 'working_days' => 3, ...$attributes]);
}

/** @return array<string, mixed> */
function overviewProps(): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.vacations.index', test()->tenant))->assertOk()->inertiaProps();
}

it('puts first the requests that wait for RH, the one that starts soonest on top, with whether the days cover them', function (): void {
    $later = overviewRequest('Úrsula Ayala', 'escalated', '2026-12-09', '2026-12-11');
    $sooner = overviewRequest('Iván Castañeda', 'escalated', '2026-11-18', '2026-11-20');
    overviewRequest('Del jefe', 'requested', '2026-11-12', '2026-11-13');
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $sooner->employee_id, 'years_of_service' => 1, 'anniversary_on' => '2026-03-15', 'statutory_days' => 23]);

    $answer = overviewProps()['toAnswer'];

    expect(array_column($answer, 'id'))->toBe([$sooner->id, $later->id])
        ->and($answer[0])->toMatchArray(['name' => 'Iván Castañeda', 'startsOn' => '2026-11-18', 'endsOn' => '2026-11-20', 'workingDays' => 3, 'reason' => 'Su jefe no respondió en 3 días hábiles', 'coverage' => ['fits' => true, 'leftAfter' => 20]])
        ->and($answer[1]['coverage'])->toBe(['fits' => false, 'leftAfter' => -3]);
});

it('shows at most four of them and never what is of another company', function (): void {
    foreach (range(1, 6) as $number) {
        overviewRequest("Persona {$number}", 'escalated', '2026-12-0'.$number, '2026-12-0'.$number);
    }
    $other = Tenant::factory()->create(['slug' => 'otra']);
    LeaveSetting::factory()->create(['tenant_id' => $other->id]);
    LeaveRequest::factory()->escalated()->create(['tenant_id' => $other->id, 'employee_id' => Employee::factory()->create(['tenant_id' => $other->id])->id]);

    expect(overviewProps()['toAnswer'])->toHaveCount(4);
});

it('counts the people out each of the next fourteen days, from today, without a name', function (): void {
    overviewRequest('Alonso Villanueva', 'approved', '2026-11-17', '2026-11-18');
    overviewRequest('Sara Gutiérrez', 'approved', '2026-11-18', '2026-11-20');
    overviewRequest('Fuera de la tira', 'approved', '2026-11-25', '2026-11-26');
    overviewRequest('Solo pedida', 'requested', '2026-11-17', '2026-11-17');
    $props = overviewProps();

    $days = array_column($props['next14'], 'away', 'date');

    expect($props['next14'])->toHaveCount(14)
        ->and(array_key_first($days))->toBe('2026-11-11')
        ->and(array_key_last($days))->toBe('2026-11-24')
        ->and($days['2026-11-16'])->toBe(0)
        ->and($days['2026-11-17'])->toBe(1)
        ->and($days['2026-11-18'])->toBe(2)
        ->and($days['2026-11-20'])->toBe(1)
        ->and($days['2026-11-24'])->toBe(0)
        ->and(json_encode($props['next14']))->not->toContain('Alonso');
});

it('counts a person once a day even with two stretches', function (): void {
    $first = overviewRequest('Doble', 'approved', '2026-11-17', '2026-11-18');
    LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $first->employee_id, 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-19']);

    expect(array_column(overviewProps()['next14'], 'away', 'date')['2026-11-18'])->toBe(1);
});

it('says how many periods still hold days, and whether the company has started', function (): void {
    $props = overviewProps();

    expect($props['firstTime'])->toBeTrue()
        ->and($props['summary']['periods'])->toBe(0);

    $person = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'hired_on' => '2020-01-15']);
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id, 'anniversary_on' => '2026-03-15', 'statutory_days' => 12]);
    LeaveEntitlement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id, 'years_of_service' => 2, 'anniversary_on' => '2025-03-15', 'statutory_days' => 0]);
    $props = overviewProps();

    expect($props['firstTime'])->toBeFalse()
        ->and($props['summary']['periods'])->toBe(1);
});

it('is not the first time once the company has a policy of its own', function (): void {
    LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(overviewProps()['firstTime'])->toBeFalse();
});
