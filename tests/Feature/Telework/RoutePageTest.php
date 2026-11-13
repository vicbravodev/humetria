<?php

declare(strict_types=1);

use App\Modules\Telework\Http\Controllers\TeleworkController;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Telework\Support\TeleworkPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Inertia\Testing\AssertableInertia as Assert;

mutates(TeleworkController::class, TeleworkPages::class);

// Rediseño v2, board V2-Ruta-N037: the NOM-037 route of one center, its six stages with the rows of table 10.3 each one
// asks for, what cannot wait, and how ready the center is for an inspection.

beforeEach(function (): void {
    $this->travelTo('2026-10-30 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $hugo = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Hugo Saiz']);
    $this->person = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $hugo->id]);
});

function teleworkRoute(?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.telework.route', [test()->tenant, ($center ?? test()->center)->id]));
}

it('shows the stages of the center with each row of table 10.3 in its stage, and the accident that cannot wait first', function (): void {
    $notice = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->person->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    teleworkRoute()->assertInertia(fn (Assert $page) => $page->component('telework/route')
        ->where('workCenter', ['id' => $this->center->id, 'name' => 'Planta Querétaro'])
        ->where('teleworkers', 1)
        ->where('route.applies', true)
        ->where('route.done', [false, false, false, false, false, false])
        ->has('rows', 17)
        ->where('rows', fn ($rows): bool => collect($rows)->pluck('stage', 'numeral')->all() === [
            '5.1' => 2, '5.1.1' => 3, '5.1.2' => 2, '5.2' => 1, '5.3' => 1, '5.4 y 7' => 3, '5.5' => 3, '5.5.1' => 3, '5.6' => 1,
            '5.7' => 4, '5.8' => 1, '5.9 y 8' => 4, '5.10' => 1, '5.11' => 5, '5.12' => 3, '5.13' => 1, '5.14' => 1,
        ])
        ->where('rows.13', fn ($row): bool => $row['title'] === 'Accidentes avisados, con seguimiento' && $row['state'] === 'missing')
        ->where('accidents', [['person' => 'Hugo Saiz', 'count' => 1, 'since' => '2026-02-27', 'waiting' => 'hace 8 meses', 'href' => route('tenant.telework.notices.show', [$this->tenant, $notice->id])]])
        ->where('inspectionHref', route('tenant.telework.inspection', [$this->tenant, $this->center->id])));
});

it('keeps the accidents from a viewer', function (): void {
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->person->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    teleworkRoute()->assertInertia(fn (Assert $page) => $page->where('accidents', [])->where('rows.0.href', null));
});

it('sends a center that has not answered «¿Te aplica?» to answer it, and one where it does not apply to its certificate', function (): void {
    $empty = WorkCenter::factory()->for($this->tenant)->create();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    teleworkRoute($empty)->assertRedirect(route('tenant.telework.applies.show', [$this->tenant, $empty->id]));
    TeleworkProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $empty->id, 'declared_on' => '2026-10-03']);
    teleworkRoute($empty)->assertRedirect(route('tenant.telework.certificate', [$this->tenant, $empty->id]));
});

it('opens the NOM-037 route from the route of a center', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.work-centers.route', [$this->tenant, $this->center->id, 'nom037']))
        ->assertRedirect(route('tenant.telework.route', [$this->tenant, $this->center->id]));
});

it('is open to the Comisión of the center only, and a 404 for another company or a made-up center', function (): void {
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);

    $this->actingAs($cora);
    teleworkRoute()->assertOk();
    teleworkRoute(WorkCenter::factory()->for($this->tenant)->create())->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    teleworkRoute(WorkCenter::factory()->create())->assertNotFound();
});

it('reads the page in a fixed number of queries', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->expectsDatabaseQueryCount(42);
    teleworkRoute()->assertOk();
});
