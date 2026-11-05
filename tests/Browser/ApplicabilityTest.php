<?php

declare(strict_types=1);

use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

/** Acme with Planta Querétaro, and Rita of RH signed in. */
function applicabilityCompany(): WorkCenter
{
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro', 'main_activity' => 'Manufactura']);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    return $plant;
}

// Rediseño v2 (board V2-Aplica): two questions, then the route or the «no aplica» kept with its date.
it('asks whether NOM-037 applies, as the redesign draws it, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = applicabilityCompany();

    visitPage('telework/applies', "/admin/acme/telework/centers/{$plant->id}/applies", $dark, $mobile)
        ->assertSee('Primero, veamos si la NOM-037 te aplica en Planta Querétaro')
        ->assertSee('más del 40 % de su tiempo')
        ->assertSee('Hacerlo después')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('declares that it applies after two answers and shows the route ahead', function (): void {
    $plant = applicabilityCompany();

    visit("/admin/acme/telework/centers/{$plant->id}/applies")
        ->click('Empezar')
        ->assertSee('Pregunta 1 de 2')
        ->radio('outside', 'yes')
        ->click('Siguiente')
        ->assertSee('¿Esa persona trabaja fuera más del 40 % de su tiempo?')
        ->radio('share', 'unsure')
        ->assertSee('Una forma rápida de saberlo')
        ->radio('share', 'over')
        ->click('Siguiente')
        ->assertSee('Sí te aplica. Esta es tu ruta.')
        ->assertSee('Registra a quién teletrabaja y dónde')
        ->assertNoJavaScriptErrors();

    expect(TeleworkProfile::query()->withoutGlobalScopes()->sole()->has_teleworkers)->toBeTrue();
});

it('declares that it does not apply when everyone works at the center', function (): void {
    $plant = applicabilityCompany();

    visit("/admin/acme/telework/centers/{$plant->id}/applies")
        ->click('Empezar')
        ->radio('outside', 'no')
        ->click('Siguiente')
        ->assertSee('No te aplica en este centro')
        ->assertNoJavaScriptErrors();

    expect(TeleworkProfile::query()->withoutGlobalScopes()->sole()->has_teleworkers)->toBeFalse();
});

// Rediseño v2 (board V2-Aplica-Otras): what NOM-035 and Ley Silla ask of one center.
it('tells what NOM-035 asks of the center, as the redesign draws it, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = applicabilityCompany();
    staffOf($plant->tenant, $plant, 60);

    visitPage('tenancy/applies', "/admin/acme/work-centers/{$plant->id}/applies", $dark, $mobile)
        ->assertSee('¿Qué te pide cada norma en este centro?')
        ->assertSee('Guías I y III')
        ->assertSee('Con 60 personas, la muestra mínima la ves al crear la evaluación.')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('keeps the Ley Silla declaration of the center', function (): void {
    $plant = applicabilityCompany();

    visit("/admin/acme/work-centers/{$plant->id}/applies?norm=seating")
        ->assertSee('Primero, ¿alguien trabaja de pie?')
        ->radio('standing', 'yes')
        ->assertSee('Falta saber a qué se dedica')
        ->radio('sector', 'industrial')
        ->assertSee('Solo donde el trabajo permite sentarse')
        ->click('Guardar declaración')
        ->assertSee('Ver mi ruta')
        ->assertNoJavaScriptErrors();

    expect(SeatingProfile::query()->withoutGlobalScopes()->sole()->sector)->toBe(Sector::Industrial);
});
