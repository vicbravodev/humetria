<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\OrgChartPeopleController;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\ReportingGaps;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(OrgChartPeopleController::class, ReportingGaps::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta']);
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $this->place = ['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id, 'area_id' => $area->id];
    $this->dir = Employee::factory()->create([...$this->place, 'name' => 'Dir']);
    Employee::factory()->reportingTo($this->dir)->create([...$this->place, 'name' => 'Gte']);
    $this->lupe = Employee::factory()->create([...$this->place, 'name' => 'Lupe Sola', 'job_title' => 'Analista']);
    $this->memo = Employee::factory()->create([...$this->place, 'name' => 'Memo Solo']);
});

it('lists, by name, the roots that lead nobody: the ones the tree does not draw yet', function (): void {
    $this->actingAs($this->owner)->getJson(route('tenant.org-chart.people.unassigned', $this->tenant))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('data.0', ['id' => $this->lupe->id, 'name' => 'Lupe Sola', 'job_title' => 'Analista', 'area' => 'Producción', 'work_center' => 'Planta'])
        ->assertJsonPath('data.1.id', $this->memo->id)
        ->assertJsonCount(2, 'data');
});

it('searches them without caring about case or accents, on the server', function (): void {
    $this->actingAs($this->owner)->getJson(route('tenant.org-chart.people.unassigned', [$this->tenant, 'q' => 'LÚPE']))
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $this->lupe->id);
});

it('pages them and keeps the search in the next page', function (): void {
    Employee::factory()->count(30)->create([...$this->place, 'job_title' => null]);

    $this->actingAs($this->owner)->getJson(route('tenant.org-chart.people.unassigned', $this->tenant))
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('total', 32)
        ->assertJsonPath('next_page_url', fn (string $url): bool => str_contains($url, 'page=2'));
});

it('is for whoever may change the org chart, not for who only sees it', function (TenantRole $role, int $status): void {
    $user = $role === TenantRole::Owner ? $this->owner : memberOf($this->tenant, $role);

    $this->actingAs($user)->getJson(route('tenant.org-chart.people.unassigned', $this->tenant))->assertStatus($status);
})->with([
    'owner' => [TenantRole::Owner, 200],
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 403],
    'committee' => [TenantRole::Committee, 403],
]);

it('does not give the people of another company, nor contact data', function (): void {
    Employee::factory()->create(['name' => 'Ajena']);

    $response = $this->actingAs($this->owner)->getJson(route('tenant.org-chart.people.unassigned', $this->tenant))->assertOk();

    expect($response->json('data.*.name'))->not->toContain('Ajena')
        ->and(json_encode($response->json()))->not->toContain('email', 'mobile', (string) $this->lupe->email);

    $this->actingAs(memberOf(Tenant::factory()->create(), TenantRole::Owner))->getJson(route('tenant.org-chart.people.unassigned', $this->tenant))->assertNotFound();
});

it('reads them with the same queries whatever the size of the company', function (int $people): void {
    Employee::factory()->count($people)->create($this->place);
    $this->actingAs($this->owner);
    $this->getJson(route('tenant.org-chart.people.unassigned', $this->tenant))->assertOk();

    // 5 of every request of the panel, the page and its count, the areas of the page and the work centers.
    $this->expectsDatabaseQueryCount(9);

    $this->getJson(route('tenant.org-chart.people.unassigned', $this->tenant))->assertOk();
})->with([3, 100]);
