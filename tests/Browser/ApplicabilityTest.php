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

// Decisiones A2 y D6: where a standard does not apply, a certificate to print on a letter sheet.
it('prints the certificate that NOM-037 does not apply, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = applicabilityCompany();
    TeleworkProfile::factory()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id, 'declared_on' => '2026-02-03']);

    visitPage('telework/certificate', "/admin/acme/telework/centers/{$plant->id}/certificate", $dark, $mobile)
        ->assertSee('NOM-037-STPS-2023 · Teletrabajo')
        ->assertSee('Constancia de que la norma no aplica')
        ->assertSee('3 de febrero de 2026')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('prints the certificate that Ley Silla does not apply, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = applicabilityCompany();
    SeatingProfile::factory()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id, 'sector' => null, 'no_standing_positions' => true, 'declared_on' => '2026-02-03']);

    visitPage('seating/certificate', "/admin/acme/seating/centers/{$plant->id}/certificate", $dark, $mobile)
        ->assertSee('Ley Silla · trabajo de pie')
        ->assertSee('Planta Querétaro')
        ->assertSee('3 de febrero de 2026')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('reaches the certificate from the answer, and from the panel', function (): void {
    $plant = applicabilityCompany();

    visit("/admin/acme/telework/centers/{$plant->id}/applies")
        ->click('Empezar')
        ->radio('outside', 'no')
        ->click('Siguiente')
        ->click('Ver constancia')
        ->assertSee('NOM-037-STPS-2023 · Teletrabajo')
        ->assertNoJavaScriptErrors();

    visit('/admin/acme')
        ->assertSeeIn('[data-norm="nom037"]', 'No aplica')
        ->click('[data-norm="nom037"] li a[href$="/certificate"]')
        ->assertSee('Constancia de que la norma no aplica')
        ->assertNoJavaScriptErrors();
});

// Rediseño v2 (board V3-Onboarding): the first time of a company, two questions per center and its routes.
it('sets the company up as the redesign draws it, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = applicabilityCompany();
    staffOf($plant->tenant, $plant, 30);
    WorkCenter::factory()->for($plant->tenant)->create(['name' => 'Oficinas Monterrey', 'main_activity' => 'Administración y ventas']);

    visitPage('tenancy/onboarding', '/admin/acme/work-centers/onboarding', $dark, $mobile)
        ->assertSee('Dos preguntas por centro y armamos todo')
        ->assertSee('Manufactura · 30 personas registradas')
        ->assertSeeIn('[aria-labelledby="summary"]', 'NOM-035 · Guías I y II')
        ->assertSee('Contesta los 2 centros')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('creates the routes from the panel, two questions per center', function (): void {
    $plant = applicabilityCompany();

    visit('/admin/acme')
        ->assertSee('Dos preguntas por centro y armamos todo')
        ->click('Empezar')
        ->assertSee('Paso 2 de 2')
        ->click("label:has(input[name=\"telework-{$plant->id}\"][value=\"no\"])")
        ->click("label:has(input[name=\"standing-{$plant->id}\"][value=\"yes\"])")
        ->assertSee('Falta el sector')
        ->click("label:has(input[name=\"sector-{$plant->id}\"][value=\"commerce\"])")
        ->assertSee('Crear mis 2 rutas')
        ->click('Crear mis 2 rutas')
        ->assertSee('Listo: tus rutas están en el panel')
        ->assertSeeIn('[data-norm="seating"]', 'Etapa 1 de 6 · identifica los puestos de pie')
        ->assertSeeIn('[data-norm="nom037"]', 'No aplica')
        ->assertNoJavaScriptErrors();
});
