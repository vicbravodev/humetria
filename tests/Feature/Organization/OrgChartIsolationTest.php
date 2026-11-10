<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->mine = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Mía']);
    // Another company, with its own line.
    $this->theirBoss = Employee::factory()->create(['name' => 'Ajena']);
    $this->theirs = Employee::factory()->reportingTo($this->theirBoss)->create(['name' => 'Ajeno']);
});

it('shows only the people of the company in the tree', function (): void {
    $this->actingAs($this->owner)->get(route('tenant.org-chart.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roots.total', 1)
            ->where('roots.data.0.id', $this->mine->id)
            ->where('total_people', 1)
            ->where('with_manager', 0));
});

it('does not find a person of another company: the same 404 as one that does not exist', function (): void {
    $this->actingAs($this->owner);

    $this->get(route('tenant.org-chart.index', [$this->tenant, 'focus' => $this->theirs->id]))->assertNotFound();
    $this->getJson(route('tenant.org-chart.reports', [$this->tenant, $this->theirBoss->id]))->assertNotFound();
    $this->getJson(route('tenant.org-chart.reports', [$this->tenant, (string) Str::uuid()]))->assertNotFound();
    $this->getJson(route('tenant.org-chart.people.search', [$this->tenant, 'q' => 'aje']))->assertExactJson([]);
});

it('does not open the org chart of a company to a member of another', function (): void {
    $other = Tenant::factory()->create();

    $this->actingAs(memberOf($other, TenantRole::Owner))->get(route('tenant.org-chart.index', $this->tenant))->assertNotFound();
    $this->getJson(route('tenant.org-chart.reports', [$this->tenant, $this->mine->id]))->assertNotFound();
    $this->getJson(route('tenant.org-chart.people.search', $this->tenant))->assertNotFound();
});
