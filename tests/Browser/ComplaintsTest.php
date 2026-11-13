<?php

declare(strict_types=1);

use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** The panel's address, and the company the signed-in owner belongs to. */
function complaintsPanel(): array
{
    $url = signInToPanel();

    return [$url, Tenant::query()->where('slug', 'acme')->firstOrFail()];
}

it('shows the channel before it is activated, with its procedure and deadlines', function (bool $dark, bool $mobile): void {
    [$url] = complaintsPanel();

    visitPage('complaints/channel', "{$url}/complaints/channel", $dark, $mobile)
        ->assertSee('Canal de quejas y denuncias')
        ->assertSee('Sin activar')
        ->assertSee('Activa el canal')
        ->assertSee('Procedimiento')
        ->assertSee('Activar el canal');
})->with($looks);

it('shows an active channel with who receives each complaint, what is missing, its link and what came', function (bool $dark, bool $mobile): void {
    [$url, $tenant] = complaintsPanel();
    $owner = App\Modules\Tenancy\Models\TenantMembership::query()->where('tenant_id', $tenant->id)->firstOrFail()->user_id;
    App\Models\User::query()->whereKey($owner)->update(['name' => 'Olga Owner']);
    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => $owner]);
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create(['published_on' => '2026-11-04']);
    Complaint::factory()->count(2)->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

    visitPage('complaints/channel', "{$url}/complaints/channel", $dark, $mobile)
        ->assertSee('Recibe quejas desde el 4 nov')
        ->assertSee('Falta 1 cosa para que el canal sea seguro')
        ->assertSee('Nadie atiende las quejas contra la dirección o el dueño')
        ->assertSee('A quién llega cada queja')
        ->assertSee('Olga Owner')
        ->assertSee('Cubierto')
        ->assertSee('Nadie todavía')
        ->assertSee(preg_replace('#^https?://#', '', $channel->publicUrl()))
        ->assertSee('Copiar enlace')
        ->assertSee('Cartel para imprimir')
        ->assertSee('Lo que ha llegado')
        ->assertSee('menos de 5')
        ->assertSee('Si llega una inspección');
})->with($looks);

it('designates from the channel who handles the complaints against the management, leaving out the owner', function (bool $dark, bool $mobile): void {
    [$url, $tenant] = complaintsPanel();
    $owner = App\Modules\Tenancy\Models\TenantMembership::query()->where('tenant_id', $tenant->id)->firstOrFail()->user_id;
    App\Models\User::query()->whereKey($owner)->update(['name' => 'Olga Owner']);
    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => $owner]);
    $hr = memberOf($tenant, App\Modules\Tenancy\Enums\TenantRole::Hr);
    $hr->forceFill(['name' => 'Rita Recursos'])->save();
    ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();

    visitPage('complaints/channel', "{$url}/complaints/channel", $dark, $mobile, fn ($page) => $page->click('Designar a alguien'))
        ->assertSee('¿Quién atiende las quejas contra la dirección o el dueño?')
        ->assertSee('Olga Owner no puede: es contra su rol')
        ->assertSee('Usuarios')
        ->click('#handler-person')
        ->assertSee('Rita Recursos · Ayuda con las rutas')
        ->assertDontSee('Olga Owner · Administra todo')
        ->keys('[role="combobox"][aria-controls="handler-person-options"]', 'Enter')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Persona designada.')
        ->assertDontSee('Nadie todavía')
        ->assertNoJavaScriptErrors();

    expect(ComplaintHandler::query()->withoutGlobalScopes()->where('user_id', $hr->id)->exists())->toBeTrue();
})->with(['light desktop' => [false, false], 'dark mobile' => [true, true]]);

it('edits how the channel is handled from its dialog', function (): void {
    [$url, $tenant] = complaintsPanel();
    ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();

    visit("{$url}/complaints/channel")
        ->click('Editar')
        ->fill('acknowledge_within_days', '5')
        ->press('Guardar ajustes')
        ->assertSee('Ajustes guardados.')
        ->assertSee('5 días')
        ->assertNoJavaScriptErrors();
});

