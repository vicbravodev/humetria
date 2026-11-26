<?php

declare(strict_types=1);

use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\Campaigns\Models\Campaign;
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

/** An answer that asks which part the person meant, with buttons, and stops there. */
function humiChoicesStream(): string
{
    $card = ['render' => 'choices', 'title' => '¿De qué parte?', 'rows' => [
        ['label' => 'De todo', 'text' => 'Quiero ver todos mis pendientes'],
        ['label' => 'NOM-035', 'text' => 'Quiero ver mis pendientes de NOM-035'],
    ], 'link' => null, 'note' => null, 'truncated' => false];
    $parts = [
        ['type' => 'start', 'messageId' => 'a1'],
        ['type' => 'start-step'],
        // What Humi consulted to know what to offer: its card must not be drawn over the question.
        ['type' => 'tool-input-available', 'toolCallId' => 't0', 'toolName' => 'GetComplianceOverview', 'input' => []],
        ['type' => 'tool-output-available', 'toolCallId' => 't0', 'output' => json_encode(['render' => 'status_list', 'title' => 'Resumen consultado oculto', 'rows' => [], 'link' => null, 'note' => null, 'truncated' => false])],
        ['type' => 'tool-input-available', 'toolCallId' => 't1', 'toolName' => 'OfferChoices', 'input' => []],
        ['type' => 'tool-output-available', 'toolCallId' => 't1', 'output' => json_encode($card)],
        ['type' => 'finish-step'],
        ['type' => 'finish', 'finishReason' => 'stop'],
    ];

    return implode('', array_map(fn (array $part): string => 'data: '.json_encode($part)."\n\n", $parts))."data: [DONE]\n\n";
}

/** The plain answer that follows a click on a button. */
function humiAfterChoiceStream(): string
{
    $parts = [
        ['type' => 'start', 'messageId' => 'a2'],
        ['type' => 'start-step'],
        ['type' => 'text-start', 'id' => 'x1'],
        ['type' => 'text-delta', 'id' => 'x1', 'delta' => 'Estos son tus pendientes de NOM-035.'],
        ['type' => 'text-end', 'id' => 'x1'],
        ['type' => 'finish-step'],
        ['type' => 'finish', 'finishReason' => 'stop'],
    ];

    return implode('', array_map(fn (array $part): string => 'data: '.json_encode($part)."\n\n", $parts))."data: [DONE]\n\n";
}

/** Serves each answer of the chat in turn: the first call gets the first body, the second the second. */
function humiServesInOrder(string ...$bodies): string
{
    $bodies = json_encode($bodies, JSON_THROW_ON_ERROR);

    return "() => { if (!window.humiServes) { window.humiServes = true; const bodies = {$bodies}; let call = 0; const real = window.fetch.bind(window); window.fetch = (input, init) => String(input instanceof Request ? input.url : input).endsWith('/assistant/chat') ? Promise.resolve(new Response(bodies[Math.min(call++, bodies.length - 1)], { headers: { 'Content-Type': 'text/event-stream' } })) : real(input, init); } return 'ready'; }";
}

it('asks with buttons, sends the option as the person\'s message, and keeps the buttons as a record, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    accessible(visitPage('dashboard', humiPanelCompany(), $dark, $mobile)
        ->assertScript(humiServesInOrder(humiChoicesStream(), humiAfterChoiceStream()), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->click('¿Cómo va la participación?')
        ->assertSee('¿De qué parte?')
        ->assertSee('Consulté el estado de cumplimiento')
        ->assertDontSee('Resumen consultado oculto')
        ->assertScript('() => [...document.querySelectorAll(\'[role="group"][aria-label="¿De qué parte?"] button\')].map((button) => button.disabled).join()', 'false,false')
        ->click('NOM-035')
        ->assertSee('Quiero ver mis pendientes de NOM-035')
        ->assertSee('Estos son tus pendientes de NOM-035.')
        ->assertScript('() => JSON.stringify([...document.querySelectorAll(\'[role="group"][aria-label="¿De qué parte?"] button\')].map((button) => [button.disabled, button.getAttribute(\'aria-pressed\')]))', '[[true,"false"],[true,"true"]]')
        ->assertScript(cspViolations(), '[]')
        ->assertScript(humiSettled(), true), 1)
        ->assertNoJavaScriptErrors();
})->with([
    'light' => [false, false],
    'dark' => [true, false],
    'phone' => [false, true],
]);

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

