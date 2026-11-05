<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Controllers\RouteController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;

mutates(RouteController::class);

// Rediseño v2: the route of one standard in one center, the panel's way in, sends each standard to its own route page.

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

// Rediseño v2: NOM-035 has its own route page too (board V2-Ruta-N035), where who waits to be channeled goes first
// (tests/Feature/Tenancy/Nom035RoutePageTest.php).
it('sends NOM-035 to its own route page (board V2-Ruta-N035)', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    routeOf('nom035')->assertRedirect(route('tenant.nom035.route', [$this->tenant, $this->plant->id]));
});

it('sends Ley Silla to its own route page (board V2-Ruta-Silla)', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    routeOf('seating')->assertRedirect(route('tenant.seating.route', [$this->tenant, $this->plant->id]));
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
