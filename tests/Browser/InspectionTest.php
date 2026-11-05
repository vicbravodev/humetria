<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;

// Board V3-Inspeccion: what an inspector would ask for today in a center, and the dossier, whole or what it has.
it('shows what you could show an inspector and what is missing, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id])->id]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $person->id]);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    visitPage('telework/inspection', "/admin/acme/telework/centers/{$plant->id}/inspection", $dark, $mobile)
        ->assertSee('Si hoy llegara una inspección a Planta Querétaro')
        ->assertSeeIn('[aria-labelledby="missing"] li:first-child p.font-medium', 'Accidentes avisados, con seguimiento')
        ->assertSeeIn('[aria-labelledby="ready"] li:first-child p.font-medium', 'Lista de personas en teletrabajo')
        ->assertSee('Descargar expediente')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('opens the dossier with only what the center can show', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    visit("/admin/acme/telework/centers/{$plant->id}/inspection")
        ->click('Solo lo que tienes')
        ->click('Abrir expediente')
        ->assertPathIs("/admin/acme/telework/centers/{$plant->id}/dossier")
        ->assertQueryStringHas('ready', '1')
        ->assertNoJavaScriptErrors();
});