it('activates the channel from the page', function (): void {
    [$url] = complaintsPanel();

    visit("{$url}/complaints/channel")
        ->press('Activar el canal')
        ->assertSee('El canal quedó activo.')
        ->assertSee('Cómo llegan las personas')
        ->assertNoJavaScriptErrors();

    expect(ComplaintChannel::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('pauses and resumes the channel from the page', function (): void {
    [$url, $tenant] = complaintsPanel();
    ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();

    visit("{$url}/complaints/channel")
        ->click('Pausar')
        ->press('Pausar el canal')
        ->assertSee('El canal quedó en pausa.')
        ->click('Reanudar')
        ->press('Reanudar el canal')
        ->assertSee('El canal volvió a recibir quejas.')
        ->assertNoJavaScriptErrors();
});

it('shows who handles the complaints and for which scope', function (bool $dark, bool $mobile): void {
    [$url, $tenant] = complaintsPanel();
    $owner = App\Modules\Tenancy\Models\TenantMembership::query()->where('tenant_id', $tenant->id)->firstOrFail();
    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => $owner->user_id]);

    visitPage('complaints/handlers', "{$url}/complaints/handlers", $dark, $mobile)
        ->assertSee('Quiénes atienden')
        ->assertSee('Toda la empresa')
        ->assertSee('Quitar')
        ->assertSee('Designar a alguien');
})->with($looks);

it('shows the empty list of handlers with its next step', function (): void {
    [$url] = complaintsPanel();

    visitPage('complaints/handlers', "{$url}/complaints/handlers")
        ->assertSee('Nadie atiende quejas todavía');
});

it('designates a handler from the page and shows them in the list', function (): void {
    [$url, $tenant] = complaintsPanel();
    $hr = memberOf($tenant, App\Modules\Tenancy\Enums\TenantRole::Hr);
    $hr->forceFill(['name' => 'Ana Recursos'])->save();

    visit("{$url}/complaints/handlers")
        ->click('Designar a alguien')
        ->click('#handler-person')
        ->type('[role="combobox"][aria-controls="handler-person-options"]', 'recursos')
        ->assertSee('Ana Recursos · Ayuda con las rutas')
        ->keys('[role="combobox"][aria-controls="handler-person-options"]', 'Enter')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Persona designada.')
        ->assertSee('Ana Recursos')
        ->assertNoJavaScriptErrors();
});

it('opens the public form of the channel with what to read first, and leaves no cookie', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme Industrial']);
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();

    visitPage('complaints-public/form', "/c/{$channel->access_code}", $dark, $mobile)
        ->assertSee('Acme Industrial')
        ->assertSee('¿Algo no está bien en su trabajo?')
        ->assertSee('Le confirmamos que la recibimos en 3 días')
        ->assertSee('Qué puede identificarle aunque no dé su nombre')
        ->assertSee('Cómo se atiende su queja')
        ->assertSee('Ya presenté una: ver cómo va')
        ->assertSee('Inspección Federal del Trabajo')
        ->assertScript('() => document.cookie', '');
})->with($looks);

it('says the channel is paused and offers the other way, or that the link is gone', function (string $case): void {
    $tenant = Tenant::factory()->create();
    $code = $case === 'paused' ? ComplaintChannel::factory()->forTenant($tenant->id)->paused()->create()->access_code : 'ZZZZZZZZZZZZZZZZ';

    visitPage('complaints-public/unavailable', "/c/{$code}")
        ->assertSee($case === 'paused' ? 'El canal no está disponible por ahora' : 'Este enlace ya no está disponible')
        ->assertSee('Otra vía')
        ->assertSee('Inspección Federal del Trabajo');
})->with(['paused', 'gone']);

/** A company with an open channel and an owner who handles it, so every option of the form is offered but the management's. */
function openChannelWithHandler(): ComplaintChannel
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme Industrial']);
    $owner = ownerOf($tenant);
    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => $owner->id]);

    return ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
}

