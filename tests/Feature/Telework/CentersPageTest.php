<?php

declare(strict_types=1);

use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Telework\Support\TeleworkPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkDocumentFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Inertia\Testing\AssertableInertia as Assert;

mutates(TeleworkPages::class);

// Rediseño v2, board V2-Centros-N037: NOM-037 in every center — what is done once for the company, and where each center
// stands in its route with what comes next.

beforeEach(function (): void {
    $this->travelTo('2026-10-30 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->offices = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey']);
    $hugo = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Hugo Saiz']);
    $this->person = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $hugo->id]);
    TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id])->id]);
});

it('says how many people telework in how many centers, and what is done once for the whole company', function (): void {
    foreach ([DocumentKind::Policy, DocumentKind::RiskInformation] as $kind) {
        TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'kind' => $kind]);
    }
    TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id, 'kind' => DocumentKind::RiskInformation]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('summary', ['teleworkers' => 2, 'centers' => 1])
        ->where('company', ['policyCenters' => 1, 'documentsReady' => 1, 'centers' => 2])
        ->where('canManage', true));
});

it('tells each center where it stands in its route and the accident that waits there', function (): void {
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->person->id]);
    TeleworkProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id, 'declared_on' => '2026-10-03']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('centers.data.0.name', 'Oficinas Monterrey')
        ->where('centers.data.0.route', ['applies' => false, 'done' => [], 'status' => 'Nadie teletrabaja más del 40 % · constancia del 3 oct 2026', 'answered' => true])
        ->where('centers.data.1.route.applies', true)
        ->where('centers.data.1.route.done', [false, false, false, false, false, false])
        ->where('centers.data.1.accident', 'Hugo Saiz')
        ->where('centers.data.0.accident', null));
});

it('keeps the accidents from a viewer', function (): void {
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->person->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    $this->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('centers.data.1.accident', null));
});
