<?php

declare(strict_types=1);

use App\Http\Impersonation;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The guide of a demo session, on the server: only the superadmin working in the demo company (as its owner or its HR,
 * through impersonation) with a session under way gets it, in every panel page (the shared prop `demoGuide`) and from
 * the poll of the dock. The current step is the first one neither done (by the real data) nor skipped.
 */

beforeEach(function (): void {
    $this->demo = Tenant::factory()->demo()->create();
    $this->owner = memberOf($this->demo, TenantRole::Owner);
    $this->hr = memberOf($this->demo, TenantRole::Hr);
    $this->center = WorkCenter::factory()->create(['tenant_id' => $this->demo->id]);
});

/** @param  list<string>  $tours */
function guidedSession(array $tours = ['nom035'], DemoSessionStatus $status = DemoSessionStatus::Active): DemoSession
{
    return DemoSession::query()->forceCreate([
        'tours' => $tours,
        'status' => $status,
        'work_center_id' => test()->center->id,
        'started_at' => now()->subMinute(),
        'ready_at' => now()->subMinute(),
    ]);
}

/** The superadmin enters `$tenant` (the demo by default) through the console, as its owner or as its HR. */
function presentAs(string $as = 'owner', ?Tenant $tenant = null): void
{
    test()->actingAs(superadmin())->post(route('companies.impersonate', $tenant ?? test()->demo), ['as' => $as])->assertRedirect();
}

/** The guide a panel page of the demo shares. */
function sharedGuide(string $route = 'tenant.dashboard'): mixed
{
    $page = test()->get(route($route, test()->demo))->assertOk()->viewData('page');

    return is_array($page) && is_array($page['props']) ? $page['props']['demoGuide'] : 'no page';
}

function polledGuide(): TestResponse
{
    return test()->getJson(route('demo-room.session.guide'));
}

it('polls the guide at /admin/demo/session/guide', function (): void {
    expect(route('demo-room.session.guide', absolute: false))->toBe('/admin/demo/session/guide');
});

it('shares no guide outside the demo or without a session', function (): void {
    presentAs();

    expect(sharedGuide())->toBeNull();
    expect(polledGuide()->getContent())->toBe('null');

    foreach ([DemoSessionStatus::Preparing, DemoSessionStatus::Ending] as $status) {
        DemoSession::query()->delete();
        guidedSession(status: $status);

        expect(sharedGuide())->toBeNull()
            ->and(polledGuide()->assertOk()->getContent())->toBe('null');
    }
});

it('shares no guide in a real company, even with a session under way', function (): void {
    guidedSession();
    $company = Tenant::factory()->create();
    ownerOf($company);
    presentAs(tenant: $company);

    $this->get(route('tenant.dashboard', $company))->assertInertia(fn (Assert $page) => $page->where('demoGuide', null));
    polledGuide()->assertNotFound();
});

it('shares the guide to the superadmin as owner and as HR', function (string $as): void {
    guidedSession(['nom035']);
    presentAs($as);

    $guide = sharedGuide();

    expect($guide)->toMatchArray([
        'tours' => [
            ['key' => 'base', 'title' => 'El centro y su gente', 'done' => 0, 'total' => 2],
            ['key' => 'nom035', 'title' => 'NOM-035', 'done' => 0, 'total' => 6],
        ],
        'finished' => false,
        'justDone' => null,
    ])
        ->and($guide['current'])->toBe([
            'key' => 'base.center',
            'tour' => 'El centro y su gente',
            'title' => 'El centro de la sesión',
            'todo' => 'Abre los centros de trabajo y ubica el de esta sesión.',
            'why' => 'Todo en la plataforma parte del centro de trabajo: ahí se aplican la NOM-035, la asistencia y lo demás.',
            'kind' => 'do',
            'href' => route('tenant.work-centers.index', $this->demo),
            'target' => 'work-centers-list',
            'ask' => null,
            'live' => null,
            'index' => 1,
            'total' => 2,
        ])
        ->and(polledGuide()->assertOk()->json())->toBe($guide);
})->with(['owner', 'hr']);

it('never shares the guide to the demo HR without impersonation, nor to support', function (): void {
    guidedSession();
    $this->actingAs($this->hr);

    expect(sharedGuide())->toBeNull();
    polledGuide()->assertNotFound();

    auth()->logout();
    $this->actingAs(support())->post(route('companies.impersonate', $this->demo))->assertRedirect();

    expect(sharedGuide())->toBeNull();
    polledGuide()->assertNotFound();
});

it('answers the poll 404 to whoever is not presenting, and sends guests to the login', function (): void {
    guidedSession();

    $this->getJson(route('demo-room.session.guide'))->assertUnauthorized();
    $this->actingAs(superadmin())->getJson(route('demo-room.session.guide'))->assertNotFound();
});

