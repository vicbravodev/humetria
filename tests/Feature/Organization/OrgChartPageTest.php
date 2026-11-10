<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\OrgChartController;
use App\Modules\Organization\Http\Controllers\OrgChartPeopleController;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(OrgChartController::class, OrgChartPeopleController::class);

// 8 of every page of the panel (company, membership, roles, shared props) and 10 of the org chart: the counts, the
// roots (3), the chain (2) and the reports of the focus (3), each with the work centers, and the name of the filter.
const ORG_CHART_PAGE_QUERIES = 18;
// 5 of every request of the panel, the person, and their reports (3).
const ORG_CHART_REPORTS_QUERIES = 9;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta']);
    $this->production = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $this->sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $this->place = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->production->id];
});

/** Dir leads Gte, who leads Ana (in Ventas) and Beto. */
function orgChartLine(): array
{
    $dir = Employee::factory()->create([...test()->place, 'name' => 'Dir', 'job_title' => 'Directora']);
    $gte = Employee::factory()->reportingTo($dir)->create([...test()->place, 'name' => 'Gte']);
    $ana = Employee::factory()->reportingTo($gte)->create([...test()->place, 'name' => 'Ana', 'job_title' => 'Vendedora', 'area_id' => test()->sales->id]);
    $beto = Employee::factory()->reportingTo($gte)->create([...test()->place, 'name' => 'Beto']);

    return [$dir, $gte, $ana, $beto];
}

/** Every key of a nested array, at any depth. */
function keysWithin(array $value): array
{
    $keys = [];
    array_walk_recursive($value, function (mixed $leaf, int|string $key) use (&$keys): void {
        $keys[] = $key;
    });

    foreach ($value as $key => $child) {
        $keys = [...$keys, $key, ...(is_array($child) ? keysWithin($child) : [])];
    }

    return array_values(array_unique(array_filter($keys, is_string(...))));
}

it('shows the org chart to whoever may see it', function (TenantRole $role, int $status, bool $manages): void {
    orgChartLine();

    $response = $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.org-chart.index', $this->tenant))->assertStatus($status);

    if ($status === 200) {
        $response->assertInertia(fn (Assert $page) => $page
            ->component('organization/org-chart')
            ->where('can_manage', $manages)
            ->where('total_people', 4)
            ->where('with_manager', 3)
            ->where('chain', [])
            ->where('focus', null)
            ->where('filters', ['work_center' => null, 'area' => null])
            ->where('roots.total', 1)
            ->where('roots.data.0.name', 'Dir')
            ->where('roots.data.0.direct_reports', 1)
            ->where('roots.data.0.total_reports', 3));
    }
})->with([
    'owner' => [TenantRole::Owner, 200, true],
    'hr' => [TenantRole::Hr, 200, true],
    'viewer' => [TenantRole::Viewer, 200, false],
    'committee' => [TenantRole::Committee, 403, false],
]);

it('is not for outsiders nor for a union account', function (): void {
    $this->get(route('tenant.org-chart.index', $this->tenant))->assertRedirect(route('login'));

    $union = Tenant::factory()->union()->create();

    $this->actingAs(memberOf($union, TenantRole::Owner))->get(route('tenant.org-chart.index', $union))->assertNotFound();
});

it('pages a company just loaded, where nobody has a manager yet', function (): void {
    Employee::factory()->count(120)->create($this->place);
    Employee::factory()->deactivated()->create($this->place);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->has('roots.data', 50)
            ->where('roots.total', 120)
            ->where('roots.last_page', 3)
            ->where('with_manager', 0)
            ->where('total_people', 120));
});

it('keeps the filters in the links to the other pages of the roots', function (): void {
    Employee::factory()->count(51)->create($this->place);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', [$this->tenant, 'area' => $this->production->id]))
        ->assertInertia(fn (Assert $page) => $page->where('roots.next_page_url', fn (string $url): bool => str_contains($url, 'area='.$this->production->id) && str_contains($url, 'page=2')));
});

it('focuses a person: the chain from the top down to them and their own reports', function (): void {
    [$dir, $gte, $ana] = orgChartLine();

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', [$this->tenant, 'focus' => $gte->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('chain.0.id', $dir->id)
            ->where('chain.1.id', $gte->id)
            ->has('chain', 2)
            ->where('focus.id', $gte->id)
            ->where('focus.children.total', 2)
            ->where('focus.children.data.0.id', $ana->id)
            ->where('focus.children.data.0.job_title', 'Vendedora')
            ->where('focus.children.data.0.area', 'Ventas')
            ->where('focus.children.data.0.work_center', 'Planta'));
});

it('does not find a focus that is not an active person of the company', function (Closure $focus): void {
    orgChartLine();

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', [$this->tenant, 'focus' => $focus()]))->assertNotFound();
})->with([
    'another company' => fn () => Employee::factory()->create()->id,
    'nobody' => fn () => (string) Str::uuid(),
    'someone who left' => fn () => Employee::factory()->deactivated()->create(test()->place)->id,
    'not an id' => fn () => 'ana',
]);

