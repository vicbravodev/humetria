<?php

declare(strict_types=1);

use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/** A company with a person of RH signed in; returns the address of its panel. */
function humiPanelCompany(): string
{
    // The browser's server keeps its limits in the database cache, which outlives a test: start each one clean.
    Cache::store('database')->flush();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $hr = memberOf($tenant, TenantRole::Hr);
    $participant = ConversationParticipant::factory()->for($hr)->create(['tenant_id' => $tenant->id]);
    assistantConversation($participant, 'Avance de mis campañas');
    test()->actingAs($hr);

    return '/admin/acme';
}

it('shows the history of Humi as the panel pages are drawn, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    visitPage('assistant/index', humiPanelCompany().'/assistant', $dark, $mobile)
        ->assertSee('Pregúntale por el cumplimiento de tu empresa')
        ->assertSee('Avance de mis campañas')
        ->assertSee('Hola, soy Humi')
        ->assertScript(cspViolations(), '[]');
})->with([
    'light' => [false, false],
    'dark' => [true, false],
    'a phone' => [false, true],
]);

/**
 * The answer of Humi as the server streams it (the Vercel data protocol, with a tool call and its card), served by the page
 * itself: the server of the browser tests hands a streamed body over empty, so the stream is checked against the real
 * server in tests/Feature/Assistant/ChatTest.php and drawn here.
 */
function humiStream(): string
{
    $card = ['render' => 'risk_levels', 'title' => 'Resultados por nivel de riesgo', 'rows' => [
        ['kind' => 'final', 'label' => 'Calificación final', 'answered' => 6, 'levels' => ['Nulo o despreciable' => 0, 'Bajo' => 1, 'Medio' => 5, 'Alto' => 0, 'Muy alto' => 0]],
        ['kind' => 'domain', 'label' => 'Carga de trabajo', 'levels' => ['Nulo o despreciable' => 2, 'Bajo' => 2, 'Medio' => 1, 'Alto' => 1, 'Muy alto' => 0]],
    ], 'link' => '/admin/acme/campaigns/1/results', 'note' => null, 'truncated' => false, 'minGroup' => 5];
    $parts = [
        ['type' => 'start', 'messageId' => 'm1'],
        ['type' => 'start-step'],
        ['type' => 'tool-input-available', 'toolCallId' => 't1', 'toolName' => 'GetRiskResults', 'input' => []],
        ['type' => 'tool-output-available', 'toolCallId' => 't1', 'output' => json_encode($card)],
        ['type' => 'finish-step'],
        ['type' => 'start-step'],
        ['type' => 'text-start', 'id' => 'x1'],
        ['type' => 'text-delta', 'id' => 'x1', 'delta' => 'El riesgo más alto está en **carga de trabajo**.'],
        ['type' => 'text-end', 'id' => 'x1'],
        ['type' => 'finish-step'],
        ['type' => 'finish', 'finishReason' => 'stop'],
    ];

    return implode('', array_map(fn (array $part): string => 'data: '.json_encode($part)."\n\n", $parts))."data: [DONE]\n\n";
}

/** A browser script that answers the chat request with `$body` and leaves the other requests of the page alone. */
function humiServes(string $body): string
{
    $body = json_encode($body, JSON_THROW_ON_ERROR);

    return "() => { if (!window.humiServes) { window.humiServes = true; const real = window.fetch.bind(window); window.fetch = (input, init) => String(input instanceof Request ? input.url : input).endsWith('/assistant/chat') ? Promise.resolve(new Response({$body}, { headers: { 'Content-Type': 'text/event-stream' } })) : real(input, init); } return 'ready'; }";
}

