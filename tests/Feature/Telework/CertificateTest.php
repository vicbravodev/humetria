<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Inertia\Testing\AssertableInertia as Assert;

// Rediseño v2 (decisiones A2 y D6): where NOM-037 does not apply, the center keeps a certificate to print, with the day, the
// center, the standard and who declared it. A page with print CSS, never a PDF.

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey']);
    $this->author = User::factory()->create(['name' => 'Rita Ramírez']);
});

function teleworkCertificate(): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.telework.certificate', [test()->tenant, test()->center->id]));
}

it('certifies that NOM-037 does not apply, with the day, the center and who declared it', function (TenantRole $role): void {
    TeleworkProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'declared_by' => $this->author->id, 'declared_on' => '2026-02-03']);

    $this->actingAs(memberOf($this->tenant, $role));

    teleworkCertificate()->assertInertia(fn (Assert $page) => $page->component('telework/certificate')
        ->where('workCenter.id', $this->center->id)
        ->where('workCenter.name', 'Oficinas Monterrey')
        ->has('workCenter.legalName')
        ->has('workCenter.address')
        ->where('declaredOn', '3 de febrero de 2026')
        ->where('declaredBy', 'Rita Ramírez'));
})->with([TenantRole::Owner, TenantRole::Hr, TenantRole::Viewer]);

it('has no certificate where someone teleworks, someone was registered since, or nothing was said', function (string $case): void {
    $where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id];

    match ($case) {
        'someone teleworks' => TeleworkProfile::factory()->teleworks()->create($where),
        'someone registered since' => [TeleworkProfile::factory()->create($where), TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create($where)->id])],
        'never answered' => null,
    };

    $this->actingAs(ownerOf($this->tenant));

    teleworkCertificate()->assertNotFound();
})->with(['someone teleworks', 'someone registered since', 'never answered']);

it('shows the Comisión only the certificate of a center it sits on', function (): void {
    TeleworkProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $member = memberOf($this->tenant, TenantRole::Committee);

    $this->actingAs($member);
    teleworkCertificate()->assertNotFound();

    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $member->id]);
    teleworkCertificate()->assertOk();
});
