<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Telework\AcknowledgementFactory;

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
        ->assertSee('Autopartes Demo · Planta Querétaro')
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

/** A draft of the policy with every question answered, on its review. */
function answeredPolicy(): DocumentDraft
{
    return DocumentDraft::factory()->create(['tenant_id' => Tenant::query()->sole()->id, 'step' => 8, 'answers' => [
        'schedule' => 'nine', 'disconnect' => 'end', 'frequency' => 'weekly', 'channels' => ['email', 'chat'], 'lactation' => 'breaks',
        'prevention' => 'yearly', 'balance' => 'boss', 'health' => ['exams'], 'notice' => 'twenty',
    ]]);
}

it('publishes the policy from its review for the people of the centers, and reads it as a page', function (): void {
    $plant = writingCompany();
    TeleworkProfile::factory()->teleworks()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id]);
    Employee::factory()->count(2)->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id]);
    answeredPolicy();

    visit("/admin/acme/telework/documents/policy/write?center={$plant->id}")
        ->assertSee('Tu política cubre los 12 temas')
        ->assertSee('Al publicarla, le llega a las 2 personas de tu centro para que confirmen que la leyeron.')
        ->assertSee('Mismos derechos')
        ->click('Publicar para 2 personas')
        ->assertSee('¿Publicar la política para 2 personas en 1 centro?')
        ->press('Publicar ahora')
        ->assertSee('Política de Teletrabajo publicada.')
        ->assertSee('Tu ruta para cumplir con el teletrabajo')
        ->assertNoJavaScriptErrors();

    $document = TeleworkDocument::query()->withoutGlobalScopes()->sole();

    visitPage('telework/written-document', "/admin/acme/telework/documents/{$document->id}/file")
        ->assertSee('Autopartes Demo · Planta Querétaro')
        ->assertSee('Versión 1 · publicada el')
        ->assertSee('Horario y desconexión');
});

