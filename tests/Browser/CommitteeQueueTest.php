<?php

declare(strict_types=1);

use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;

// Board V2-Comision (decisión A5): a member of the Comisión, with their account, lands on their queue and only that.
it('shows a member of the Comisión their queue, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    $ruben = memberOf($tenant, TenantRole::Committee);
    $ruben->update(['name' => 'Rubén Ortiz']);
    CommitteeAssignment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'user_id' => $ruben->id]);
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Hugo Saiz'])->id]);
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $tenant->id, 'workplace_id' => WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $person->id])->id]);
    test()->actingAs($ruben);

    visitPage('telework/queue', '/admin/acme', $dark, $mobile)
        ->assertSee('Hola, Rubén')
        ->assertSee('La Comisión tiene 1 cosa pendiente.')
        ->assertSee('Valida 1 lugar de teletrabajo · Planta Querétaro')
        ->assertSee('Hugo Saiz contestó la lista de su lugar')
        ->assertSee('Qué puedes ver aquí')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

// C-11: a center where people telework and the checklist is not published blocks the Comisión, and its queue says so.
it('tells the Comisión that its center has no checklist published', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    $ruben = memberOf($tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'user_id' => $ruben->id]);
    TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id])->id]);
    test()->actingAs($ruben);

    visit('/admin/acme')
        ->assertSee('Publica la lista de verificación · Planta Querétaro')
        ->assertSee('no tiene lista de verificación publicada')
        ->assertNoJavaScriptErrors();
});

// Rediseño v2 (PR 3): the Ley Silla assessments to sign wait in the same queue, with their standard.
it('shows the Comisión the Ley Silla assessments it has to sign', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    $ruben = memberOf($tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'user_id' => $ruben->id]);
    RiskAssessment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Óscar Peña'])->id]);
    test()->actingAs($ruben);

    visit('/admin/acme')
        ->assertSee('Firma 1 evaluación de trabajo de pie · Planta Querétaro')
        ->assertSee('Ley Silla')
        ->click('Revisar y firmar')
        ->assertScript(pathBecomes("/admin/acme/seating/centers/{$plant->id}/assessments"), true)
        ->assertPathIs("/admin/acme/seating/centers/{$plant->id}/assessments")
        ->assertNoJavaScriptErrors();
});