it('says why Humi cannot answer when the budget of the month is used, and keeps the person from writing', function (): void {
    $panel = humiPanelCompany();
    $tenantId = Tenant::query()->where('slug', 'acme')->sole()->id;
    TenantAiSettings::factory()->create(['tenant_id' => $tenantId, 'monthly_token_budget' => 10]);
    AiUsage::factory()->create(['tenant_id' => $tenantId, 'input_tokens' => 10, 'output_tokens' => 0]);

    visitPage('dashboard', $panel)
        ->click('[aria-label="Abrir a Humi"]')
        ->click('¿Qué me falta para estar al día?')
        ->assertSee('Tu empresa agotó el uso de Humi de este mes.')
        ->assertScript('() => document.querySelector(\'textarea\').placeholder', 'Humi no está disponible');
});

it('does not offer Humi at all when it is off for the company', function (): void {
    $panel = humiPanelCompany();
    TenantAiSettings::factory()->create(['tenant_id' => Tenant::query()->where('slug', 'acme')->sole()->id, 'enabled' => false]);

    visitPage('dashboard', $panel)
        ->assertMissing('[aria-label="Abrir a Humi"]')
        ->assertMissing('aside[aria-label="Humi"]');
});

it('lets the superadmin turn Humi off for a company and set its budget from the console', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Asistente Humi')
        ->assertSee('Humi en Grupo Ejemplo')
        ->assertSee('Este mes usó 0 tokens.')
        ->click('Activar Humi para esta empresa')
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

/** A browser script that reads the header of Humi: the room at its right, its height and the actions it shows, in order. */
function humiHeader(): string
{
    return <<<'JS'
        () => {
            const header = document.querySelector('header:has(+ div > [role="log"])');
            const actions = [...header.querySelectorAll('a, button')].filter((action) => action.offsetParent !== null).map((action) => action.getAttribute('aria-label'));
            const closes = [...document.querySelectorAll('[aria-label="Cerrar"], button:has(> .sr-only)')].filter((action) => action.offsetParent !== null && (action.getAttribute('aria-label') ?? action.textContent.trim()) === 'Cerrar').length;
            return `${getComputedStyle(header).paddingRight} ${header.getBoundingClientRect().height} ${actions.join(', ')} | ${closes}`;
        }
        JS;
}

it('keeps the actions of the header together at 16 px from the edge, the same height with or without the close button', function (): void {
    $panel = humiPanelCompany();

    visitPage('dashboard', $panel)
        ->click('[aria-label="Abrir a Humi"]')
        ->assertScript(humiHeader(), '16px 68 Nueva conversación, Abrir en página completa, Cerrar | 1');

    visitPage('assistant/index', $panel.'/assistant')
        ->assertScript(humiHeader(), '16px 68  | 0');

    // On a phone the sheet's own close is not drawn: the header's, of 44 px like the rest, is the only one.
    visitPage('dashboard', $panel, mobile: true)
        ->click('[aria-label="Abrir a Humi"]')
        ->assertScript(humiHeader(), '16px 68 Nueva conversación, Cerrar | 1');
});

/** Like humiServes(), and keeps the body of each chat request in window.humiSent for the test to read. */
function humiRecords(string $body): string
{
    $body = json_encode($body, JSON_THROW_ON_ERROR);

    return "() => { window.humiSent = []; const real = window.fetch.bind(window); window.fetch = (input, init) => { if (String(input instanceof Request ? input.url : input).endsWith('/assistant/chat')) { window.humiSent.push(JSON.parse(init.body)); return Promise.resolve(new Response({$body}, { headers: { 'Content-Type': 'text/event-stream' } })); } return real(input, init); }; return 'ready'; }";
}

it('shows the page Humi is looking at beside the box, and stops sending it once the person removes it', function (): void {
    $panel = humiPanelCompany();
    $campaign = Campaign::factory()->active(now()->addDays(10)->toDateString())->create(['tenant_id' => Tenant::query()->where('slug', 'acme')->sole()->id]);
    $sentPage = '() => window.humiSent.map((body) => body.pageContext?.kind ?? "ninguna").join(",")';

    visitPage('campaigns/show', "{$panel}/campaigns/{$campaign->id}")
        ->assertScript(humiRecords(humiStream()), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->assertSee('Viendo: Esta campaña')
        ->click('¿Cómo va la participación?')
        ->assertSee('El riesgo más alto está en')
        ->assertScript($sentPage, 'campaign')
        ->click('[aria-label="Quitar contexto"]')
        ->assertDontSee('Viendo: Esta campaña')
        ->click('¿Qué dice la norma que debo hacer?')
        ->assertScript('() => new Promise((done) => setTimeout(() => done(window.humiSent.length), 300))', 2)
        ->assertScript($sentPage, 'campaign,ninguna')
        ->assertNoJavaScriptErrors();

    visitPage('dashboard', $panel)
        ->click('[aria-label="Abrir a Humi"]')
        ->assertDontSee('Viendo:')
        ->assertMissing('[aria-label="Quitar contexto"]');
});

it('starts with what Humi does and does not see, and the first questions as a list with lines, each with the real fact of the company it answers', function (): void {
    visitPage('dashboard', humiPanelCompany())
        ->click('[aria-label="Abrir a Humi"]')
        ->assertSee('Hola, soy Humi')
        ->assertSee('Leo el cumplimiento de tu empresa y te explico la norma. No cambio datos.')
        ->assertSee('Humi nunca ve respuestas individuales ni la Guía I. Solo resultados de grupos, con el mínimo de personas que fijó cada evaluación.')
        ->assertScript('() => [...document.querySelectorAll(\'[role="log"] li > button\')].map((button) => `${getComputedStyle(button).borderTopWidth} ${getComputedStyle(button).borderLeftWidth}`).join(",")', '1px 0px,1px 0px,1px 0px,1px 0px')
        // The company has no work center yet: that is its one pending item, and no campaign runs.
        ->assertSee('1 pendiente')
        ->assertSee('No hay campañas en curso')
        // «¿Qué pendientes tengo primero?» has no fact of its own: it shows alone, never a made-up one.
        ->assertScript('() => document.querySelectorAll(\'[role="log"] li > button small\').length', 3);
});

/** An answer whose tool says where it came from and where to go next, as a tool may bring it. */
function humiSourcedStream(): string
{
    $card = ['render' => 'progress', 'title' => 'Avance de campañas', 'rows' => [], 'link' => null, 'note' => null, 'truncated' => false,
        'next' => ['label' => 'Ver la campaña', 'link' => '/admin/acme/campaigns'], 'sources' => ['Campaña 2026', 'Guías I y III']];
    $parts = [
        ['type' => 'start', 'messageId' => 'm1'],
        ['type' => 'tool-input-available', 'toolCallId' => 't1', 'toolName' => 'GetCampaignProgress', 'input' => []],
        ['type' => 'tool-output-available', 'toolCallId' => 't1', 'output' => json_encode($card)],
        ['type' => 'text-start', 'id' => 'x1'],
        ['type' => 'text-delta', 'id' => 'x1', 'delta' => 'Van 142 de 186 personas.'],
        ['type' => 'text-end', 'id' => 'x1'],
        ['type' => 'finish', 'finishReason' => 'stop'],
    ];

    return implode('', array_map(fn (array $part): string => 'data: '.json_encode($part)."\n\n", $parts))."data: [DONE]\n\n";
}

it('ends an answer with its actions, and with its sources and next step only when the tool brought them', function (bool $sourced): void {
    $page = visitPage('dashboard', humiPanelCompany())
        ->assertScript(humiServes($sourced ? humiSourcedStream() : humiStream()), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->click('¿Cómo va la participación?')
        ->assertPresent('[aria-label="Copiar respuesta"]')
        ->assertPresent('[aria-label="Responder de nuevo"]');

    if ($sourced) {
        $page->assertSee('Fuentes')
            ->assertScript('() => [...document.querySelectorAll(\'[aria-label="Fuentes"] li\')].map((source) => source.innerText.replace(/\s+/g, " ")).join(",")', '1 Campaña 2026,2 Guías I y III')
            ->assertScript('() => document.querySelector(\'a[href="/admin/acme/campaigns"]\')?.innerText', 'Ver la campaña');
    } else {
        $page->assertSee('El riesgo más alto está en')
            ->assertMissing('[aria-label="Fuentes"]')
            ->assertDontSee('Ver la campaña');
    }

    $page->assertScript(cspViolations(), '[]')->assertNoJavaScriptErrors();
})->with([
    'with sources' => [true],
    'without them' => [false],
]);

/** A browser script that waits for the entrances to end, so axe judges colors as they settle, not mid fade. */
function humiSettled(): string
{
    return '() => Promise.all(document.getAnimations().filter((animation) => animation.effect?.getTiming().iterations !== Infinity).map((animation) => animation.finished.catch(() => undefined))).then(() => true)';
}

/** A browser script that answers the chat request with `$response`, a JavaScript expression of a Response. */
function humiAnswersWith(string $response): string
{
    return "() => { const real = window.fetch.bind(window); window.fetch = (input, init) => String(input instanceof Request ? input.url : input).endsWith('/assistant/chat') ? Promise.resolve({$response}) : real(input, init); return 'ready'; }";
}

it('shows Humi thinking with the steps it takes and a skeleton, and lets the person stop it', function (): void {
    $parts = json_encode(implode('', array_map(fn (array $part): string => 'data: '.json_encode($part)."\n\n", [
        ['type' => 'start', 'messageId' => 'm1'],
        ['type' => 'tool-input-available', 'toolCallId' => 't1', 'toolName' => 'GetCampaignProgress', 'input' => []],
    ])));
    // A stream that never ends: Humi stays on it until the person stops it.
    $stalled = "new Response(new ReadableStream({ start(controller) { controller.enqueue(new TextEncoder().encode({$parts})); } }), { headers: { 'Content-Type': 'text/event-stream' } })";

    visitPage('dashboard', humiPanelCompany())
        ->assertScript(humiAnswersWith($stalled), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->click('¿Cómo va la participación?')
        ->assertSee('Consultando el avance de campañas')
        ->assertScript('() => document.querySelectorAll(\'[role="log"] [data-slot="skeleton"]\').length', 2)
        ->assertScript('() => document.querySelector(\'textarea\').placeholder', 'Humi está respondiendo')
        ->click('[aria-label="Detener"]')
        ->assertPresent('[aria-label="Enviar"]')
        ->assertNoJavaScriptErrors();
});

it('says when Humi is back after too many questions, and that the page keeps working', function (): void {
    $refusal = json_encode(json_encode(['reason' => 'rate', 'message' => 'Alcanzaste el límite de mensajes por ahora.', 'retry_after' => 240]));

    accessible(visitPage('dashboard', humiPanelCompany())
        ->assertScript(humiAnswersWith("new Response({$refusal}, { status: 429, headers: { 'Content-Type': 'application/json' } })"), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->click('¿Cómo va la participación?')
        ->assertSee('Hiciste muchas preguntas seguidas. Humi vuelve en unos 4 minutos. La página sigue funcionando.')
        ->assertScript('() => document.querySelector(\'textarea\').placeholder', 'Disponible en unos minutos')
        ->assertScript(humiSettled(), true), 1);
});

it('says what failed with a way to retry and the code for support, with Humi listening again', function (bool $dark): void {
    accessible(visitPage('dashboard', humiPanelCompany(), $dark)
        ->assertScript(humiAnswersWith("new Response('Server Error', { status: 500, headers: { 'X-Request-Id': 'b3f1c2d4-0000-4000-8000-000000000001' } })"), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->click('¿Cómo va la participación?')
        ->assertSee('No pude responder esta vez.')
        ->assertSee('Si pasa otra vez, escribe a soporte con este código:')
        ->assertSee('b3f1c2d4-0000-4000-8000-000000000001')
        ->assertPresent('[role="alert"] button:has-text("Reintentar")')
        ->assertScript('() => document.querySelectorAll(\'[role="alert"] path[d="M148 238 H172"]\').length', 1)
        ->assertScript(humiSettled(), true), 1);
})->with([
    'light' => [false],
    'dark' => [true],
]);

/** A browser script that says which of its four faces Humi shows in `$where`, by its mouth. */
function humiFaceIn(string $where): string
{
    return "() => { const mouths = { 'M140 234 Q160 252 180 234': 'hola', 'M148 238 H172': 'escucha', 'M146 240 Q160 232 178 236': 'calcula', 'M134 228 Q160 258 186 228': 'listo' }; return [...document.querySelectorAll('{$where} path')].map((path) => mouths[path.getAttribute('d')]).find(Boolean) ?? 'ninguna'; }";
}

it('uses its four faces: hola at rest, calcula while it answers, listo when it finishes and beside each answer, then hola again', function (): void {
    visitPage('dashboard', humiPanelCompany())
        ->assertScript(humiServes(humiStream()), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->assertScript(humiFaceIn('header'), 'hola')
        ->assertScript(humiFaceIn('[role="log"] > div:first-child'), 'hola')
        ->click('¿Cómo va la participación?')
        ->assertSee('El riesgo más alto está en')
        ->assertScript(humiFaceIn('header'), 'listo')
        ->assertScript(humiFaceIn('[role="log"] > div:last-child'), 'listo')
        ->assertScript('() => new Promise((done) => setTimeout(() => done(true), 2700))', true)
        ->assertScript(humiFaceIn('header'), 'hola')
        ->assertScript(humiFaceIn('[role="log"] > div:last-child'), 'listo');
});

it('keeps Humi calculating while the answer comes', function (): void {
    $parts = json_encode('data: '.json_encode(['type' => 'start', 'messageId' => 'm1'])."\n\n");
    $stalled = "new Response(new ReadableStream({ start(controller) { controller.enqueue(new TextEncoder().encode({$parts})); } }), { headers: { 'Content-Type': 'text/event-stream' } })";

    visitPage('dashboard', humiPanelCompany())
        ->assertScript(humiAnswersWith($stalled), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->click('¿Cómo va la participación?')
        ->assertSee('Pensando')
        ->assertScript(humiFaceIn('header'), 'calcula')
        ->assertScript(humiFaceIn('[role="log"] [role="status"]'), 'calcula')
        ->assertScript('() => getComputedStyle(document.querySelector(\'[role="log"] [role="status"] .humi-look\')).animationName', 'humi-look');
});

it('keeps Humi still with reduced motion', function (): void {
    visit(humiPanelCompany(), ['reducedMotion' => 'reduce'])
        ->click('[aria-label="Abrir a Humi"]')
        ->assertSee('Hola, soy Humi')
        ->assertScript('() => [...document.querySelectorAll(\'aside[aria-label="Humi"] .humi-bob, aside[aria-label="Humi"] .humi-blink, aside[aria-label="Humi"] .humi-look\')].map((element) => getComputedStyle(element).animationName).filter((name) => name !== "none").length', 0)
        ->assertScript('() => document.querySelectorAll(\'aside[aria-label="Humi"] .humi-bob\').length > 0', true);
});

it('reads as a page of its own: the history by day with a search on the server, a column of 720 px and the box at the bottom', function (): void {
    $panel = humiPanelCompany();
    $participant = ConversationParticipant::query()->withoutGlobalScopes()->sole();
    assistantConversation($participant, 'Explícame la Ley Silla');

    visitPage('assistant/index', $panel.'/assistant')
        ->assertSee('Hoy')
        ->assertSee('Explícame la Ley Silla')
        ->assertPresent('button:has-text("Nueva conversación")')
        // Not a box inside a page: the conversation reaches the bottom of the window, its box there.
        ->assertScript('() => Math.abs(window.innerHeight - document.querySelector(\'textarea\').closest(\'div.border-t\').getBoundingClientRect().bottom) < 24', true)
        ->assertScript('() => Math.round(document.querySelector(\'[role="log"] > div\').getBoundingClientRect().width) <= 720', true)
        ->assertScript('() => document.querySelector(\'[role="log"]\').getBoundingClientRect().width > 720', true)
        ->type('[aria-label="Buscar conversaciones"]', 'silla')
        ->assertQueryStringHas('q', 'silla')
        ->assertSee('Explícame la Ley Silla')
        ->assertDontSee('Avance de mis campañas')
        ->type('[aria-label="Buscar conversaciones"]', 'nada parecido')
        ->assertSee('Ninguna conversación tiene «nada parecido».')
        ->assertNoJavaScriptErrors();
});

it('takes the whole screen of a phone, with buttons of 44 px and the questions to follow in one row', function (): void {
    visitPage('dashboard', humiPanelCompany(), mobile: true)
        ->assertScript(humiServes(humiStream()), 'ready')
        ->click('[aria-label="Abrir a Humi"]')
        ->assertScript('() => { const sheet = document.querySelector(\'[role="dialog"]\').getBoundingClientRect(); return sheet.width === window.innerWidth && sheet.height === window.innerHeight; }', true)
        ->click('¿Cómo va la participación?')
        ->assertSee('El riesgo más alto está en')
        ->assertScript(humiSettled(), true)
        ->assertScript('() => [...document.querySelectorAll(\'[role="dialog"] button, [role="dialog"] a\')].filter((control) => control.offsetParent !== null && control.getBoundingClientRect().height < 44 && control.getAttribute("aria-label") !== "Quitar contexto").map((control) => control.getAttribute("aria-label") ?? control.innerText).join(" | ")', '')
        ->assertScript('() => new Set([...document.querySelectorAll(\'[role="log"] button\')].filter((button) => button.innerText.startsWith("¿")).map((button) => button.getBoundingClientRect().top)).size', 1);
});