it('opens from the launcher, shows the answer with what it looked at, its card and its text, and closes, in both themes', function (bool $dark): void {
    visitPage('dashboard', humiPanelCompany(), $dark)
        ->assertScript(humiServes(humiStream()), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->assertSee('Hola, soy Humi')
        ->assertSee('Humi puede equivocarse. Verifica las cifras antes de reportarlas.')
        ->click('¿Cómo va la participación?')
        ->assertSee('Consulté los resultados de riesgo')
        ->assertSee('Apliqué el mínimo de 5 personas por grupo')
        ->assertSee('Calificación final')
        ->assertSee('Carga de trabajo')
        ->assertSee('Solo se muestran grupos de al menos 5 personas.')
        ->assertSee('El riesgo más alto está en')
        ->assertSee('¿Qué dice la norma que debo hacer?')
        ->assertScript('() => document.querySelectorAll(\'[aria-label="Copiar respuesta"]\').length', 1)
        ->assertScript(cspViolations(), '[]')
        ->assertNoJavaScriptErrors()
        ->click('[aria-label="Cerrar"]')
        ->assertDontSee('Hola, soy Humi');
})->with([
    'light' => [false],
    'dark' => [true],
]);

/** An answer that looked the norm up twice, with the markdown a model writes without blank lines. */
function humiRegulationsStream(): string
{
    $criterion = ['norm' => 'NOM-037', 'id' => 'C-01', 'title' => 'La NOM-037 aplica a quien teletrabaja más del 40 % de su jornada', 'source' => 'Norma + STPS', 'text' => "**Qué hacemos.** Una persona entra al padrón con **más de 40 %**.\n\n**Qué dice la ley.** Otra cosa."];
    $card = json_encode(['render' => 'list', 'title' => 'Normativa', 'rows' => [$criterion], 'link' => null, 'note' => null, 'truncated' => false]);
    $text = "### ¿A quién aplica?\nA quienes teletrabajan más del 40 %.\nPuntos:\n* Una política\n* Una comisión\n*Fuente: criterio C-01.*";
    $parts = [
        ['type' => 'start', 'messageId' => 'm1'],
        ['type' => 'tool-input-available', 'toolCallId' => 't1', 'toolName' => 'SearchRegulations', 'input' => []],
        ['type' => 'tool-output-available', 'toolCallId' => 't1', 'output' => $card],
        ['type' => 'tool-input-available', 'toolCallId' => 't2', 'toolName' => 'SearchRegulations', 'input' => []],
        ['type' => 'tool-output-available', 'toolCallId' => 't2', 'output' => $card],
        ['type' => 'text-start', 'id' => 'x1'],
        ['type' => 'text-delta', 'id' => 'x1', 'delta' => $text],
        ['type' => 'text-end', 'id' => 'x1'],
        ['type' => 'finish', 'finishReason' => 'stop'],
    ];

    return implode('', array_map(fn (array $part): string => 'data: '.json_encode($part)."\n\n", $parts))."data: [DONE]\n\n";
}

it('draws the markdown of an answer and its norm card, once, and Humi reacts: it floats, and listens when the person writes', function (bool $dark): void {
    visitPage('dashboard', humiPanelCompany(), $dark)
        ->assertScript(humiServes(humiRegulationsStream()), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->assertScript('() => document.querySelectorAll(\'header .humi-bob\').length', 1)
        ->assertScript('() => document.querySelectorAll(\'header path[d="M148 238 H172"]\').length', 0)
        ->click('[aria-label="Pregúntale algo a Humi"]')
        ->assertScript('() => document.querySelectorAll(\'header path[d="M148 238 H172"]\').length', 1)
        ->click('¿Cómo va la participación?')
        ->assertSee('¿A quién aplica?')
        ->assertSee('NOM-037 · C-01')
        ->assertSee('Norma + STPS')
        ->assertSee('Una persona entra al padrón con')
        ->assertScript('() => /###|\*\*|\*Fuente/.test(document.body.innerText)', false)
        ->assertScript('() => document.body.innerText.split("Consulté la normativa").length - 1', 1)
        ->assertScript('() => document.querySelectorAll(\'[aria-label="Normativa"]\').length', 1)
        ->assertScript('() => document.querySelectorAll(\'[aria-label="Normativa"] li\').length', 1)
        ->assertScript('() => getComputedStyle(document.querySelector(\'[aria-label="Normativa"]\')).animationName', 'humi-enter')
        ->assertScript('() => document.querySelectorAll(\'[role="log"] li\').length > 0', true)
        ->assertScript('() => document.querySelectorAll(\'[role="log"] em\').length', 1)
        ->assertScript(cspViolations(), '[]')
        ->assertNoJavaScriptErrors();
})->with([
    'light' => [false],
    'dark' => [true],
]);

it('slides the docked panel out when it closes instead of removing it at once, then brings the button back', function (): void {
    visitPage('dashboard', humiPanelCompany())
        ->click('[aria-label="Abrir a Humi"]')
        ->assertScript('() => getComputedStyle(document.querySelector(\'aside[aria-label="Humi"]\')).animationName', 'humi-slide-in')
        ->click('[aria-label="Cerrar"]')
        ->assertScript('() => getComputedStyle(document.querySelector(\'aside[aria-label="Humi"]\')).animationName', 'humi-slide-out')
        ->assertScript('() => new Promise((done) => setTimeout(() => done(document.querySelectorAll(\'aside[aria-label="Humi"]\').length), 600))', 0)
        ->assertScript('() => document.querySelectorAll(\'[aria-label="Abrir a Humi"]\').length', 1)
        ->assertNoJavaScriptErrors();
});

it('says why Humi cannot answer when it is off for the company, and keeps the person from writing', function (): void {
    $panel = humiPanelCompany();
    TenantAiSettings::factory()->create(['tenant_id' => Tenant::query()->where('slug', 'acme')->sole()->id, 'enabled' => false]);

    visitPage('dashboard', $panel)
        ->click('[aria-label="Abrir a Humi"]')
        ->click('¿Qué me falta para estar al día?')
        ->assertSee('Humi no está disponible para tu empresa. Pídele a tu administrador que la active.')
        ->assertScript('() => document.querySelector(\'textarea\').placeholder', 'Humi no está disponible');
});

it('lets the superadmin turn Humi off for a company and set its budget from the console', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Asistente Humi')
        ->assertSee('Humi en Grupo Ejemplo')
        ->assertSee('Este mes usó 0 tokens.')
        ->click('Humi está activa para esta empresa')
        ->type('monthly_token_budget', '5000')
        ->press('Guardar')
        ->assertSee('Humi actualizada para la empresa.')
        ->assertNoJavaScriptErrors();

    $settings = TenantAiSettings::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

    expect($settings->enabled)->toBeFalse()
        ->and($settings->monthly_token_budget)->toBe(5000);
});

it('passes the accessibility and CSP checks with the Humi dialog of the console open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('@tenant-actions')->click('Asistente Humi')->assertSee('Tokens por mes'));
})->with([
    'light' => [false, false],
    'dark' => [true, false],
    'a phone' => [false, true],
]);