// Rediseño v2 (board V4-Quejas-Form): four steps — what, who is involved, what happened, how to follow it up — the last
// one sends, with no step to review; the key is still the one the system generates (decisión A9).
it('walks the four steps of the form and hands back a reference and a key once', function (bool $mobile): void {
    // A robot is quicker than any person: the three seconds of the ticket have their own tests.
    config(['complaints.ticket.min_seconds' => 0]);
    $channel = openChannelWithHandler();
    $page = $mobile ? visit("/c/{$channel->access_code}")->on()->mobile() : visit("/c/{$channel->access_code}");

    $page->click('Presentar una queja')
        ->assertSee('Paso 1 de 4')
        ->assertSee('¿Qué quiere reportar?')
        ->press('Continuar')
        ->assertSee('Elija qué quiere informar.')
        ->click('Denuncia de violencia laboral')
        ->press('Continuar')
        ->assertSee('Paso 2 de 4')
        ->assertSee('¿Quién está involucrado?')
        ->assertSee('Por ahora la empresa no tiene a nadie independiente')
        ->press('Continuar')
        ->assertSee('Elija de quién se trata')
        ->click('Mi jefa o jefe inmediato')
        ->press('Continuar')
        ->assertSee('Cuéntenos qué pasó')
        ->press('Continuar')
        ->assertSee('Cuéntenos un poco más')
        ->fill('#complaint-description', 'Mi jefe me grita todos los días frente a mis compañeros y me amenaza.')
        ->press('Continuar')
        ->assertSee('Paso 4 de 4')
        ->assertSee('¿Cómo quiere darle seguimiento?')
        ->assertNoAccessibilityIssues(1)
        ->press('Enviar queja')
        ->assertSee('Recibimos su queja')
        ->assertSee('Le confirmaremos que alguien la leyó a más tardar el')
        ->assertSee('Su referencia')
        ->assertSee('Su clave')
        ->assertSee('la clave no se puede recuperar')
        ->assertScript('() => document.cookie', '')
        ->assertNoJavaScriptErrors();

    expect(Complaint::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Complaint::query()->withoutGlobalScopes()->first()?->description)->toContain('me grita');
})->with([[false], [true]]);

it('goes back a step without losing what was written', function (): void {
    $channel = openChannelWithHandler();

    visit("/c/{$channel->access_code}")->on()->mobile()
        ->click('Presentar una queja')
        ->click('Denuncia de violencia laboral')
        ->press('Continuar')
        ->click('Atrás')
        ->assertSee('¿Qué quiere reportar?')
        ->assertChecked('[name="type"][value="workplace_violence"]')
        ->click('Atrás')
        ->assertSee('Presentar una queja')
        ->assertNoJavaScriptErrors();
});

it('shows the form steps without accessibility issues and with room for a finger', function (): void {
    $channel = openChannelWithHandler();

    visit("/c/{$channel->access_code}")->on()->mobile()
        ->click('Presentar una queja')
        ->assertNoAccessibilityIssues(1)
        ->assertScript(smallTouchTargets(), '')
        ->click('Queja por una práctica opuesta a un entorno favorable')
        ->press('Continuar')
        ->assertNoAccessibilityIssues(1)
        ->assertScript(smallTouchTargets(), '');
});

it('sends a confidential complaint with the name that only its handlers read', function (): void {
    config(['complaints.ticket.min_seconds' => 0]);
    $channel = openChannelWithHandler();

    visit("/c/{$channel->access_code}")
        ->click('Presentar una queja')
        ->click('Queja por una práctica opuesta a un entorno favorable')
        ->press('Continuar')
        ->click('Una compañera o compañero')
        ->press('Continuar')
        ->fill('#complaint-description', 'Las cargas de trabajo del turno de noche son excesivas desde hace meses.')
        ->press('Continuar')
        ->click('[name="identity_mode"][value="confidential"]')
        ->press('Enviar queja')
        ->assertSee('Escriba su nombre.')
        ->fill('#reporter-name', 'Rosa Aguilar')
        ->fill('#notify-email', 'rosa.privada@ejemplo.mx')
        ->press('Enviar queja')
        ->assertSee('Recibimos su queja')
        ->assertDontSee('rosa.privada@ejemplo.mx')
        ->assertNoJavaScriptErrors();

    $complaint = Complaint::query()->withoutGlobalScopes()->sole();
    expect($complaint->reporter_name)->toBe('Rosa Aguilar')->and($complaint->reporter_email)->toBe('rosa.privada@ejemplo.mx');
});

