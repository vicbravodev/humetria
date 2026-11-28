<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Demos\Support\SessionCenter;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Enums\LinkKind;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Database\Seeders\Demo\DemoRoster;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/*
 * The Vacaciones tour, on the presenter's own computer: the guide opens the portal of one person of the session's center
 * with a one-use link, issued as the demo's head of HR and never sent anywhere; the presenter then creates the key there.
 */

mutates(SessionCenter::class);

beforeEach(function (): void {
    $this->demo = Tenant::factory()->demo()->create();
    $this->owner = memberOf($this->demo, TenantRole::Owner);
    memberOf($this->demo, TenantRole::Hr);
    $this->hrHead = User::factory()->create(['email' => DemoRoster::person(DemoRoster::HR)->email]);
    $this->center = WorkCenter::factory()->create(['tenant_id' => $this->demo->id]);
    $this->person = Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
});

function portalSession(DemoSessionStatus $status = DemoSessionStatus::Active): DemoSession
{
    return DemoSession::query()->forceCreate([
        'tours' => ['leave'],
        'status' => $status,
        'work_center_id' => test()->center->id,
        'started_at' => now()->subMinute(),
        'ready_at' => now()->subMinute(),
    ]);
}

function presentPortalAs(string $as = 'hr', ?Tenant $tenant = null): void
{
    test()->actingAs(superadmin())->post(route('companies.impersonate', $tenant ?? test()->demo), ['as' => $as])->assertRedirect();
}

it('opens the portal at /admin/demo/session/portal/{employee}', function (): void {
    expect(route('demo-room.session.portal', 'x', absolute: false))->toBe('/admin/demo/session/portal/x')
        ->and(route('demo-room.session.people.options', absolute: false))->toBe('/admin/demo/session/people/options');
});

it('opens the portal of a session person on this computer', function (string $as): void {
    portalSession();
    presentPortalAs($as);
    Notification::fake();
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message.json_encode($log->context);
    });

    $response = $this->post(route('demo-room.session.portal', $this->person->id), [], ['X-Inertia' => 'true'])->assertStatus(409);
    $url = (string) $response->headers->get('X-Inertia-Location');
    $link = WorkerAccessLink::query()->sole();

    expect($link)->employee_id->toBe($this->person->id)->kind->toBe(LinkKind::Issued)->issued_by->toBe($this->hrHead->id)
        ->and($url)->toStartWith(rtrim(config()->string('app.url'), '/').'/me/'.$this->demo->slug.'/link/');
    Notification::assertNothingSent();
    // The demo's trail says who issued it, with the superadmin behind; never the address.
    expect(AuditEntry::query()->where('event', 'demo.portal_link_issued')->sole())
        ->tenant_id->toBe($this->demo->id)
        ->subject_id->toBe($link->id)
        ->impersonator_id->not->toBeNull()
        ->metadata->toBe(['employee_id' => $this->person->id]);

    // The link opens on this computer: «Continuar» spends it and leads to creating the key.
    $this->get($url)->assertOk();
    $this->post($url)->assertRedirect(route('worker.key.show', $this->demo->slug));

    // Opening the person's session leaves the impersonation alone: back in the panel, the presenter still has the guide.
    $this->get(route('tenant.dashboard', $this->demo))->assertOk()->assertInertia(fn ($page) => $page->has('demoGuide.current')->whereNot('impersonator', null));

    // The address is the credential: it is never logged.
    expect($logged)->toContain('worker_access.link_created'.json_encode(['link_id' => $link->id, 'employee_id' => $this->person->id, 'kind' => 'issued']))
        ->and(implode("\n", $logged))->not->toContain(basename($url));
})->with(['hr', 'owner']);