it('moves to the next step when the fact happens', function (): void {
    guidedSession(['nom035']);
    presentAs();

    expect(sharedGuide('tenant.work-centers.index')['current'])->toMatchArray(['key' => 'base.people', 'href' => route('tenant.employees.index', $this->demo)]);

    Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);

    expect(polledGuide()->json('current'))->toMatchArray(['key' => 'nom035.draft', 'tour' => 'NOM-035', 'index' => 1, 'total' => 6, 'href' => route('tenant.campaigns.index', $this->demo)])
        ->and(polledGuide()->json('tours.0'))->toBe(['key' => 'base', 'title' => 'El centro y su gente', 'done' => 2, 'total' => 2]);

    $campaign = Campaign::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);

    // The evaluation's own page: «Llévame» opens the draft the presenter just created.
    expect(polledGuide()->json('current'))->toMatchArray(['key' => 'nom035.launch', 'index' => 2, 'href' => route('tenant.campaigns.show', [$this->demo, $campaign])]);
});

it('takes the presenter to the session center for the attendance steps', function (): void {
    guidedSession(['attendance']);
    presentAs();
    Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);
    DemoSession::query()->sole()->recordVisit('tenant.work-centers.index');

    expect(polledGuide()->json('current'))->toMatchArray(['key' => 'attendance.mode', 'href' => route('tenant.attendance.center', [$this->demo, $this->center])]);
});

it('records the pages a step waits for only on a full visit, never from the poll nor a partial reload', function (): void {
    $session = guidedSession(['nom035']);
    presentAs();

    polledGuide()->assertOk();
    $this->get(route('tenant.work-centers.index', $this->demo), [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'tenancy/work-centers/index',
        'X-Inertia-Partial-Data' => 'demoGuide',
    ]);

    expect($session->refresh()->visited)->toBe([]);

    // A page no step waits for is not kept.
    sharedGuide('tenant.employees.index');

    expect($session->refresh()->visited)->toBe([]);

    sharedGuide('tenant.work-centers.index');

    expect($session->refresh()->visited)->toBe(['tenant.work-centers.index']);
});

it('records nothing of a real company nor of a session not under way', function (): void {
    $session = guidedSession(status: DemoSessionStatus::Preparing);
    presentAs();

    sharedGuide('tenant.work-centers.index');

    expect($session->refresh()->visited)->toBe([]);
});

it('polls with a fixed number of queries', function (): void {
    guidedSession(['nom035', 'attendance', 'engagement', 'complaints', 'leave', 'humi']);
    presentAs();
    polledGuide()->assertOk();

    // The request's own (the account and its roles, the one behind it and theirs, the demo, the session), the company and
    // one read per fact: 7 + 1 + 13.
    $this->expectsDatabaseQueryCount(21);

    polledGuide()->assertOk();
});

it('carries no person of the demo, only counts', function (): void {
    guidedSession(['nom035']);
    presentAs();
    Employee::factory()->create([
        'tenant_id' => $this->demo->id,
        'work_center_id' => $this->center->id,
        'name' => 'Rosalinda Persona',
        'email' => 'rosalinda@example.com',
    ]);
    $this->get(route('tenant.work-centers.index', $this->demo));

    $this->get(route('tenant.dashboard', $this->demo))->assertInertia(function (Assert $page): void {
        $guide = json_encode($page->toArray()['props']['demoGuide'], JSON_THROW_ON_ERROR);

        expect($guide)->not->toContain('Rosalinda')
            ->not->toContain('rosalinda@example.com')
            ->not->toContain($this->owner->name)
            ->not->toContain($this->owner->email);
    });

    expect(json_encode(polledGuide()->json(), JSON_THROW_ON_ERROR))->not->toContain('Rosalinda')
        ->and(session(Impersonation::SESSION_KEY))->not->toBeNull();
});