it('asks for the day and the name of the notice of a traumatic event, and sends it', function (): void {
    config(['complaints.ticket.min_seconds' => 0]);
    $channel = openChannelWithHandler();

    visit("/c/{$channel->access_code}")
        ->click('Presentar una queja')
        ->click('Aviso de un acontecimiento traumático severo')
        ->press('Continuar')
        ->assertSee('¿Dónde ocurrió y quiénes estuvieron?')
        ->assertDontSee('¿Quién está involucrado?')
        ->press('Continuar')
        ->fill('#complaint-description', 'Presencié un accidente en el que un compañero perdió la mano en la prensa.')
        ->press('Continuar')
        ->assertSee('Indique la fecha en que ocurrió.')
        ->click('#complaint-date')
        ->click('[data-day] button[tabindex="0"]')
        ->press('Continuar')
        ->assertSee('Este aviso necesita su nombre')
        ->press('Enviar queja')
        ->assertSee('Escriba su nombre.')
        ->fill('#reporter-name', 'Rosa Aguilar')
        ->press('Enviar queja')
        ->assertSee('Recibimos su queja')
        ->assertNoJavaScriptErrors();

    $complaint = Complaint::query()->withoutGlobalScopes()->sole();
    expect($complaint->type->value)->toBe('traumatic_event_notice')->and($complaint->reporter_name)->toBe('Rosa Aguilar')->and($complaint->occurred_on)->not->toBeNull();
});

it('warns of the complaints nobody holds, with the button that assigns them again', function (bool $dark, bool $mobile): void {
    [$url, $tenant] = complaintsPanel();
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
    Complaint::factory()->count(2)->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

    visitPage('complaints/channel', "{$url}/complaints/channel", $dark, $mobile)
        ->assertSee('2 casos sin encargada.')
        ->assertSee('Asignar de nuevo');
})->with($looks);

it('assigns again the complaints nobody holds from the page', function (): void {
    [$url, $tenant] = complaintsPanel();
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => App\Modules\Tenancy\Models\TenantMembership::query()->where('tenant_id', $tenant->id)->firstOrFail()->user_id]);
    $complaint = Complaint::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

    visit("{$url}/complaints/channel")
        ->press('Asignar de nuevo')
        ->assertSee('Los casos sin encargada se asignaron.')
        ->assertDontSee('sin encargada.')
        ->assertNoJavaScriptErrors();

    expect(App\Modules\Complaints\Models\ComplaintAssignment::query()->withoutGlobalScopes()->where('complaint_id', $complaint->id)->count())->toBe(1);
});

/** The signed-in owner of the panel's company, handling a confidential complaint and an anonymous one. */
function handlerWithCases(): array
{
    [$url, $tenant] = complaintsPanel();
    $owner = App\Modules\Tenancy\Models\TenantMembership::query()->where('tenant_id', $tenant->id)->firstOrFail()->user_id;
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
    $plant = App\Modules\Tenancy\Models\WorkCenter::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Planta Querétaro']);
    $confidential = Complaint::factory()->create([
        'tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'reference' => 'ABCD2345', 'work_center_id' => $plant->id,
        'description' => 'El jefe de turno me humilla frente a todos cada semana.', 'identity_mode' => 'confidential', 'reporter_name' => 'Rosa Aguilar', 'reporter_contact' => 'Cel. 442 000 0000',
        'received_on' => '2026-10-01',
    ]);
    $anonymous = Complaint::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'reference' => 'WXYZ6789', 'description' => 'Las cargas del turno de noche son excesivas.', 'received_on' => '2026-11-02']);

    foreach ([$confidential, $anonymous] as $complaint) {
        App\Modules\Complaints\Models\ComplaintAssignment::factory()->create(['tenant_id' => $tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $owner]);
    }

    return [$url, $confidential, $anonymous];
}

