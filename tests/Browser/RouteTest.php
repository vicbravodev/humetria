<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Results\Models\ReportDetails;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;

/** Acme with Planta Querétaro, where Hugo Saiz teleworks and reported an accident, and Rita of RH signed in. */
function routeCompany(): WorkCenter
{
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    $hugo = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Hugo Saiz']);
    WorkerNoticeFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $hugo->id])->id]);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    return $plant;
}

// Rediseño v2 (boards V3-Movil and V2-Ruta-N037): the route of one standard in one center, what cannot wait first. NOM-037
// has its own page, wide or on a phone.
it('shows the route of a center as the redesign draws it, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = routeCompany();

    visitPage('telework/route', "/admin/acme/work-centers/{$plant->id}/routes/nom037", $dark, $mobile)
        ->assertSee('Tu ruta para cumplir con el teletrabajo')
        ->assertSeeIn('[aria-labelledby="before"]', 'Hugo Saiz avisó un accidente el 27 feb')
        ->assertSeeIn('ol[aria-label="Etapas de la NOM-037"] li:first-child', 'Aquí vas')
        ->assertSeeIn('ol[aria-label="Etapas de la NOM-037"] li:first-child', 'Define las reglas del teletrabajo')
        ->assertSeeIn('ol[aria-label="Etapas de la NOM-037"] li:nth-child(4)', 'Hecho')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('opens the route of a center from the panel', function (): void {
    $plant = routeCompany();

    visit('/admin/acme')
        ->click('[data-norm="nom037"] li a[href$="/routes/nom037"]')
        ->assertSee('Tu ruta para cumplir con el teletrabajo')
        ->assertNoJavaScriptErrors();

    expect($plant->name)->toBe('Planta Querétaro');
});

// Board V3-AlDia: the six stages done, the next evaluation and what stays alive.
it('shows a route all done with its dossier, its next evaluation and what stays alive', function (bool $dark): void {
    $plant = routeCompany();
    $where = ['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id];
    DocumentCenter::factory()->create($where);
    $closed = Campaign::factory()->closed()->create($where);
    ResultPublication::factory()->create(['tenant_id' => $plant->tenant_id, 'campaign_id' => $closed->id]);
    ReportDetails::factory()->create(['tenant_id' => $plant->tenant_id, 'campaign_id' => $closed->id]);

    visitPage('tenancy/nom035-route', "/admin/acme/work-centers/{$plant->id}/routes/nom035", $dark)
        ->assertSee('Todo al día en la NOM-035')
        ->assertSee('La siguiente evaluación toca antes del')
        ->assertSee('Descargar expediente')
        ->assertSeeIn('[aria-labelledby="alive"]', 'Si alguien vive un acontecimiento traumático severo')
        ->assertSeeIn('[aria-labelledby="cycle"]', 'Nueva evaluación');
})->with(['light' => false, 'dark' => true]);
