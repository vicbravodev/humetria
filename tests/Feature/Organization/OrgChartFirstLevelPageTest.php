<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\OrgChartController;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(OrgChartController::class);

// 8 of every page of the panel and 11 of the chart without a focus: the counts, the indicators (2), the roots (3) and
// their first level (2), each with the work centers and who is away today, and the people with no place in the chart (1).
const PLAIN_ORG_CHART_QUERIES = 19;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $this->place = ['tenant_id' => $this->tenant->id];
});

it('sends with the roots the first level of each, so the tree opens with two levels', function (): void {
    $dir = Employee::factory()->create([...$this->place, 'name' => 'Dir']);
    $other = Employee::factory()->create([...$this->place, 'name' => 'Otra']);
    $gte = Employee::factory()->reportingTo($dir)->create([...$this->place, 'name' => 'Gte']);
    Employee::factory()->reportingTo($dir)->create([...$this->place, 'name' => 'Ana']);
    Employee::factory()->reportingTo($gte)->create([...$this->place, 'name' => 'Nieta']);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->has('first_level.'.$dir->id, 2)
            ->where('first_level.'.$dir->id.'.0.name', 'Ana')
            ->where('first_level.'.$dir->id.'.1.name', 'Gte')
            ->where('first_level.'.$dir->id.'.1.direct_reports', 1)
            ->where('first_level.'.$dir->id.'.1.total_reports', 1)
            // Whoever leads nobody has no entry, and the second level is not sent.
            ->missing('first_level.'.$other->id)
            ->has('first_level', 1));
});

it('narrows the first level by the filters, as the roots', function (): void {
    $dir = Employee::factory()->create([...$this->place, 'name' => 'Dir']);
    Employee::factory()->reportingTo($dir)->create([...$this->place, 'name' => 'Gte']);
    Employee::factory()->reportingTo($dir)->create([...$this->place, 'name' => 'Ana', 'area_id' => $this->sales->id]);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', [$this->tenant, 'area' => $this->sales->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('first_level.'.$dir->id, 1)
            ->where('first_level.'.$dir->id.'.0.name', 'Ana'));
});

it('does not send the first level of another company, nor contact data', function (): void {
    $mine = Employee::factory()->create([...$this->place, 'name' => 'Mía']);
    $mineReport = Employee::factory()->reportingTo($mine)->create([...$this->place, 'name' => 'Mi reporte']);
    $theirBoss = Employee::factory()->create(['name' => 'Ajena']);
    Employee::factory()->reportingTo($theirBoss)->create(['name' => 'Ajeno']);

    $props = $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', $this->tenant))->assertOk()->inertiaProps();

    expect(array_keys($props['first_level']))->toBe([$mine->id])
        ->and(json_encode($props['first_level']))->not->toContain('Ajeno', 'email', 'mobile', (string) $mineReport->email);
});

it('reads the first level of any number of roots with the same queries', function (int $roots): void {
    foreach (range(1, $roots) as $number) {
        $boss = Employee::factory()->create([...$this->place, 'name' => "Raíz {$number}"]);
        Employee::factory()->count(3)->reportingTo($boss)->create($this->place);
    }
    $this->actingAs($this->viewer);
    // The first request warms what is cached between requests (roles and permissions).
    $this->get(route('tenant.org-chart.index', $this->tenant))->assertOk();

    $this->expectsDatabaseQueryCount(PLAIN_ORG_CHART_QUERIES);

    $this->get(route('tenant.org-chart.index', $this->tenant))->assertOk();
})->with([1, 40]);

it('says how many people have no manager and lead nobody: the ones the chart does not draw', function (): void {
    $dir = Employee::factory()->create([...$this->place, 'name' => 'Dir']);
    Employee::factory()->reportingTo($dir)->create($this->place);
    Employee::factory()->count(2)->create($this->place);
    Employee::factory()->deactivated()->create($this->place);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('without_manager', 2));
});