it('lists the cases assigned to the handler, without what they say', function (bool $dark, bool $mobile): void {
    [$url] = handlerWithCases();

    visitPage('complaints/index', "{$url}/complaints", $dark, $mobile)
        ->assertSee('Solo ves los casos que te asignaron.')
        ->assertSee('Antes de seguir')
        ->assertSee('Esperan acuse de recibo')
        ->assertSee('ABCD2345')
        ->assertSee('WXYZ6789')
        ->assertSee('Acuse venció hace')
        ->assertSee('Planta Querétaro')
        ->assertDontSee('humilla')
        ->assertDontSee('Rosa Aguilar');
})->with($looks);

it('shows the empty inbox with what makes it fill', function (): void {
    [$url] = complaintsPanel();

    visitPage('complaints/index', "{$url}/complaints")
        ->assertSee('No tienes casos abiertos')
        ->assertSee('Revisar el canal');
});

it('narrows the inbox by a reference from the page', function (): void {
    [$url] = handlerWithCases();

    visit("{$url}/complaints")
        ->fill('input[type="search"]', 'WXYZ')
        ->assertSee('WXYZ6789')
        ->assertDontSee('ABCD2345')
        ->assertNoJavaScriptErrors();
});

it('opens a confidential case with the words of the person and their name', function (bool $dark, bool $mobile): void {
    [$url, $case] = handlerWithCases();

    visitPage('complaints/show', "{$url}/complaints/cases/{$case->id}", $dark, $mobile)
        ->assertSee('ABCD2345')
        ->assertSee('Espera acuse')
        ->assertSee('humilla frente a todos')
        ->assertSee('Rosa Aguilar')
        ->assertSee('Cel. 442 000 0000')
        ->assertSee('Tu consulta quedó registrada')
        ->assertSee('Plazos que te fijaste');
})->with($looks);

it('opens an anonymous case saying that there is nobody to ask for', function (): void {
    [$url, , $case] = handlerWithCases();

    visitPage('complaints/show', "{$url}/complaints/cases/{$case->id}")
        ->assertSee('Anónima: no sabemos quién es')
        ->assertSee('anónima · recibida el 2 de noviembre de 2026')
        ->assertSee('Las cargas del turno de noche');
});

it('opens a case from the inbox', function (): void {
    [$url] = handlerWithCases();

    visit("{$url}/complaints")
        ->click('[aria-label="Acusar recibo: ABCD2345"]')
        ->assertSee('Lo que cuenta la persona')
        ->assertNoJavaScriptErrors();
});

it('works a case from the page: acknowledges it, writes to the person, takes a note and closes it', function (): void {
    [$url, $case] = handlerWithCases();

    visit("{$url}/complaints/cases/{$case->id}")
        ->assertValue('#case-acknowledgement', 'Recibimos su denuncia. La atiende una persona designada que no está involucrada. Le responderemos aquí a más tardar el 31 de octubre. Puede escribirnos si quiere agregar algo.')
        ->press('Enviar acuse de recibo')
        ->assertSee('Acuse enviado.')
        ->assertSee('Le responderemos aquí a más tardar el 31 de octubre')
        ->press('Empezar la revisión')
        ->assertSee('El caso pasó a revisión.')
        ->fill('#case-reply', 'Gracias por avisarnos, revisaremos lo que cuenta.')
        ->press('Enviar a la persona')
        ->assertSee('Mensaje enviado a la persona.')
        ->click('Agregar nota')
        ->fill('#case-note', 'Hablé con el jefe de turno.')
        ->press('Guardar nota')
        ->assertSee('Nota guardada.')
        ->assertSee('Hablé con el jefe de turno.')
        ->click('Cerrar con una respuesta')
        ->click('Se tomaron medidas')
        ->fill('#case-answer', 'Hablamos con las personas involucradas.')
        ->press('Cerrar y responder')
        ->assertSee('Caso cerrado.')
        ->assertSee('Hablamos con las personas involucradas.')
        ->assertSee('Abrir de nuevo')
        ->assertNoJavaScriptErrors();

    expect($case->refresh()->status->value)->toBe('closed');
});

