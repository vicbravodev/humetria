<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\RoutePage;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RoutePage::class);

// Rediseño v2 (boards V3-Movil and V3-AlDia): the route of one standard in one center, with what cannot wait before it, and
// the all-done state with the next obligation once the six stages are done.

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-30 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
});

function routeOf(string $norm, ?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.work-centers.route', [test()->tenant, ($center ?? test()->plant)->id, $norm]));
}

it('sends NOM-037 to its own route page (board V2-Ruta-N037)', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    routeOf('nom037')->assertRedirect(route('tenant.telework.route', [$this->tenant, $this->plant->id]));
});

it('puts first the people of the center who wait for a clinical assessment, for whoever channels them', function (TenantRole $role, int $urgencies): void {
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $closed->id, 'requires_clinical_assessment' => true]);

    $this->actingAs(memberOf($this->tenant, $role));

    routeOf('nom035')->assertInertia(fn (Assert $page) => $page->has('urgencies', $urgencies)->has('until'));
})->with([
    'HR' => [TenantRole::Hr, 1],
    'a viewer' => [TenantRole::Viewer, 0],
]);

it('says that the center has to answer, instead of a route', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    routeOf('seating')->assertInertia(fn (Assert $page) => $page->where('route.answered', false));
});

it('answers 404 for a standard it does not know, and for an archived center, a made-up one or another company\'s', function (string $case): void {
    $this->actingAs(ownerOf($this->tenant));

    match ($case) {
        'unknown standard' => $this->get("/admin/acme/work-centers/{$this->plant->id}/routes/nom-999")->assertNotFound(),
        'archived' => routeOf('nom035', WorkCenter::factory()->for($this->tenant)->archived()->create())->assertNotFound(),
        'made-up' => $this->get('/admin/acme/work-centers/'.Illuminate\Support\Str::uuid().'/routes/nom035')->assertNotFound(),
        'another company' => routeOf('nom035', WorkCenter::factory()->create())->assertNotFound(),
    };
})->with(['unknown standard', 'archived', 'made-up', 'another company']);
