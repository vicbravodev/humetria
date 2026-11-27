<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Seeders\Demo\DemoRoster;

/*
 * The dock of the guide: on top of every page of the demo company while the superadmin presents a session, it says what
 * to do, what is going on and why it matters, and moves on when the real fact happens (its poll, every 3 s).
 */

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

const GUIDE_DOCK = 'section[aria-label="Guía de la demo"]';

/**
 * A session of the NOM-035 tour on its center with its people already added and the evaluation drafted: the guide is on
 * «Lanza la evaluación». The superadmin presents it as the demo's owner.
 */
function presentedEvaluation(): Campaign
{
    $demo = Tenant::factory()->demo()->create(['slug' => 'alba-demo']);
    memberOf($demo, TenantRole::Owner);
    $center = WorkCenter::factory()->create(['tenant_id' => $demo->id, 'name' => 'Sala · Grupo Ejemplo']);
    Employee::factory()->count(2)->create(['tenant_id' => $demo->id, 'work_center_id' => $center->id]);
    $campaign = Campaign::factory()->create(['tenant_id' => $demo->id, 'work_center_id' => $center->id]);
    DemoSession::query()->forceCreate([
        'tours' => ['nom035'],
        'status' => DemoSessionStatus::Active,
        'work_center_id' => $center->id,
        'visited' => ['tenant.work-centers.index'],
        'started_at' => now()->subMinute(),
        'ready_at' => now()->subMinute(),
    ]);
    test()->actingAs(superadmin())->post(route('companies.impersonate', $demo), ['as' => 'owner']);

    return $campaign;
}

/** A script that resolves once the page shows `$text` (or not, with `$shown` false), waiting up to 8 s for the poll. */
function guideShows(string $text, bool $shown = true): string
{
    $wanted = json_encode($text, JSON_THROW_ON_ERROR);
    $expect = $shown ? 'true' : 'false';

    return <<<JS
        () => new Promise((resolve) => {
            const start = performance.now();
            const check = () => {
                const seen = document.body.innerText.includes({$wanted}) === {$expect};
                seen || performance.now() - start > 8000 ? resolve(seen) : setTimeout(check, 100);
            };
            check();
        })
        JS;
}

it('shows the guide to the superadmin in the demo session and moves on when the evaluation launches', function (): void {
    $campaign = presentedEvaluation();

    $page = visitPage('campaigns/show', "/admin/alba-demo/campaigns/{$campaign->id}")
        ->assertSeeIn(GUIDE_DOCK, 'Paso 2 de 6 · NOM-035')
        ->assertSeeIn(GUIDE_DOCK, 'Lanza la evaluación')
        ->assertSeeIn(GUIDE_DOCK, 'Hazlo')
        ->assertSeeIn(GUIDE_DOCK, 'Revisa la evaluación y lánzala.')
        ->assertSeeIn(GUIDE_DOCK, 'Cada persona recibe su invitación por correo y WhatsApp')
        ->assertSeeIn(GUIDE_DOCK, 'Llévame')
        ->assertSeeIn(GUIDE_DOCK, 'Saltar paso')
        ->assertSee('Guía · 4 de 8')
        // The step's control is lit on its page.
        ->assertPresent('[data-tour="launch-evaluation"][data-guide-spotlight]');

    $campaign->forceFill(['status' => CampaignStatus::Active, 'launched_at' => now(), 'closes_on' => now()->addWeek()->toDateString()])->save();

    $page->assertScript(guideShows('El público contesta'), true)
        ->assertSeeIn(GUIDE_DOCK, 'Listo: «Lanza la evaluación».')
        ->assertSeeIn(GUIDE_DOCK, 'Mira')
        ->assertNotPresent('[data-tour="launch-evaluation"][data-guide-spotlight]')
        ->assertNoJavaScriptErrors();
});

it('hides the guide when the session ends in another tab', function (): void {
    $campaign = presentedEvaluation();

    $page = visit("/admin/alba-demo/campaigns/{$campaign->id}")->assertSeeIn(GUIDE_DOCK, 'Lanza la evaluación');

    DemoSession::query()->firstOrFail()->forceFill(['status' => DemoSessionStatus::Ended, 'ended_at' => now()])->save();

    $page->assertScript(guideShows('Lanza la evaluación', shown: false), true)
        ->assertNotPresent(GUIDE_DOCK)
        ->assertNotPresent('[data-guide-spotlight]')
        ->assertNoJavaScriptErrors();
});

it('shows the guide in light and dark, on a computer and as a sheet on a phone', function (bool $dark, bool $mobile): void {
    $campaign = presentedEvaluation();

    visitPage('campaigns/show', "/admin/alba-demo/campaigns/{$campaign->id}", $dark, $mobile)
        ->assertSeeIn(GUIDE_DOCK, 'Lanza la evaluación')
        ->assertSeeIn(GUIDE_DOCK, 'Por qué importa')
        ->assertScript($mobile
            ? '() => document.querySelector(\'section[aria-label="Guía de la demo"]\').getBoundingClientRect().width === window.innerWidth'
            : '() => document.querySelector(\'section[aria-label="Guía de la demo"]\').getBoundingClientRect().width === 380', true);
})->with($looks);