it('skips and rewinds a step', function (): void {
    $session = guidedSession(['nom035']);
    presentAs();
    Log::spy();

    $this->postJson(route('demo-room.session.skip', 'base.center'))->assertOk()->assertJsonPath('current.key', 'base.people');

    expect($session->refresh()->skipped_steps)->toBe(['base.center'])
        ->and(polledGuide()->json('tours.0.done'))->toBe(1);
    Log::shouldHaveReceived('info')->once()->with('demo.session_step_skipped', ['session_id' => $session->id, 'step' => 'base.center']);

    // Skipping the evaluation's draft leaves its next steps without an evaluation to open: «Llévame» goes to the list.
    Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);
    $this->postJson(route('demo-room.session.skip', 'nom035.draft'))->assertOk()
        ->assertJsonPath('current.key', 'nom035.launch')
        ->assertJsonPath('current.href', route('tenant.campaigns.index', $this->demo));

    // Rewinding takes the skip away, and the visit that did the step: the presenter goes through it again.
    $this->postJson(route('demo-room.session.rewind', 'nom035.draft'))->assertOk()->assertJsonPath('current.key', 'nom035.draft');
    $this->postJson(route('demo-room.session.rewind', 'base.center'))->assertOk()->assertJsonPath('current.key', 'base.center');
    sharedGuide('tenant.work-centers.index');

    expect($session->refresh()->visited)->toBe(['tenant.work-centers.index']);

    $this->postJson(route('demo-room.session.rewind', 'base.center'))->assertOk()->assertJsonPath('current.key', 'base.center');

    expect($session->refresh())->skipped_steps->toBe([])->visited->toBe([]);
    Log::shouldHaveReceived('info')->with('demo.session_step_rewound', ['session_id' => $session->id, 'step' => 'base.center']);
});

it('skips each step once', function (): void {
    $session = guidedSession(['nom035']);
    presentAs();

    $this->postJson(route('demo-room.session.skip', 'nom035.answers'))->assertOk();
    $this->postJson(route('demo-room.session.skip', 'nom035.answers'))->assertOk();

    expect($session->refresh()->skipped_steps)->toBe(['nom035.answers']);
});

it('skips nor rewinds a step the session does not have, nor without a session under way', function (string $route): void {
    $session = guidedSession(['nom035']);
    presentAs();

    $this->postJson(route($route, 'leave.key'))->assertNotFound();
    $this->postJson(route($route, 'nom035.nothing'))->assertNotFound();

    $session->forceFill(['status' => DemoSessionStatus::Ending])->save();

    $this->postJson(route($route, 'nom035.draft'))->assertNotFound();
    expect($session->refresh()->skipped_steps)->toBe([]);
})->with(['demo-room.session.skip', 'demo-room.session.rewind']);

it('lets only the presenter skip or rewind', function (string $route): void {
    $session = guidedSession(['nom035']);

    $this->actingAs($this->owner)->postJson(route($route, 'base.center'))->assertNotFound();
    $this->actingAs(superadmin())->postJson(route($route, 'base.center'))->assertNotFound();

    expect($session->refresh()->skipped_steps)->toBe([]);
})->with(['demo-room.session.skip', 'demo-room.session.rewind']);

it('says finished when every step is done or skipped', function (): void {
    guidedSession(['humi']);
    presentAs();
    sharedGuide('tenant.work-centers.index');
    $this->postJson(route('demo-room.session.skip', 'base.people'))->assertOk()->assertJsonPath('finished', false);

    $this->postJson(route('demo-room.session.skip', 'humi.ask'))->assertOk()->assertJson([
        'tours' => [
            ['key' => 'base', 'done' => 2, 'total' => 2],
            ['key' => 'humi', 'title' => 'Humi', 'done' => 1, 'total' => 1],
        ],
        'current' => null,
        'finished' => true,
    ]);
});

it('reports the step just done once', function (): void {
    $session = guidedSession(['nom035']);
    presentAs();

    expect(polledGuide()->json('justDone'))->toBeNull();

    expect(sharedGuide('tenant.work-centers.index'))->toMatchArray(['justDone' => 'El centro de la sesión'])
        ->and(polledGuide()->json('justDone'))->toBeNull()
        ->and(sharedGuide())->toMatchArray(['justDone' => null]);

    // A skipped step was not done: nothing to celebrate.
    $this->postJson(route('demo-room.session.skip', 'base.people'))->assertOk()->assertJsonPath('justDone', null);

    Campaign::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);

    expect(polledGuide()->json())->toMatchArray(['justDone' => 'Crea la evaluación'])
        ->and(polledGuide()->json('justDone'))->toBeNull()
        ->and($session->refresh()->guide_step)->toBe('nom035.launch');
});

it('reports the last step done when the session finishes', function (): void {
    guidedSession(['humi']);
    presentAs();
    $this->postJson(route('demo-room.session.skip', 'base.center'));
    $this->postJson(route('demo-room.session.skip', 'base.people'));
    $participant = App\Modules\Assistant\Models\ConversationParticipant::factory()->for($this->owner)->create(['tenant_id' => $this->demo->id]);
    App\Modules\Assistant\Models\AiUsage::factory()->create(['tenant_id' => $this->demo->id, 'participant_id' => $participant->id]);

    expect(polledGuide()->json())->toMatchArray(['finished' => true, 'justDone' => 'Pregúntale a Humi'])
        ->and(polledGuide()->json('justDone'))->toBeNull();
});