it('shows a worked case with its thread, in light, dark and on a phone', function (bool $dark, bool $mobile): void {
    [$url, $case] = handlerWithCases();
    $owner = App\Modules\Tenancy\Models\TenantMembership::query()->where('tenant_id', $case->tenant_id)->firstOrFail()->user_id;
    $position = 0;
    foreach ([['handler_message', 'Acusamos recibo de su queja.'], ['internal_note', 'Hablé con el jefe de turno.']] as [$kind, $text]) {
        App\Modules\Complaints\Models\ComplaintEntry::factory()->create(['tenant_id' => $case->tenant_id, 'complaint_id' => $case->id, 'position' => ++$position, 'kind' => $kind, 'body' => $text, 'author_id' => $owner]);
    }
    App\Modules\Complaints\Models\ComplaintEntry::factory()->fromReporter('2026-10-02')->create(['tenant_id' => $case->tenant_id, 'complaint_id' => $case->id, 'position' => 3, 'body' => 'Sigue pasando esta semana.']);

    visitPage('complaints/show', "{$url}/complaints/cases/{$case->id}", $dark, $mobile)
        ->assertSee('Conversación con la persona')
        ->assertSee('Sigue pasando esta semana.')
        ->assertSee('La persona no las ve')
        ->assertSee('Hablé con el jefe de turno.')
        ->assertSee('Acuse de recibo')
        ->assertSee('Bitácora del caso');
})->with($looks);

it('steps aside from a case, which then no longer opens', function (): void {
    [$url, $case] = handlerWithCases();

    visit("{$url}/complaints/cases/{$case->id}")
        ->click('Excusarme de este caso')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Te excusaste del caso.')
        ->assertNoJavaScriptErrors();

    visit("{$url}/complaints/cases/{$case->id}")->assertSee('404');
});

it('keeps a case for a legal process from its switch, and lets it go again', function (): void {
    [$url, $case] = handlerWithCases();

    visit("{$url}/complaints/cases/{$case->id}")
        ->click('[role="switch"]')
        ->assertSee('Retención legal activada.')
        ->assertAttribute('[role="switch"]', 'aria-checked', 'true')
        ->click('[role="switch"]')
        ->assertSee('Retención legal levantada.')
        ->assertNoJavaScriptErrors();

    expect($case->refresh()->on_hold)->toBeFalse();
});

it('shows the outside handler how the channel is handled, from its menu', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    ComplaintChannel::factory()->forTenant($tenant->id)->active()->create(['procedure' => 'Recibimos su queja y la atiende una persona designada.']);
    test()->actingAs(memberOf($tenant, App\Modules\Tenancy\Enums\TenantRole::Complaints));

    visitPage('complaints/procedure', '/admin/acme/complaints/procedure', $dark, $mobile)
        ->assertSee('Cómo se atiende')
        ->assertSee('Recibimos su queja y la atiende una persona designada.')
        ->assertSee('3 días')
        ->assertSee('Cómo atiendes un caso');
})->with($looks);

/** A channel with a complaint whose reference and key the person knows. */
function followedComplaint(): array
{
    $channel = openChannelWithHandler();
    $complaint = Complaint::factory()->create(['tenant_id' => $channel->tenant_id, 'channel_id' => $channel->id, 'reference' => 'ABCD2345', 'access_key_hash' => hash('sha256', 'KEYK1234KEYK5678KEYK')]);
    App\Modules\Complaints\Models\ComplaintEntry::factory()->create(['tenant_id' => $channel->tenant_id, 'complaint_id' => $complaint->id, 'position' => 1, 'kind' => 'handler_message', 'body' => 'Acusamos recibo de su queja.', 'author_id' => ComplaintHandler::query()->withoutGlobalScopes()->where('tenant_id', $channel->tenant_id)->value('user_id')]);

    return [$channel, $complaint];
}

it('asks for the reference and the key to follow a complaint up', function (bool $dark, bool $mobile): void {
    [$channel] = followedComplaint();

    visitPage('complaints-public/follow-up', "/c/{$channel->access_code}/follow-up", $dark, $mobile)
        ->assertSee('Ver cómo va su queja')
        ->assertSee('Referencia')
        ->assertSee('Ver mi queja')
        ->assertSee('Si perdió la clave no podemos recuperarla')
        ->assertDontSee('El canal está en pausa')
        ->assertScript('() => document.cookie', '');
})->with($looks);

