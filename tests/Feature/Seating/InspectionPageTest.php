<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Seating\Http\Controllers\SeatingController;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\RoutePages;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SeatingController::class, RoutePages::class);

// Rediseño v2, board V3-Inspeccion (A17) for Ley Silla: by center, what an inspector would ask for of the thirteen
// requirements, what the center can show and what is missing (in the order of the route), each with its button.

beforeEach(function (): void {
    $this->travelTo('2026-11-02 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id];
    SeatingProfile::factory()->create($where);
    $position = StandingPosition::factory()->create($where);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'position_id' => $position->id, 'employee_id' => Employee::factory()->create($where)->id]);
});

function seatingInspection(?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.seating.inspection', [test()->tenant, ($center ?? test()->center)->id]));
}

it('splits the requirements into what is missing, in the order of the route, and what you can show', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    seatingInspection()->assertInertia(fn (Assert $page) => $page->component('seating/inspection')
        ->where('workCenter', ['id' => $this->center->id, 'name' => 'Planta Querétaro'])
        ->where('applicable', 11)
        ->where('ready', fn ($ready): bool => collect($ready)->pluck('numeral')->all() === ['R1'])
        ->where('ready.0', fn ($row): bool => $row['title'] === 'Los puestos de pie, con su Figura 1' && $row['action'] === 'Ver puestos' && $row['href'] === route('tenant.seating.center', [$this->tenant, $this->center->id]))
        ->where('missing', fn ($missing): bool => collect($missing)->pluck('numeral')->all() === ['R4', 'R6', 'R2', 'R3', 'R8', 'R9', 'R7', 'R10', 'R12', 'R13'])
        ->where('missing.0', fn ($row): bool => $row['detail'] === '1 puesto sin asiento' && $row['action'] === 'Registrar asientos')
        ->where('dossierHref', route('tenant.seating.dossier', [$this->tenant, $this->center->id])));
});

it('keeps what has nothing registered out of «Lo puedes mostrar», with what is missing to register', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    seatingInspection()->assertInertia(fn (Assert $page) => $page
        ->where('ready', fn ($ready): bool => collect($ready)->doesntContain(fn (array $row): bool => $row['state'] === 'pending'))
        ->where('missing', fn ($missing): bool => collect($missing)->where('state', 'pending')->pluck('detail', 'numeral')->all() === [
            'R6' => 'Aún no hay asientos registrados',
            'R3' => 'Aún no hay evaluaciones firmadas',
            'R8' => 'Aún no hay evaluaciones firmadas',
            'R9' => 'Aún no hay evaluaciones firmadas',
            'R7' => 'Aún no hay evaluaciones firmadas',
        ]));
});

it('shows a viewer what is missing without the ways to fix it nor the dossier', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    seatingInspection()->assertInertia(fn (Assert $page) => $page
        ->where('missing.0.href', null)
        ->where('dossierHref', null));
});

it('is open to the Comisión of the center only, and a 404 for another company or a made-up center', function (): void {
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);

    $this->actingAs($cora);
    seatingInspection()->assertOk()->assertInertia(fn (Assert $page) => $page->where('dossierHref', route('tenant.seating.dossier', [$this->tenant, $this->center->id])));
    seatingInspection(WorkCenter::factory()->for($this->tenant)->create())->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    seatingInspection(WorkCenter::factory()->create())->assertNotFound();
});

it('reads the page in a fixed number of queries', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->expectsDatabaseQueryCount(24);
    seatingInspection()->assertOk();
});
