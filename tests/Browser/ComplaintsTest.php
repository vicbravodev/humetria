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
        ->assertSee('Quejas y denuncias')
        ->assertSee('Sin activar')
        ->assertSee('Procedimiento')
        ->assertSee('Activar el canal');
})->with($looks);

it('shows an active channel with its link and the actions on it', function (bool $dark, bool $mobile): void {
    [$url, $tenant] = complaintsPanel();
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();

    visitPage('complaints/channel', "{$url}/complaints/channel", $dark, $mobile)
        ->assertSee('Activo')
        ->assertSee($channel->publicUrl())
        ->assertSee('Copiar enlace')
        ->assertSee('Guardar ajustes');
})->with($looks);

it('activates the channel from the page', function (): void {
    [$url] = complaintsPanel();

    visit("{$url}/complaints/channel")
        ->press('Activar el canal')
        ->assertSee('El canal quedó activo.')
        ->assertSee('Enlace del canal')
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

it('shows who handles the complaints and who each kind of complaint would reach', function (bool $dark, bool $mobile): void {
    [$url, $tenant] = complaintsPanel();
    $owner = App\Modules\Tenancy\Models\TenantMembership::query()->where('tenant_id', $tenant->id)->firstOrFail();
    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => $owner->user_id]);

    visitPage('complaints/handlers', "{$url}/complaints/handlers", $dark, $mobile)
        ->assertSee('Quién atiende')
        ->assertSee('Toda la empresa')
        ->assertSee('A quién llegaría cada queja')
        ->assertSee('Falta')
        ->assertSee('Designar');
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
        ->click('#handler-person')
        ->type('[role="combobox"][aria-controls="handler-person-options"]', 'recursos')
        ->assertSee('Ana Recursos')
        ->keys('[role="combobox"][aria-controls="handler-person-options"]', 'Enter')
        ->press('Designar')
        ->assertSee('Persona designada.')
        ->assertSee('Ana Recursos')
        ->assertNoJavaScriptErrors();
});

it('opens the public form of the channel with what to read first, and leaves no cookie', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme Industrial']);
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();

    visitPage('complaints-public/form', "/c/{$channel->access_code}", $dark, $mobile)
        ->assertSee('Acme Industrial')
        ->assertSee('Quejas y denuncias')
        ->assertSee('Cómo se atiende')
        ->assertSee('Qué protegemos y qué no')
        ->assertScript('() => document.cookie', '');
})->with($looks);

