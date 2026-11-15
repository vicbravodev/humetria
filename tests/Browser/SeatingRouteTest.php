<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

/** A center where two people work standing in one position, with a seat report nobody resolved for a month. */
function standingPlant(): WorkCenter
{
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    $where = ['tenant_id' => $tenant->id, 'work_center_id' => $plant->id];
    SeatingProfile::factory()->create($where);
    $position = StandingPosition::factory()->create($where + ['name' => 'Operador de línea']);

    foreach (['Hugo Saiz', 'Ana Ruiz'] as $name) {
        PositionAssignment::factory()->create(['tenant_id' => $tenant->id, 'position_id' => $position->id, 'employee_id' => Employee::factory()->create($where + ['name' => $name])->id]);
    }

    SeatReport::factory()->create($where + ['created_at' => now()->subMonth()]);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    return $plant;
}

// Board V2-Ruta-Silla: the route of a center, from its stages to what an inspector would ask for and to Humi.
it('shows the Ley Silla route of a center, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = standingPlant();

    visitPage('seating/route', "/admin/acme/seating/centers/{$plant->id}/route", $dark, $mobile)
        ->assertSee('Tu ruta para cumplir con la Ley Silla')
        ->assertSee('En Planta Querétaro 2 personas trabajan de pie en 1 puesto.')
        ->assertSee('Aquí vas')
        ->assertSee('Antes de seguir')
        ->assertSee('Podrías mostrar')
        ->assertSee('Las personas también participan')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('goes from the route of a center to the inspection and opens Humi from its card', function (): void {
    $plant = standingPlant();

    visit("/admin/acme/seating/centers/{$plant->id}/route")
        ->click('Pregúntale a Humi')
        ->assertSee('Hola, soy Humi')
        ->assertNoJavaScriptErrors();

    visit("/admin/acme/seating/centers/{$plant->id}/route")
        ->click('Ver lo que te pediría')
        ->assertSee('Si hoy llegara una inspección a Planta Querétaro')
        ->assertNoJavaScriptErrors();
});

// Board V3-Inspeccion for Ley Silla.
it('shows what you could show an inspector and what is missing, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = standingPlant();

    visitPage('seating/inspection', "/admin/acme/seating/centers/{$plant->id}/inspection", $dark, $mobile)
        ->assertSee('Si hoy llegara una inspección a Planta Querétaro')
        ->assertSeeIn('[aria-labelledby="missing"] li:first-child p.font-medium', 'Asientos en cada puesto')
        ->assertSeeIn('[aria-labelledby="ready"] li:first-child p.font-medium', 'Los puestos de pie, con su Figura 1')
        ->assertSee('Descargar expediente')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('opens the Ley Silla dossier with only what the center can show', function (): void {
    $plant = standingPlant();

    visit("/admin/acme/seating/centers/{$plant->id}/inspection")
        ->click('Solo lo que tienes')
        ->click('Abrir expediente')
        ->assertScript(pathBecomes("/admin/acme/seating/centers/{$plant->id}/dossier"), true)
        ->assertPathIs("/admin/acme/seating/centers/{$plant->id}/dossier")
        ->assertQueryStringHas('ready', '1')
        ->assertNoJavaScriptErrors();
});