it('says the channel is paused while the people with a complaint follow it, with the other way', function (): void {
    [$channel] = followedComplaint();
    $channel->forceFill(['paused_at' => now()])->save();

    visitPage('complaints-public/follow-up', "/c/{$channel->access_code}/follow-up", false, true)
        ->assertSee('El canal está en pausa')
        ->assertSee('Inspección Federal del Trabajo')
        ->assertSee('Ver mi queja');
});

it('opens the case with the reference and the key, and writes in it', function (bool $mobile): void {
    [$channel, $complaint] = followedComplaint();
    $page = $mobile ? visit("/c/{$channel->access_code}/follow-up")->on()->mobile() : visit("/c/{$channel->access_code}/follow-up");

    $page->fill('#follow-up-reference', 'abcd 2345')
        ->fill('#follow-up-key', 'keyk-1234-keyk-5678-keyk')
        ->press('Ver mi queja')
        ->assertSee('ABCD2345')
        ->assertSee('Recibimos su queja')
        ->assertSee('Le responderemos a más tardar el')
        ->assertSee('Alguien la leyó')
        ->assertSee('Persona encargada')
        ->assertSee('Acusamos recibo de su queja.')
        ->assertNoAccessibilityIssues(1)
        ->fill('#follow-up-reply', 'Sigue pasando y ahora lo hace delante de todos.')
        ->press('Enviar')
        ->assertSee('Sigue pasando y ahora lo hace delante de todos.')
        ->assertSee('Usted')
        ->assertScript('() => document.cookie', '')
        ->click('Salir')
        ->assertSee('Ver cómo va su queja')
        ->assertDontSee('Sigue pasando')
        ->assertNoJavaScriptErrors();

    expect(App\Modules\Complaints\Models\ComplaintEntry::query()->withoutGlobalScopes()->where('complaint_id', $complaint->id)->where('kind', 'reporter_message')->count())->toBe(1);
})->with([[false], [true]]);

it('says the same when the key is wrong, and lets the person withdraw their complaint', function (): void {
    [$channel, $complaint] = followedComplaint();

    visit("/c/{$channel->access_code}/follow-up")
        ->fill('#follow-up-reference', 'ABCD2345')
        ->fill('#follow-up-key', 'WRONGWRONGWRONGWRONG')
        ->press('Ver mi queja')
        ->assertSee('La referencia o la clave no coinciden.')
        ->fill('#follow-up-key', 'KEYK1234KEYK5678KEYK')
        ->press('Ver mi queja')
        ->assertSee('Recibimos su queja')
        ->click('Retirar mi queja')
        ->click('[role="dialog"] button:has-text("Retirar mi queja")')
        ->assertSee('Su queja se cerró')
        ->assertSee('Usted retiró el caso.')
        ->assertNoJavaScriptErrors();

    expect($complaint->refresh()->resolution?->value)->toBe('withdrawn');
});

it('links the receipt to the follow-up and the form to it', function (): void {
    $channel = openChannelWithHandler();

    visit("/c/{$channel->access_code}")->assertSee('Ya presenté una: ver cómo va')->click('a[href$="/follow-up"]')->assertSee('Ver cómo va su queja')->assertNoJavaScriptErrors();
});

it('shows the evidence of the mechanism, ready to print, with no case in it', function (bool $dark, bool $mobile): void {
    [$url, $tenant] = complaintsPanel();
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create(['procedure' => 'Se acusa recibo y la atiende una persona designada.']);
    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => App\Modules\Tenancy\Models\TenantMembership::query()->where('tenant_id', $tenant->id)->firstOrFail()->user_id]);
    Complaint::factory()->count(6)->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'description' => 'Una historia reservada']);

    visitPage('complaints/evidence', "{$url}/complaints/evidence", $dark, $mobile)
        ->assertSee('Mecanismo seguro y confidencial para quejas y denuncias')
        ->assertSee('Se acusa recibo y la atiende una persona designada.')
        ->assertSee('Casos recibidos: 6')
        ->assertSee('Imprimir o guardar PDF')
        ->assertDontSee('Una historia reservada');
})->with($looks);

