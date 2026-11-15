<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Seating\Http\Controllers\SeatingController;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\RoutePages;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SeatingController::class, RoutePages::class);

// Rediseño v2, board V2-Ruta-Silla: the Ley Silla route of one center, its six stages with the thirteen requirements each
// one asks for, what cannot wait, how the people take part and how ready the center is for an inspection.

beforeEach(function (): void {
    $this->travelTo('2026-11-02 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id];
    SeatingProfile::factory()->create($where);
    $position = StandingPosition::factory()->create($where + ['name' => 'Operador de línea']);

    foreach (['Hugo Saiz', 'Ana Ruiz'] as $name) {
        PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'position_id' => $position->id, 'employee_id' => Employee::factory()->create($where + ['name' => $name])->id]);
    }
});

function seatingRoute(?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.seating.route', [test()->tenant, ($center ?? test()->center)->id]));
}

it('shows the stages of the center with each requirement in its stage, in plain words and with its way to fix it', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    seatingRoute()->assertInertia(fn (Assert $page) => $page->component('seating/route')
        ->where('workCenter', ['id' => $this->center->id, 'name' => 'Planta Querétaro'])
        ->where('people', 2)
        ->where('positions', 1)
        ->where('route.applies', true)
        ->where('route.done', [true, false, false, false, false, false])
        ->has('rows', 13)
        ->where('rows', fn ($rows): bool => collect($rows)->pluck('stage', 'numeral')->all() === [
            'R1' => 1, 'R2' => 3, 'R3' => 3, 'R4' => 2, 'R5' => 2, 'R6' => 2, 'R7' => 4, 'R8' => 3, 'R9' => 3, 'R10' => 5, 'R11' => 4, 'R12' => 6, 'R13' => 6,
        ])
        ->where('rows.1', [
            'numeral' => 'R2',
            'title' => 'Cada persona que trabaja de pie, evaluada',
            'detail' => '2 personas sin evaluar',
            'state' => 'missing',
            'stage' => 3,
            'href' => route('tenant.seating.assessments.index', [$this->tenant, $this->center->id]),
            'action' => 'Evaluar',
        ])
        ->where('rows.0.state', 'done')
        ->where('rows.0.detail', null)
        ->where('rows.12.href', route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))
        ->where('reports', null)
        ->where('inputs', ['answered' => 0, 'people' => 2])
        ->where('inspectionHref', route('tenant.seating.inspection', [$this->tenant, $this->center->id])));
});

it('puts first the seats reported long ago that nobody resolved, and how many people answered their two questions', function (): void {
    $where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id];
    SeatReport::factory()->count(2)->create($where + ['created_at' => '2026-10-10 10:00:00']);
    SeatReport::factory()->create($where);
    PersonInput::factory()->create($where + ['employee_id' => Employee::query()->where('name', 'Hugo Saiz')->value('id')]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    seatingRoute()->assertInertia(fn (Assert $page) => $page
        ->where('reports', ['count' => 2, 'since' => '2026-10-10', 'waiting' => 'hace 3 semanas', 'href' => route('tenant.seating.reports.index', [$this->tenant, $this->center->id])])
        ->where('inputs', ['answered' => 1, 'people' => 2]));
});

it('shows the viewer the stages without a way into them, nor the reports and answers', function (): void {
    SeatReport::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'created_at' => '2026-10-01 10:00:00']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    seatingRoute()->assertInertia(fn (Assert $page) => $page
        ->where('rows', fn ($rows): bool => collect($rows)->every(fn (array $row): bool => $row['href'] === null && $row['action'] === null))
        ->where('reports', null)
        ->where('inputs', null));
});

it('sends a center that has not answered «¿Te aplica?» to answer it, and one where it does not apply to its certificate', function (): void {
    $empty = WorkCenter::factory()->for($this->tenant)->create();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    seatingRoute($empty)->assertRedirect(route('tenant.work-centers.applies', [$this->tenant, $empty->id, 'norm' => 'seating']));
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $empty->id, 'no_standing_positions' => true, 'declared_on' => '2026-10-03']);
    seatingRoute($empty)->assertRedirect(route('tenant.seating.certificate', [$this->tenant, $empty->id]));
});

it('opens the Ley Silla route from the route of a center', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.work-centers.route', [$this->tenant, $this->center->id, 'seating']))
        ->assertRedirect(route('tenant.seating.route', [$this->tenant, $this->center->id]));
});

it('is open to the Comisión of the center only, and a 404 for another company or a made-up center', function (): void {
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);

    $this->actingAs($cora);
    seatingRoute()->assertOk();
    seatingRoute(WorkCenter::factory()->for($this->tenant)->create())->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    seatingRoute(WorkCenter::factory()->create())->assertNotFound();
    $this->get("/admin/acme/seating/centers/{$this->center->id}x/route")->assertNotFound();
});

it('reads the page in a fixed number of queries', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->expectsDatabaseQueryCount(26);
    seatingRoute()->assertOk();
});
