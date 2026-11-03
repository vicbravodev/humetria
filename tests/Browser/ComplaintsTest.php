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
        ->type('[role="combobox"][aria-controls="handler-person-options"]', 'ana')
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
