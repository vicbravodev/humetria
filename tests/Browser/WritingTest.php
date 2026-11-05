<?php

declare(strict_types=1);

use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

/** Autopartes Demo with Planta Querétaro, and Rita of RH signed in. */
function writingCompany(): WorkCenter
{
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    return $plant;
}

// Rediseño v2 (board V2-Politica, decisión A3): one question at a time, the policy written beside it.
it('writes the policy with questions, as the redesign draws it, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = writingCompany();

    visitPage('telework/write', "/admin/acme/telework/documents/policy/write?center={$plant->id}", $dark, $mobile)
        ->assertSee('Guardar y volver a la ruta')
        ->assertSee('NOM-037 · Etapa 1 · Política de Teletrabajo')
        ->assertSee('Se guarda en cada respuesta')
        ->assertSee('Pregunta 1 de 8')
        ->assertSee('Faltan unos 12 min')
        ->assertSee('¿En qué horario trabajan y desde cuándo nadie debe recibir mensajes de trabajo?')
        ->assertSee('Autopartes Demo · todos los centros')
        ->assertSee('La jornada en teletrabajo es de 9:00 a 18:00, de lunes a viernes.')
        ->assertSee('Texto base de Humetría, listo para editar.')
        ->assertSee('Puedes cambiarlo después')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('keeps each answer as it is given and picks up where it was left', function (): void {
    writingCompany();

    visit('/admin/acme/telework/documents/policy/write')
        ->radio('schedule', 'eight')
        ->assertSee('La jornada en teletrabajo es de 8:00 a 17:00')
        ->click('Siguiente')
        ->assertSee('Pregunta 2 de 8')
        ->assertSee('¿Cada cuánto se reúne el equipo para que nadie se quede aislado?')
        ->radio('frequency', 'weekly')
        ->assertSee('el equipo se reúne una vez por semana')
        ->assertNoJavaScriptErrors();

    expect(DocumentDraft::query()->withoutGlobalScopes()->sole())
        ->answers->toMatchArray(['schedule' => 'eight', 'frequency' => 'weekly'])
        ->step->toBe(1);

    visit('/admin/acme/telework/documents/policy/write')
        ->assertSee('Pregunta 2 de 8')
        ->assertRadioSelected('frequency', 'weekly')
        ->click('Anterior')
        ->assertRadioSelected('schedule', 'eight')
        ->assertNoJavaScriptErrors();
});

it('says what is left to answer at the end, and leads back to it', function (): void {
    writingCompany();
    DocumentDraft::factory()->create(['tenant_id' => Tenant::query()->sole()->id, 'answers' => ['schedule' => 'nine', 'disconnect' => 'end', 'channels' => ['email']], 'step' => 8]);

    visit('/admin/acme/telework/documents/policy/write')
        ->assertSee('Te faltan 6 preguntas')
        ->click('Contacto con el equipo')
        ->assertSee('Pregunta 2 de 8')
        ->assertNoJavaScriptErrors();
});
