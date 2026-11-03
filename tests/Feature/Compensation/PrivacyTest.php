<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

// Money never leaves Compensación: no page outside it receives a salary, and the survey results cannot be cut by one
// (CLAUDE.md, Privacidad; spec «Permisos, privacidad y registro»).

/** The keys that carry money or a pay level, at any depth. */
const SALARY_KEYS = ['salary', 'daily_salary', 'daily_salary_cents', 'salary_cents', 'sbc', 'amount_cents'];

/**
 * Where in `$value` a salary key shows, as dotted paths.
 *
 * @param  array<array-key, mixed>  $value
 * @return list<string>
 */
function salaryKeysIn(array $value, string $path = 'props'): array
{
    $found = [];

    foreach ($value as $key => $item) {
        if (in_array(strtolower((string) $key), SALARY_KEYS, true)) {
            $found[] = $path.'.'.$key;
        } elseif (is_array($item)) {
            $found = [...$found, ...salaryKeysIn($item, $path.'.'.$key)];
        }
    }

    return $found;
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->evaluation = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'workers' => 30]);
    Employee::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->area->id]);

    foreach (range(1, 6) as $ignored) {
        AnonymousResponse::factory()->create(['campaign_id' => $this->evaluation->id, 'area_id' => $this->area->id, 'answers' => nom035Answers('nom035.guide-ii', 'almost_always')]);
    }
});

it('finds a salary key at any depth, whatever its case', function (): void {
    expect(salaryKeysIn(['rows' => [['person' => ['Daily_Salary' => 500]]], 'totals' => ['sbc' => 1]]))
        ->toBe(['props.rows.0.person.Daily_Salary', 'props.totals.sbc']);
});

it('exposes no salary in the props of employees, dashboard or results pages', function (string $url, TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => expect(salaryKeysIn($page->toArray()['props']))->toBe([]));
})->with([
    'dashboard' => fn (): string => route('tenant.dashboard', $this->tenant),
    'employees' => fn (): string => route('tenant.employees.index', $this->tenant),
    'employee import' => fn (): string => route('tenant.employees.import.create', $this->tenant),
    'campaigns' => fn (): string => route('tenant.campaigns.index', $this->tenant),
    'results' => fn (): string => route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id]),
    'report' => fn (): string => route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]),
    'Ley Silla' => fn (): string => route('tenant.seating.index', $this->tenant),
    'NOM-037' => fn (): string => route('tenant.telework.index', $this->tenant),
], [TenantRole::Owner, TenantRole::Hr]);

it('exposes no salary in the props of the employees pages of a union, nor in Compensación itself for now', function (string $name): void {
    $union = Tenant::factory()->union()->create();
    Employee::factory()->count(2)->create(['tenant_id' => $union->id, 'work_center_id' => null]);

    $this->actingAs(ownerOf($union))
        ->get(route($name, $union))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => expect(salaryKeysIn($page->toArray()['props']))->toBe([]));
})->with(['tenant.employees.index', 'tenant.employees.import.create', 'tenant.compensation.index']);

it('forbids adding salary or category/level as filters of survey results', function (array $hostile): void {
    $url = route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id]);
    $hr = memberOf($this->tenant, TenantRole::Hr);

    $plain = $this->actingAs($hr)->get($url)->assertOk()->inertiaProps();
    $filtered = $this->actingAs($hr)->get($url.'?'.http_build_query($hostile))->assertOk()->inertiaProps();

    // The page ignores them: same numbers, same groups, and the only filter it offers or echoes is the area.
    expect($filtered)->toBe($plain)
        ->and(array_keys($filtered['filters']))->toBe(['area']);
})->with([
    'salary' => [['salary' => '500']],
    'daily salary' => [['daily_salary' => '500', 'sbc' => '524.65']],
    'category' => [['category' => 'oficial', 'job_category' => 'oficial']],
    'level' => [['level' => '2', 'job_grade' => '2', 'job_grade_id' => '00000000-0000-4000-8000-000000000000']],
    'staff group and seniority' => [['staff_group' => 'union', 'hired_on' => '2020-01-01']],
]);