it('shows the checklist and the summary with the minimum on the channel page', function (): void {
    [$url, $tenant] = complaintsPanel();
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
    Complaint::factory()->count(3)->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

    visit("{$url}/complaints/channel")
        ->assertSee('Nadie atiende las quejas todavía')
        ->assertSee('Lo que ha llegado')
        ->assertSee('menos de 5')
        ->assertNoJavaScriptErrors();
});

it('shows the poster with a QR that draws, ready to print', function (bool $dark, bool $mobile): void {
    [$url, $tenant] = complaintsPanel();
    ComplaintChannel::factory()->forTenant($tenant->id)->active()->create(['procedure' => 'Se acusa recibo y la atiende una persona designada.']);

    visitPage('complaints/poster', "{$url}/complaints/poster", $dark, $mobile)
        ->assertSee('¿Algo no está bien en su trabajo?')
        ->assertSee('Le respondemos en 30 días.')
        ->assertSee('Violencia laboral')
        ->assertSee('Imprimir')
        ->assertScript('() => { const image = document.querySelector("img[alt^=\"Código QR\"]"); return image !== null && image.complete && image.naturalWidth > 0; }', true);
})->with($looks);

it('opens the poster from the channel page', function (): void {
    [$url, $tenant] = complaintsPanel();
    ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();

    visit("{$url}/complaints/channel")->click('Cartel para imprimir')->assertSee('¿Algo no está bien en su trabajo?')->assertNoJavaScriptErrors();
});

it('asks for a real address on the last step and lets it stay empty', function (): void {
    config(['complaints.ticket.min_seconds' => 0]);
    $channel = openChannelWithHandler();

    visit("/c/{$channel->access_code}")->on()->mobile()
        ->click('Presentar una queja')
        ->click('Queja por una práctica opuesta a un entorno favorable')
        ->press('Continuar')
        ->click('Una compañera o compañero')
        ->press('Continuar')
        ->fill('#complaint-description', 'Las cargas de trabajo del turno de noche son excesivas desde hace meses.')
        ->press('Continuar')
        ->fill('#notify-email', 'no es un correo')
        ->press('Enviar queja')
        ->assertSee('Escriba un correo válido o déjelo vacío.')
        ->assertScript(smallTouchTargets(), '')
        ->fill('#notify-email', '')
        ->press('Enviar queja')
        ->assertSee('Recibimos su queja')
        ->assertNoJavaScriptErrors();
});

// C-15, E-18 and E-19: the promise is exact, the key is told to be kept now, and it can be handed to the phone's own share sheet.
it('promises only what the routing does, and tells the person to keep the receipt now', function (): void {
    config(['complaints.ticket.min_seconds' => 0]);
    $channel = openChannelWithHandler();

    $page = visit("/c/{$channel->access_code}")->on()->mobile()
        ->assertSee('La lee solo quien la empresa designó para atender quejas. Si su queja es contra Recursos Humanos o contra la dirección, no llega a ellos.')
        ->assertDontSee('nunca la persona de la que se queja')
        ->click('Presentar una queja')
        ->click('Denuncia de violencia laboral')
        ->press('Continuar')
        ->click('Mi jefa o jefe inmediato')
        ->press('Continuar')
        ->press('Continuar')
        ->fill('#complaint-description', 'Mi jefe me grita todos los días frente a mis compañeros y me amenaza.')
        ->press('Continuar');
    // The browser of the test has a share sheet: the button shows, and nothing is written on the device.
    $page->script('() => { navigator.share = () => Promise.resolve(); return true; }');
    $page->press('Enviar queja')
        ->assertSee('Guárdela ahora: no se vuelve a mostrar. Si el dispositivo no es suyo, cierre esta pantalla al terminar.')
        // Only the page's own theme may sit on the device: nothing of the complaint, and above all not the key.
        ->assertScript('() => Object.entries({ ...window.localStorage, ...window.sessionStorage }).filter(([key]) => key !== "appearance").length', 0)
        ->assertNoJavaScriptErrors();
});
