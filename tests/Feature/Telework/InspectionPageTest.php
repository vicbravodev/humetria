<?php

declare(strict_types=1);

use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Support\TeleworkPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Inertia\Testing\AssertableInertia as Assert;

mutates(TeleworkPages::class);

// Rediseño v2, board V3-Inspeccion (decisión A17): what an inspector would ask for today in a center, from the rows of
// table 10.3 — what you can show and what is missing, each with its way to fix it — and the dossier to download.

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->person = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id]);
});

function inspectionOf(?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.telework.inspection', [test()->tenant, ($center ?? test()->center)->id]));
}

it('splits the rows into what is missing, urgent first and then in the order of the route, and what you can show', function (): void {
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->person->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    inspectionOf()->assertInertia(fn (Assert $page) => $page->component('telework/inspection')
        ->where('workCenter', ['id' => $this->center->id, 'name' => 'Planta Querétaro'])
        ->where('applicable', 17)
        ->where('ready', fn ($ready): bool => collect($ready)->pluck('numeral')->all() === ['5.1', '5.7'])
        ->where('ready.0', fn ($row): bool => $row['title'] === 'Lista de personas en teletrabajo' && $row['href'] === route('tenant.telework.center', [$this->tenant, $this->center->id]))
        ->where('missing.0', fn ($row): bool => $row['numeral'] === '5.11' && $row['title'] === 'Accidentes avisados, con seguimiento' && $row['action'] === 'Ver los avisos')
        ->where('missing', fn ($missing): bool => collect($missing)->pluck('numeral')->all() === ['5.11', '5.2', '5.3', '5.6', '5.8', '5.10', '5.13', '5.14', '5.1.2', '5.1.1', '5.4 y 7', '5.5', '5.5.1', '5.12', '5.9 y 8'])
        ->where('dossierHref', route('tenant.telework.dossier', [$this->tenant, $this->center->id])));
});

it('shows a viewer what is missing without the ways to fix it nor the dossier', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    inspectionOf()->assertInertia(fn (Assert $page) => $page
        ->where('missing.0.href', null)
        ->where('dossierHref', null));
});

it('is open to the Comisión of the center only, and a 404 for another company or a made-up center', function (): void {
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    $other = WorkCenter::factory()->for($this->tenant)->create();

    $this->actingAs($cora);
    inspectionOf()->assertOk();
    inspectionOf($other)->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    inspectionOf(WorkCenter::factory()->create())->assertNotFound();
    $this->get(route('tenant.telework.inspection', [$this->tenant, 'not-a-uuid']))->assertNotFound();
});

it('reads the page in a fixed number of queries', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->expectsDatabaseQueryCount(28);
    inspectionOf()->assertOk();
});