it('folds into its pill and opens again', function (): void {
    $campaign = presentedEvaluation();

    visit("/admin/alba-demo/campaigns/{$campaign->id}")
        ->click('[aria-label="Minimizar la guía"]')
        ->assertNotPresent(GUIDE_DOCK)
        ->click('[aria-controls="demo-guide"]')
        ->assertSeeIn(GUIDE_DOCK, 'Lanza la evaluación')
        ->assertNoJavaScriptErrors();
});

it('steps aside to its pill while a dialog is open, and celebrates the launch done there', function (): void {
    $campaign = presentedEvaluation();

    visit("/admin/alba-demo/campaigns/{$campaign->id}")
        ->assertSeeIn(GUIDE_DOCK, 'Lanza la evaluación')
        ->click('Lanzar e invitar a 2 personas')
        ->assertNotPresent(GUIDE_DOCK)
        ->assertSee('Lanza la evaluación')
        // The page that comes back after the launch spends the step just done: the dock celebrates it from the page itself.
        ->click('[data-slot="dialog-content"] button[type="submit"]')
        ->assertScript(guideShows('El público contesta'), true)
        ->assertSeeIn(GUIDE_DOCK, 'Listo: «Lanza la evaluación».')
        ->assertNoJavaScriptErrors();
});

it('takes the presenter to the step page and moves on when the page is all the step waits for', function (): void {
    presentedEvaluation();
    DemoSession::query()->firstOrFail()->forceFill(['visited' => []])->save();

    visit('/admin/alba-demo')
        ->assertSeeIn(GUIDE_DOCK, 'El centro de la sesión')
        ->click('Llévame')
        ->assertScript(guideShows('Paso 2 de 6 · NOM-035'), true)
        ->assertPathIs('/admin/alba-demo/work-centers')
        ->assertSeeIn(GUIDE_DOCK, 'Listo: «El centro de la sesión».')
        ->assertNoJavaScriptErrors();
});

it('opens Humi with the step question written, beside the guide', function (): void {
    presentedEvaluation();
    DemoSession::query()->firstOrFail()->forceFill(['tours' => ['humi']])->save();

    visit('/admin/alba-demo')
        ->assertSeeIn(GUIDE_DOCK, 'Humi abre con la pregunta escrita: «¿Cómo va el cumplimiento de la empresa?»')
        ->click('#demo-guide button:has-text("Pregúntale a Humi")')
        ->assertScript('() => new Promise((resolve) => { const start = performance.now(); const check = () => { const value = document.querySelector(\'aside[aria-label="Humi"] textarea\')?.value; value || performance.now() - start > 8000 ? resolve(value ?? null) : setTimeout(check, 100); }; check(); })', '¿Cómo va el cumplimiento de la empresa?')
        ->assertScript(<<<'JS'
            () => {
                const dock = document.querySelector('section[aria-label="Guía de la demo"]').getBoundingClientRect();
                const humi = document.querySelector('aside[aria-label="Humi"]').getBoundingClientRect();

                return dock.right <= humi.left;
            }
            JS, true)
        ->assertNoJavaScriptErrors();
});

it('skips a step', function (): void {
    $campaign = presentedEvaluation();

    visit("/admin/alba-demo/campaigns/{$campaign->id}")
        ->click('Saltar paso')
        ->assertScript(guideShows('El público contesta'), true)
        ->assertSeeIn(GUIDE_DOCK, 'Paso 3 de 6 · NOM-035')
        ->assertDontSeeIn(GUIDE_DOCK, 'Listo:')
        ->assertNoJavaScriptErrors();

    expect(DemoSession::query()->firstOrFail()->skipped_steps)->toBe(['nom035.launch']);
});

it('rings a control that appears after the page', function (): void {
    $campaign = presentedEvaluation();

    visit("/admin/alba-demo/campaigns/{$campaign->id}")
        ->assertPresent('[data-tour="launch-evaluation"][data-guide-spotlight]')
        // As a tab or a deferred prop would: the control the step lights comes later.
        ->assertScript("() => { document.querySelector('[data-tour=\"launch-evaluation\"]').removeAttribute('data-tour'); const late = document.createElement('button'); late.id = 'late-control'; late.textContent = 'Tarde'; setTimeout(() => { late.setAttribute('data-tour', 'launch-evaluation'); document.querySelector('main, [data-slot=\"page-body\"]').append(late); }, 300); return 'ready'; }", 'ready')
        ->assertScript('() => new Promise((resolve) => { const start = performance.now(); const check = () => { const lit = document.querySelector(\'#late-control\')?.hasAttribute(\'data-guide-spotlight\') === true; lit || performance.now() - start > 3000 ? resolve(lit) : setTimeout(check, 50); }; check(); })', true);
});