it('says the channel is paused and offers the other way, or that the link is gone', function (string $case): void {
    $tenant = Tenant::factory()->create();
    $code = $case === 'paused' ? ComplaintChannel::factory()->forTenant($tenant->id)->paused()->create()->access_code : 'ZZZZZZZZZZZZZZZZ';

    visitPage('complaints-public/unavailable', "/c/{$code}")
        ->assertSee($case === 'paused' ? 'El canal no está disponible por ahora' : 'Este enlace no está disponible')
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

it('walks the form step by step and hands back a reference and a key once', function (bool $mobile): void {
    // A robot is quicker than any person: the three seconds of the ticket have their own tests.
    config(['complaints.ticket.min_seconds' => 0]);
    $channel = openChannelWithHandler();
    $page = $mobile ? visit("/c/{$channel->access_code}")->on()->mobile() : visit("/c/{$channel->access_code}");

    $page->click('Quiero presentar una queja')
        ->assertSee('¿Qué quiere informar?')
        ->click('Denuncia de violencia laboral')
        ->press('Continuar')
        ->assertSee('¿Qué pasó?')
        ->press('Continuar')
        ->assertSee('Cuéntenos un poco más')
        ->fill('#complaint-description', 'Mi jefe me grita todos los días frente a mis compañeros y me amenaza.')
        ->press('Continuar')
        ->assertSee('¿Dónde y de quién se trata?')
        ->assertSee('Por ahora la empresa no tiene a nadie independiente')
        ->press('Continuar')
        ->assertSee('Elija de quién se trata')
        ->click('Mi jefa o jefe inmediato')
        ->press('Continuar')
        ->assertSee('¿Cómo quiere ir?')
        ->assertNoAccessibilityIssues(1)
        ->press('Continuar')
        ->assertSee('Revise antes de enviar')
        ->assertSee('Anónima: sin nombre ni contacto')
        ->assertNoAccessibilityIssues(1)
        ->press('Enviar mi queja')
        ->assertSee('Recibimos su queja')
        ->assertSee('Referencia')
        ->assertSee('Clave')
        ->assertScript('() => document.cookie', '')
        ->assertNoJavaScriptErrors();

    expect(Complaint::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Complaint::query()->withoutGlobalScopes()->first()?->description)->toContain('me grita');
})->with([[false], [true]]);

it('shows the form steps without accessibility issues and with room for a finger', function (): void {
    $channel = openChannelWithHandler();

    visit("/c/{$channel->access_code}")->on()->mobile()
        ->click('Quiero presentar una queja')
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
        ->click('Quiero presentar una queja')
        ->click('Queja por una práctica opuesta a un entorno favorable')
        ->press('Continuar')
        ->fill('#complaint-description', 'Las cargas de trabajo del turno de noche son excesivas desde hace meses.')
        ->press('Continuar')
        ->click('Una compañera o compañero')
        ->press('Continuar')
        ->click('[name="identity_mode"][value="confidential"]')
        ->press('Continuar')
        ->assertSee('Escriba su nombre.')
        ->fill('#reporter-name', 'Rosa Aguilar')
        ->press('Continuar')
        ->assertSee('Confidencial, con su nombre (Rosa Aguilar)')
        ->press('Enviar mi queja')
        ->assertSee('Recibimos su queja')
        ->assertNoJavaScriptErrors();

    expect(Complaint::query()->withoutGlobalScopes()->sole()->reporter_name)->toBe('Rosa Aguilar');
});

it('asks for the day and the name of the notice of a traumatic event, and sends it', function (): void {
    config(['complaints.ticket.min_seconds' => 0]);
    $channel = openChannelWithHandler();

    visit("/c/{$channel->access_code}")
        ->click('Quiero presentar una queja')
        ->click('Aviso de un acontecimiento traumático severo')
        ->press('Continuar')
        ->fill('#complaint-description', 'Presencié un accidente en el que un compañero perdió la mano en la prensa.')
        ->press('Continuar')
        ->assertSee('Indique la fecha en que ocurrió.')
        ->click('#complaint-date')
        ->click('[data-day] button[tabindex="0"]')
        ->press('Continuar')
        ->assertSee('¿Dónde ocurrió?')
        ->assertDontSee('¿De quién se trata?')
        ->press('Continuar')
        ->assertSee('Este aviso necesita su nombre')
        ->fill('#reporter-name', 'Rosa Aguilar')
        ->press('Continuar')
        ->assertSee('Fecha en que ocurrió')
        ->press('Enviar mi queja')
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
        ->assertSee('Información confidencial.')
        ->assertSee('ABCD2345')
        ->assertSee('WXYZ6789')
        ->assertSee('Acuse vencido')
        ->assertSee('Planta Querétaro')
        ->assertDontSee('humilla')
        ->assertDontSee('Rosa Aguilar');
})->with($looks);

it('shows the empty inbox with what makes it fill', function (): void {
    [$url] = complaintsPanel();

    visitPage('complaints/index', "{$url}/complaints")
        ->assertSee('Sin casos abiertos');
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
        ->assertSee('Caso ABCD2345')
        ->assertSee('humilla frente a todos')
        ->assertSee('Rosa Aguilar')
        ->assertSee('Cel. 442 000 0000')
        ->assertSee('Tu consulta queda registrada');
})->with($looks);

it('opens an anonymous case saying that there is nobody to ask for', function (): void {
    [$url, , $case] = handlerWithCases();

    visitPage('complaints/show', "{$url}/complaints/cases/{$case->id}")
        ->assertSee('Anónima: no hay nombre')
        ->assertSee('Las cargas del turno de noche');
});

it('opens a case from the inbox', function (): void {
    [$url] = handlerWithCases();

    visit("{$url}/complaints")
        ->click('ABCD2345')
        ->assertSee('Lo que cuenta la persona')
        ->assertNoJavaScriptErrors();
});