it('shows a written document as a page to read and print, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = writingCompany();
    $document = TeleworkDocument::factory()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id, 'path' => null, 'template_version' => 'humetria-2026-11-05', 'body' => [
        ['key' => 'e', 'heading' => 'Horario y desconexión', 'text' => "La jornada en teletrabajo es de 9:00 a 18:00.\n\nAl terminar su horario, nadie está obligado a responder."],
    ]]);

    visitPage('telework/written-document', "/admin/acme/telework/documents/{$document->id}/file", $dark, $mobile)
        ->assertSee('Política de Teletrabajo')
        ->assertSee('Al terminar su horario, nadie está obligado a responder.')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('lets the person read a written document from their portal before confirming it', function (bool $dark, bool $mobile): void {
    $plant = writingCompany();
    $employee = Employee::factory()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id]);
    $document = TeleworkDocument::factory()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id, 'path' => null, 'template_version' => 'humetria-2026-11-05', 'body' => [
        ['key' => 'e', 'heading' => 'Horario y desconexión', 'text' => 'La jornada en teletrabajo es de 9:00 a 18:00.'],
    ]]);
    workIn($plant->tenant);
    AcknowledgementFactory::new()->create(['tenant_id' => $plant->tenant_id, 'document_id' => $document->id, 'employee_id' => $employee->id]);
    $token = resolve(IssuePortalLink::class)->handle($employee->id);

    visitPage('telework/portal-document', "/portal/{$token}/documents/{$document->id}/file", $dark, $mobile)
        ->assertSee('Autopartes Demo')
        ->assertSee('Volver a su portal')
        ->assertSee('La jornada en teletrabajo es de 9:00 a 18:00.');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('shows the review with every section to read and rewrite, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    writingCompany();
    answeredPolicy();

    visitPage('telework/write', '/admin/acme/telework/documents/policy/write', $dark, $mobile)
        ->assertSee('Tu política cubre los 12 temas')
        ->assertSee('Lista para revisar')
        ->assertSee('Cambios de domicilio')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('rewrites a section on the review and puts back the text of the answers', function (): void {
    writingCompany();
    answeredPolicy();

    visit('/admin/acme/telework/documents/policy/write')
        ->click('Editar Mismos derechos')
        ->clear('#section-f')
        ->type('#section-f', 'Quien teletrabaja tiene los mismos derechos que quien va al centro.')
        ->click('Guardar')
        ->assertSee('Quien teletrabaja tiene los mismos derechos que quien va al centro.')
        ->click('Volver al texto de tus respuestas')
        ->assertSee('gozarán del mismo trato que el personal en la modalidad presencial')
        ->assertDontSee('Quien teletrabaja tiene los mismos derechos que quien va al centro.')
        ->assertNoJavaScriptErrors();

    expect(DocumentDraft::query()->withoutGlobalScopes()->sole()->edits)->toBe([]);
});

// Board V3-Doc-Riesgos: a supporting document with the same questions and preview as the policy.
it('writes the risks of working from home with questions, as the redesign draws it, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    writingCompany();
    DocumentDraft::factory()->create(['tenant_id' => Tenant::query()->sole()->id, 'kind' => 'risk_information', 'answers' => ['work' => 'computer', 'risks' => ['posture', 'electric', 'isolation']], 'step' => 1]);

    visitPage('telework/write', '/admin/acme/telework/documents/risk_information/write', $dark, $mobile)
        ->assertSee('NOM-037 · Documentos de apoyo · Riesgos de trabajar desde casa')
        ->assertSee('Pregunta 2 de 4')
        ->assertSee('¿Qué riesgos quieres explicarle a tu equipo?')
        ->assertSee('Caídas o tropiezos con cables')
        ->assertSee('Riesgos de trabajar desde casa y cómo prevenirlos')
        ->assertSee('Para quienes trabajan desde casa con computadora y teléfono más del 40 % de su tiempo.')
        ->assertSee('Instalación eléctrica. No conecte varios aparatos a una sola extensión; avise si un contacto está dañado.')
        ->assertDontSee('Los 12 temas')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

// Board V2-N037-Docs: the six supporting documents, each written with questions or uploaded.
it('lists the supporting documents, as the redesign draws them, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $plant = writingCompany();
    TeleworkDocument::factory()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id, 'kind' => 'committee_participation']);

    visitPage('telework/supporting-documents', '/admin/acme/telework/documents/supporting', $dark, $mobile)
        ->assertSee('Documentos de apoyo')
        ->assertSee('Además de la política, la norma pide estos 6.')
        ->assertSee('1 de 6 listo')
        ->assertSee('≈ 25 min los demás')
        ->assertSee('Riesgos de trabajar desde casa')
        ->assertSee('≈ 7 min · numeral 5.13')
        ->assertSee('Crear con preguntas')
        ->assertSee('Ya lo tengo')
        ->assertSee('Nueva versión')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('goes from the list to write a document with questions, and back', function (): void {
    writingCompany();

    visit('/admin/acme/telework/documents/supporting')
        ->click('Crear con preguntas · Mantenimiento del equipo')
        ->assertSee('NOM-037 · Documentos de apoyo · Mantenimiento del equipo')
        ->assertSee('Pregunta 1 de 4')
        ->click('Guardar y volver')
        ->assertSee('Además de la política, la norma pide estos 6.')
        ->click('Ya lo tengo · Regreso a la oficina')
        ->assertSee('Documentos de NOM-037')
        ->assertRadioSelected('kind', 'reversibility')
        ->assertNoJavaScriptErrors();
});

// Rediseño v2 (boards V2-Centros-N037 and V2-Ruta-N037): what is done once for the company leads to its questions.
it('goes from the company and from the route of a center to write the policy and the supporting documents', function (): void {
    $plant = writingCompany();
    TeleworkProfile::factory()->teleworks()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id]);

    visit('/admin/acme/telework')
        ->click('Continuar · Política de Teletrabajo')
        ->assertSee('Pregunta 1 de 8')
        ->click('Guardar y volver')
        ->click('Ver documentos · 6 documentos de apoyo')
        ->assertSee('Además de la política, la norma pide estos 6.')
        ->assertNoJavaScriptErrors();

    visit("/admin/acme/telework/centers/{$plant->id}/route")
        ->assertSee('Termina tu Política de Teletrabajo')
        ->click('a[href*="/documents/policy/write"]')
        ->assertSee('Guardar y volver a la ruta')
        ->click('Guardar y volver a la ruta')
        ->assertSee('Ver los 6')
        ->click('a[href$="/telework/documents/supporting"]')
        ->assertSee('Además de la política, la norma pide estos 6.')
        ->assertNoJavaScriptErrors();
});
