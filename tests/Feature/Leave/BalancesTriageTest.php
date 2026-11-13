<?php

declare(strict_types=1);

use App\Modules\Leave\Http\Controllers\BalanceController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Support\BalancePages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(BalanceController::class, BalancePages::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    // Wednesday 11 November 2026, noon in Mexico City.
    $this->travelTo('2026-11-11 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2024-03-15']);
});

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function triageProps(array $query = []): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.vacations.balances.index', [test()->tenant, ...$query]))->assertOk()->inertiaProps();
}

function triageUnacknowledged(Employee $person): void
{
    $period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15']);
    LeaveStatement::factory()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'leave_entitlement_id' => $period->id, 'acknowledged_at' => null]);
}

describe('where it opens', function (): void {
    it('opens on the statements nobody confirmed when there are some', function (): void {
        triageUnacknowledged($this->ana);
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'hired_on' => null]);

        expect(triageProps()['tab'])->toBe('statements');
    });

    it('opens on whoever has no start date when no statement waits', function (): void {
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'hired_on' => null]);

        expect(triageProps()['tab'])->toBe('missing');
    });

    it('opens on everybody when nothing needs chasing', function (): void {
        expect(triageProps()['tab'])->toBe('all');
    });

    it('keeps the tab that is asked for, everybody included, and falls back to the first for one it does not know', function (): void {
        triageUnacknowledged($this->ana);

        expect(triageProps(['tab' => 'all'])['tab'])->toBe('all')
            ->and(triageProps(['tab' => 'terminations'])['tab'])->toBe('terminations')
            ->and(triageProps(['tab' => 'nope'])['tab'])->toBe('statements');
    });
});

it('says the days a person was given in the periods that still hold days, so the bar has a whole to measure', function (): void {
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 12]);
    $open = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14, 'extra_days' => 2]);
    LeaveEntry::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_entitlement_id' => $open->id, 'kind' => 'taken', 'days' => -4]);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Sin Periodos', 'hired_on' => '2020-01-15']);

    $people = collect(triageProps(['tab' => 'all'])['people']['data'])->keyBy('name');

    expect($people['Ana Solís'])->toMatchArray(['available' => 12 + 12, 'granted' => 12 + 16])
        ->and($people['Beto Sin Periodos']['granted'])->toBeNull();
});

it('says today, to tell the next period is close', function (): void {
    expect(triageProps()['today'])->toBe('2026-11-11');
});

it('lists ten people per page, as the screen shows them', function (): void {
    foreach (range(1, 11) as $number) {
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => sprintf('Persona %02d', $number), 'hired_on' => '2020-01-15']);
    }

    $props = triageProps(['tab' => 'all']);

    expect($props['people']['per_page'])->toBe(10)
        ->and($props['people']['data'])->toHaveCount(10)
        ->and($props['people']['total'])->toBe(12);
});

it('says how many days are still owed in all, for the head of the page', function (): void {
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 12, 'extra_days' => 3]);
    $other = Tenant::factory()->create();
    $stranger = Employee::factory()->create(['tenant_id' => $other->id]);
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $other->id, 'employee_id' => $stranger->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 20]);

    expect(triageProps()['owedDays'])->toBe(15);
});

describe('a search that finds nobody in the tab', function (): void {
    it('says how many people match it in the other tabs', function (): void {
        triageUnacknowledged(Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Pendiente', 'hired_on' => '2024-03-15']));

        expect(triageProps(['tab' => 'statements', 'q' => 'Solís'])['elsewhere'])->toBe(1);
    });

    it('says none when the tab already has the match, when it is everybody, or when nothing is searched', function (): void {
        triageUnacknowledged($this->ana);

        expect(triageProps(['tab' => 'statements', 'q' => 'Solís'])['elsewhere'])->toBe(0)
            ->and(triageProps(['tab' => 'all', 'q' => 'Nadie Existe'])['elsewhere'])->toBe(0)
            ->and(triageProps(['tab' => 'statements'])['elsewhere'])->toBe(0);
    });

    it('says none when nobody matches anywhere', function (): void {
        triageUnacknowledged(Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Pendiente', 'hired_on' => '2024-03-15']));

        expect(triageProps(['tab' => 'statements', 'q' => 'Zzz'])['elsewhere'])->toBe(0);
    });
});