it('narrows the tree by work center and area, naming each chosen filter', function (): void {
    [, , $ana] = orgChartLine();
    Employee::factory()->create([...$this->place, 'name' => 'Sola']);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', [$this->tenant, 'area' => $this->sales->id, 'work_center' => $this->plant->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', [
                'work_center' => ['value' => $this->plant->id, 'label' => 'Planta'],
                'area' => ['value' => $this->sales->id, 'label' => 'Ventas'],
            ])
            ->where('roots.total', 1)
            ->where('roots.data.0.name', 'Dir')
            ->where('roots.data.0.matches', false)
            // The counts of the company are not narrowed.
            ->where('total_people', 5));

    // Something that is not an id filters nothing.
    $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', [$this->tenant, 'area' => 'ventas', 'work_center' => 'planta']))
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['work_center' => null, 'area' => null])->where('roots.total', 2));
});

it('gives the reports of a person a page at a time', function (): void {
    [, $gte, $ana, $beto] = orgChartLine();

    $this->actingAs($this->viewer)->getJson(route('tenant.org-chart.reports', [$this->tenant, $gte->id]))
        ->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'last_page', 'next_page_url'])
        ->assertJsonPath('data.0.id', $ana->id)
        ->assertJsonPath('data.1.id', $beto->id)
        ->assertJsonPath('total', 2)
        ->assertJsonPath('next_page_url', null);

    $this->getJson(route('tenant.org-chart.reports', [$this->tenant, $gte->id, 'area' => $this->sales->id, 'work_center' => $this->plant->id]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ana->id);

    Employee::factory()->count(60)->reportingTo($ana)->create($this->place);

    $this->getJson(route('tenant.org-chart.reports', [$this->tenant, $ana->id, 'page' => 2, 'area' => $this->production->id]))
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('current_page', 2)
        ->assertJsonPath('last_page', 2);
    $this->getJson(route('tenant.org-chart.reports', [$this->tenant, $ana->id, 'area' => $this->production->id]))
        ->assertJsonPath('next_page_url', fn (string $url): bool => str_contains($url, 'page=2') && str_contains($url, 'area='.$this->production->id));
});

it('gives the reports only to whoever may see the org chart', function (): void {
    [, $gte] = orgChartLine();

    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->getJson(route('tenant.org-chart.reports', [$this->tenant, $gte->id]))->assertForbidden();
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->getJson(route('tenant.org-chart.people.search', $this->tenant))->assertForbidden();
});

it('never sends contact data of anyone', function (): void {
    [, $gte, $ana] = orgChartLine();

    $props = $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', [$this->tenant, 'focus' => $ana->id]))->assertOk()->inertiaProps();
    $reports = $this->getJson(route('tenant.org-chart.reports', [$this->tenant, $gte->id]))->json();
    $found = $this->getJson(route('tenant.org-chart.people.search', [$this->tenant, 'q' => 'ana']))->json();

    expect(keysWithin(['roots' => $props['roots'], 'chain' => $props['chain'], 'focus' => $props['focus']]))->toContain('name', 'job_title')->not->toContain('email', 'mobile')
        ->and(keysWithin($reports))->toContain('name')->not->toContain('email', 'mobile')
        ->and(json_encode([$props['roots'], $props['chain'], $props['focus'], $reports, $found]))->not->toContain($ana->email, (string) $ana->mobile)
        ->and($found)->toBe([['value' => $ana->id, 'label' => 'Ana · Vendedora']]);
});

it('reads the page and the reports with the same queries whatever the size of the company', function (int $people): void {
    [, $gte, $ana] = orgChartLine();
    Employee::factory()->count($people - 4)->reportingTo($gte)->create($this->place);
    $this->actingAs($this->viewer);
    // The first request warms what is cached between requests (roles and permissions).
    $this->get(route('tenant.org-chart.index', $this->tenant))->assertOk();

    $this->expectsDatabaseQueryCount(ORG_CHART_PAGE_QUERIES + ORG_CHART_REPORTS_QUERIES);

    $this->get(route('tenant.org-chart.index', [$this->tenant, 'focus' => $ana->id, 'area' => $this->sales->id]))->assertOk();
    $this->getJson(route('tenant.org-chart.reports', [$this->tenant, $gte->id]))->assertOk();
})->with([5, 500]);

it('searches active people of the company to focus one, a few at a time', function (): void {
    Employee::factory()->count(21)->create([...$this->place, 'job_title' => null]);
    $first = Employee::factory()->create([...$this->place, 'name' => '  Aaa', 'job_title' => null]);
    Employee::factory()->deactivated()->create([...$this->place, 'name' => 'Zulema que se fue']);
    $number = Employee::factory()->create([...$this->place, 'name' => 'Otra', 'employee_number' => 'ZUL-1', 'job_title' => null]);

    $this->actingAs($this->viewer)->getJson(route('tenant.org-chart.people.search', $this->tenant))
        ->assertOk()
        ->assertJsonCount(20)
        ->assertJsonPath('0.value', $first->id);
    $this->getJson(route('tenant.org-chart.people.search', [$this->tenant, 'q' => ' zúl ']))->assertExactJson([['value' => $number->id, 'label' => 'Otra']]);
});