it('closes the sheet on a phone when the veil around it is tapped', function (): void {
    $campaign = presentedEvaluation();

    visit("/admin/alba-demo/campaigns/{$campaign->id}")->on()->mobile()
        ->assertSeeIn(GUIDE_DOCK, 'Lanza la evaluación')
        ->assertScript('() => { document.querySelector(\'[data-slot="guide-veil"]\').click(); return true; }', true)
        ->assertNotPresent(GUIDE_DOCK)
        ->assertPresent('[aria-controls="demo-guide"]');
});

it('keeps the guide when a skip is refused, and asks for it again', function (): void {
    $campaign = presentedEvaluation();

    visit("/admin/alba-demo/campaigns/{$campaign->id}")
        ->assertSeeIn(GUIDE_DOCK, 'Lanza la evaluación')
        // The skip answers 404, as when its step stopped being one the session has; the guide's own poll still works.
        ->assertScript("() => { const real = window.fetch.bind(window); window.guidePolls = 0; window.fetch = (input, init) => { const url = String(input instanceof Request ? input.url : input); if (url.includes('/skip')) { return Promise.resolve(new Response('', { status: 404 })); } if (url.endsWith('/session/guide')) { window.guidePolls += 1; } return real(input, init); }; return 'ready'; }", 'ready')
        ->click('Saltar paso')
        ->assertScript('() => new Promise((resolve) => { const start = performance.now(); const check = () => window.guidePolls > 0 || performance.now() - start > 2500 ? resolve(window.guidePolls > 0) : setTimeout(check, 50); check(); })', true)
        ->assertSeeIn(GUIDE_DOCK, 'Lanza la evaluación')
        ->assertNoJavaScriptErrors();
});

it('leaves the owner first steps aside while the guide shows', function (): void {
    config(['first_steps.enabled' => true]);
    $campaign = presentedEvaluation();

    visit("/admin/alba-demo/campaigns/{$campaign->id}")
        ->assertSeeIn(GUIDE_DOCK, 'Lanza la evaluación')
        ->assertNotPresent('[aria-label^="Primeros pasos"]');
});

it('opens the portal of a person of the session on this computer, for the Vacaciones tour', function (): void {
    $demo = Tenant::factory()->demo()->create(['slug' => 'alba-demo']);
    memberOf($demo, TenantRole::Owner);
    User::factory()->create(['email' => DemoRoster::person(DemoRoster::HR)->email]);
    $center = WorkCenter::factory()->create(['tenant_id' => $demo->id]);
    Employee::factory()->create(['tenant_id' => $demo->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz']);
    DemoSession::query()->forceCreate([
        'tours' => ['leave'],
        'status' => DemoSessionStatus::Active,
        'work_center_id' => $center->id,
        'visited' => ['tenant.work-centers.index'],
        'started_at' => now()->subMinute(),
        'ready_at' => now()->subMinute(),
    ]);
    test()->actingAs(superadmin())->post(route('companies.impersonate', $demo), ['as' => 'owner']);

    visit('/admin/alba-demo')
        ->assertSeeIn(GUIDE_DOCK, 'Entra al portal y crea la llave')
        ->click('#demo-portal-person')
        ->type('[role="combobox"][aria-controls="demo-portal-person-options"]', 'ana')
        ->assertSee('Ana Ruiz')
        ->keys('[role="combobox"][aria-controls="demo-portal-person-options"]', 'Enter')
        ->click('Abrir su portal')
        ->assertScript('() => new Promise((resolve) => { const start = performance.now(); const check = () => location.pathname.startsWith("/me/alba-demo/") || performance.now() - start > 8000 ? resolve(location.pathname.startsWith("/me/alba-demo/")) : setTimeout(check, 100); check(); })', true)
        ->assertNoJavaScriptErrors();
});

it('says when the session is over and takes the presenter back to the room', function (): void {
    $demo = Tenant::factory()->demo()->create(['slug' => 'alba-demo']);
    memberOf($demo, TenantRole::Owner);
    DemoSession::query()->forceCreate([
        'tours' => [],
        'status' => DemoSessionStatus::Active,
        'work_center_id' => WorkCenter::factory()->create(['tenant_id' => $demo->id])->id,
        'skipped_steps' => ['base.center', 'base.people'],
        'started_at' => now()->subMinute(),
        'ready_at' => now()->subMinute(),
    ]);
    test()->actingAs(superadmin())->post(route('companies.impersonate', $demo), ['as' => 'owner']);

    visit('/admin/alba-demo')
        ->assertSeeIn(GUIDE_DOCK, 'Terminaste el recorrido de esta sesión.')
        ->assertSee('Guía · 2 de 2')
        ->click('Volver a la sala')
        ->assertPathIs('/admin/demo')
        ->assertSee('Sala de demostración')
        ->assertNoJavaScriptErrors();
});
