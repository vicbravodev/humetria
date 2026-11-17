<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Cache;

/**
 * A company whose person of RH is signed in, with a campaign that closes in three days and has 3 of 4 questionnaires
 * unanswered when `$closing`: something real for the bubble to say on the page of campaigns.
 */
function invitingCompany(bool $closing = true): Tenant
{
    // The facts are kept a minute in the database cache of the browser's server, which outlives a test.
    Cache::store('database')->flush();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    if ($closing) {
        $campaign = Campaign::factory()->active(now()->addDays(3)->toDateString())->create(['tenant_id' => $tenant->id, 'work_center_id' => WorkCenter::factory()->create(['tenant_id' => $tenant->id])->id, 'instruments' => ['nom035.guide-i', 'nom035.guide-iii'], 'workers' => 60]);

        foreach (range(1, 4) as $number) {
            ParticipantInstrument::factory()->create([
                'participant_id' => Participant::factory()->create(['campaign_id' => $campaign->id])->id,
                'instrument' => 'nom035.guide-iii',
                'status' => $number === 1 ? 'completed' : 'pending',
                'completed_at' => $number === 1 ? now() : null,
            ]);
        }
    }

    return $tenant;
}

/**
 * The browser forgets what the person declined and moves its clock `$seconds` ahead of the page's arrival. The bubble
 * counts from when it was drawn: wait for it before moving the clock.
 */
function aheadBy(int $seconds): string
{
    return "() => { if (!window.humiAhead) { localStorage.clear(); sessionStorage.clear(); const real = Date.now.bind(Date); window.humiAhead = 0; Date.now = () => real() + window.humiAhead; } window.humiAhead = {$seconds} * 1000; return 'ahead'; }";
}

function invitationShows(): string
{
    return eventually('() => document.querySelector(\'[data-testid="humi-invitation-timer"]\') !== null');
}

/** Nothing finite moves while axe reads the colors: the countdown is stopped where it is, not ended. */
function countdownStopped(): string
{
    return '() => { document.querySelector(\'[data-testid="humi-invitation-timer"]\').style.animation = "none"; return true; }';
}

it('invites after a minute on the page of campaigns with what is real there, without taking the focus, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    invitingCompany();

    visitPage('campaigns/index', '/admin/acme/campaigns', $dark, $mobile, fn ($page) => $page
        ->assertScript(aheadBy(61), 'ahead')
        ->assertScript(invitationShows(), true)
        ->assertSee('y faltan 3 cuestionarios')
        ->assertSee('¿Te cuento cómo va?')
        ->assertSee('Preguntarle a Humi')
        ->assertSee('Ahora no')
        ->assertSee('No volver a sugerir')
        ->assertScript('() => document.querySelector(\'p[role="status"]\').textContent.includes("¿Te cuento cómo va?")', true)
        ->assertScript('() => document.activeElement === document.body', true)
        ->assertScript('() => getComputedStyle(document.querySelector(\'[data-testid="humi-invitation-timer"]\')).animationDuration', '8s')
        ->assertScript(countdownStopped(), true));
})->with([
    'light' => [false, false],
    'dark' => [true, false],
    'a phone' => [false, true],
]);

it('does not invite on the page of campaigns with nothing real to say', function (): void {
    invitingCompany(closing: false);

    // A window to see that something does not happen: the bubble asks itself every second.
    visit('/admin/acme/campaigns')->assertPresent('[aria-label="Abrir a Humi"]')->assertScript(aheadBy(61), 'ahead')
        ->wait(2)->assertScript('() => document.querySelector(\'[data-testid="humi-invitation-timer"]\') === null', true);
});

it('does not invite on a page that does not invite, nor before a minute', function (): void {
    invitingCompany();

    visit('/admin/acme')->assertPresent('[aria-label="Abrir a Humi"]')->assertScript(aheadBy(61), 'ahead')
        ->wait(2)->assertScript('() => document.querySelector(\'[data-testid="humi-invitation-timer"]\') === null', true);
    visit('/admin/acme/campaigns')->assertPresent('[aria-label="Abrir a Humi"]')->assertScript(aheadBy(30), 'ahead')
        ->wait(2)->assertScript('() => document.querySelector(\'[data-testid="humi-invitation-timer"]\') === null', true);
});

it('opens Humi with the question of the page written and not sent, and does not invite again in the session', function (): void {
    invitingCompany();

    $page = visit('/admin/acme/campaigns')
        ->assertPresent('[aria-label="Abrir a Humi"]')
        ->assertScript(aheadBy(61), 'ahead')
        ->assertScript(invitationShows(), true)
        ->click('Preguntarle a Humi')
        ->assertSee('Hola, soy Humi')
        ->assertScript('() => document.querySelector(\'[aria-label="Pregúntale algo a Humi"]\').value', '¿Cómo va la participación?')
        ->click('[aria-label="Cerrar"]')
        ->assertScript(settled(), true)
        ->assertScript(aheadBy(61 + 11 * 60 + 24 * 3600), 'ahead');

    $page->wait(2)->assertScript('() => document.querySelector(\'[data-testid="humi-invitation-timer"]\') === null', true);
});

it('closes by itself when its bar ends, stops the bar while the focus is on it, and closes with Esc', function (): void {
    invitingCompany();
    $timer = '[data-testid="humi-invitation-timer"]';
    $focus = '() => { document.querySelector(\'[aria-label="Cerrar sugerencia"]\').focus(); return true; }';

    $page = visit('/admin/acme/campaigns')
        ->assertPresent('[aria-label="Abrir a Humi"]')
        ->assertScript(aheadBy(61), 'ahead')
        ->assertScript(invitationShows(), true)
        ->assertScript($focus, true)
        ->assertScript("() => getComputedStyle(document.querySelector('{$timer}')).animationPlayState", 'paused')
        // Its bar ending is what closes it.
        ->assertScript("() => { document.activeElement.blur(); document.querySelector('{$timer}').dispatchEvent(new AnimationEvent('animationend', { bubbles: true })); return true; }", true)
        ->assertScript(eventually("() => document.querySelector('{$timer}') === null"), true);

    // A day later the page may invite again: Esc closes it.
    $page->assertScript(aheadBy(61 + 24 * 3600), 'ahead')
        ->assertScript(invitationShows(), true)
        ->assertScript($focus, true)
        ->keys('[aria-label="Cerrar sugerencia"]', 'Escape')
        ->assertScript(eventually("() => document.querySelector('{$timer}') === null"), true)
        ->assertPresent('[aria-label="Abrir a Humi"]');
});

it('turns the suggestions off with «No volver a sugerir», says where they turn on, and the page of Humi turns them on again', function (): void {
    invitingCompany();

    visit('/admin/acme/campaigns')
        ->assertPresent('[aria-label="Abrir a Humi"]')
        ->assertScript(aheadBy(61), 'ahead')
        ->assertScript(invitationShows(), true)
        ->click('No volver a sugerir')
        ->assertSee('No te sugeriré más.')
        ->assertSee('Sugerencias de la burbuja')
        ->click('la página de Humi')
        ->assertScript(pathBecomes('/admin/acme/assistant'), true)
        ->assertScript('() => document.querySelector(\'#humi-suggestions\').getAttribute("aria-checked")', 'false')
        ->click('#humi-suggestions')
        ->assertScript('() => document.querySelector(\'#humi-suggestions\').getAttribute("aria-checked")', 'true')
        ->assertScript('() => Object.keys(localStorage).filter((key) => key.startsWith("humi.suggestions.")).map((key) => JSON.parse(localStorage.getItem(key)).off).join()', 'false');
});