it('answers a request outside Inertia with a plain redirect, and a new press with a new link', function (): void {
    portalSession();
    presentPortalAs();

    $first = $this->post(route('demo-room.session.portal', $this->person->id))->assertRedirect()->headers->get('Location');
    $second = $this->post(route('demo-room.session.portal', $this->person->id))->assertRedirect()->headers->get('Location');

    // The link before dies: a one-use link is never good twice.
    expect($second)->not->toBe($first);
    $this->post((string) $first)->assertRedirect((string) $first);
    $this->post((string) $second)->assertRedirect(route('worker.key.show', $this->demo->slug));
});

it('refuses a person outside the session center', function (): void {
    portalSession();
    presentPortalAs();
    $elsewhere = Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => WorkCenter::factory()->create(['tenant_id' => $this->demo->id])->id]);
    $gone = Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id, 'deactivated_at' => now()]);
    $other = Employee::factory()->create();

    foreach ([$elsewhere->id, $gone->id, $other->id, '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c'] as $id) {
        $this->post(route('demo-room.session.portal', $id))->assertNotFound();
    }

    $this->post(route('demo-room.session.portal', 'not-a-uuid'))->assertNotFound();
    expect(WorkerAccessLink::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('refuses outside an active session', function (): void {
    presentPortalAs();

    $this->post(route('demo-room.session.portal', $this->person->id))->assertNotFound();

    foreach ([DemoSessionStatus::Preparing, DemoSessionStatus::Ending, DemoSessionStatus::Ended] as $status) {
        DemoSession::query()->delete();
        portalSession($status);

        $this->post(route('demo-room.session.portal', $this->person->id))->assertNotFound();
    }

    expect(WorkerAccessLink::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('lets only the presenter open a portal, never in a real company', function (): void {
    portalSession();

    $this->post(route('demo-room.session.portal', $this->person->id))->assertRedirect(route('login'));
    $this->actingAs(superadmin())->post(route('demo-room.session.portal', $this->person->id))->assertNotFound();
    auth()->logout();
    $this->actingAs($this->owner)->post(route('demo-room.session.portal', $this->person->id))->assertNotFound();
    auth()->logout();

    $company = Tenant::factory()->create();
    ownerOf($company);
    $real = Employee::factory()->create(['tenant_id' => $company->id]);
    presentPortalAs('owner', $company);

    $this->post(route('demo-room.session.portal', $real->id))->assertNotFound();
    expect(WorkerAccessLink::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('offers only the names of the active people of the session center', function (): void {
    portalSession();
    presentPortalAs();
    Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id, 'name' => 'Beatriz Soto']);
    Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Baja', 'deactivated_at' => now()]);
    Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => WorkCenter::factory()->create(['tenant_id' => $this->demo->id])->id, 'name' => 'Ana Lejos']);
    Employee::factory()->create(['name' => 'Ana Ajena']);

    $this->getJson(route('demo-room.session.people.options'))->assertOk()->assertExactJson([
        ['value' => $this->person->id, 'label' => 'Ana Ruiz'],
        ['value' => Employee::query()->where('name', 'Beatriz Soto')->value('id'), 'label' => 'Beatriz Soto'],
    ]);
    $this->getJson(route('demo-room.session.people.options', ['q' => '  ana ']))->assertOk()->assertExactJson([
        ['value' => $this->person->id, 'label' => 'Ana Ruiz'],
    ]);
});

it('offers a few people at a time', function (): void {
    portalSession();
    presentPortalAs();
    Employee::factory()->count(25)->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);

    expect($this->getJson(route('demo-room.session.people.options'))->assertOk()->json())->toHaveCount(20);
});

it('offers nobody outside an active session nor to whoever is not presenting', function (): void {
    $session = portalSession();

    $this->getJson(route('demo-room.session.people.options'))->assertUnauthorized();
    $this->actingAs(superadmin())->getJson(route('demo-room.session.people.options'))->assertNotFound();
    $this->actingAs($this->owner)->getJson(route('demo-room.session.people.options'))->assertNotFound();

    $session->forceFill(['status' => DemoSessionStatus::Ending])->save();
    presentPortalAs();

    $this->getJson(route('demo-room.session.people.options'))->assertNotFound();
});
