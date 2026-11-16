<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Http\Controllers\AccessController;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Notifications\WorkerInvitation;
use App\Modules\WorkerAccess\Support\AccessPages;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

mutates(AccessController::class, AccessPages::class);

/** The queries of the page «Accesos», whatever the number of people or keys. */
const ACCESS_PAGE_QUERIES = 19;

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->office = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
    $this->ana = accessPerson($this->plant, 'Ana López', 'Operadora', 'ana@acme.test');
    $this->bob = accessPerson($this->plant, 'Bob Díaz', 'Almacenista', null);
    $this->carla = accessPerson($this->office, 'Carla Ruiz', 'Analista', 'carla@acme.test');
    EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ex Empleado']);
    // Ana: her phone (a key of one device) and an old one revoked. Carla: a synced key of her account, revoked.
    $this->phone = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone de Ana', 'registered_at' => CarbonImmutable::parse('2026-11-02 15:00:00', 'UTC'), 'last_used_at' => CarbonImmutable::parse('2026-11-10 14:05:00', 'UTC')]);
    $this->old = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'Moto viejo', 'registered_at' => CarbonImmutable::parse('2026-10-01 15:00:00', 'UTC'), 'revoked_at' => CarbonImmutable::parse('2026-11-01 15:00:00', 'UTC')]);
    $this->synced = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->carla->id, 'label' => 'Pixel', 'backup_eligible' => true, 'backup_state' => true, 'registered_at' => CarbonImmutable::parse('2026-10-20 15:00:00', 'UTC'), 'revoked_at' => CarbonImmutable::parse('2026-11-05 15:00:00', 'UTC')]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    Notification::fake();
});

function accessPerson(WorkCenter $center, string $name, string $jobTitle, ?string $email): Employee
{
    return EmployeeFactory::new()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => $name, 'job_title' => $jobTitle, 'email' => $email]);
}

function accessUrl(array $query = []): string
{
    return route('tenant.worker-access.index', ['acme', ...$query]);
}

/** A link RH issued to `$person`, sent at `$created` and open until `$expires` (UTC). */
function issuedLink(Employee $person, string $created, string $expires, array $state = []): WorkerAccessLink
{
    $link = WorkerAccessLink::factory()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'expires_at' => CarbonImmutable::parse($expires, 'UTC'), ...$state]);
    $link->forceFill(['created_at' => CarbonImmutable::parse($created, 'UTC')])->save();

    return $link;
}

describe('the list', function (): void {
    it('lives at /admin/{tenant}/access, for every company whatever its tools', function (): void {
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
        $this->actingAs($this->hr);

        expect(accessUrl())->toEndWith('/admin/acme/access');
        $this->get(accessUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('worker-access/access'));
    });

    it('tells RH how long a link lasts, from config/worker_access.php', function (): void {
        $this->actingAs($this->hr);

        $this->get(accessUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('linkHours', 48));
        config(['worker_access.links.issued_hours' => 24]);
        $this->get(accessUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('linkHours', 24));
    });

    it('shows RH each person with their keys, active first, and whether the key is synced, never a credential', function (): void {
        $this->actingAs($this->hr);

        $response = $this->get(accessUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('worker-access/access')
            ->where('center', null)
            ->where('filters', ['q' => '', 'status' => 'all'])
            ->where('counts', ['all' => 3, 'with_key' => 1, 'invited' => 0, 'not_invited' => 2])
            ->where('canManage', true)
            ->where('people.total', 3)
            ->where('people.per_page', 25)
            ->where('people.data.0', [
                'id' => $this->ana->id, 'name' => 'Ana López', 'jobTitle' => 'Operadora', 'center' => 'Planta Norte', 'hasEmail' => true, 'status' => 'with_key', 'link' => null,
                'keys' => [
                    ['id' => $this->phone->id, 'label' => 'iPhone de Ana', 'synced' => false, 'registeredAt' => '2026-11-02 09:00', 'lastUsedAt' => '2026-11-10 08:05', 'revokedAt' => null],
                    ['id' => $this->old->id, 'label' => 'Moto viejo', 'synced' => false, 'registeredAt' => '2026-10-01 09:00', 'lastUsedAt' => null, 'revokedAt' => '2026-11-01 09:00'],
                ],
            ])
            ->where('people.data.1', [
                'id' => $this->bob->id, 'name' => 'Bob Díaz', 'jobTitle' => 'Almacenista', 'center' => 'Planta Norte', 'hasEmail' => false, 'status' => 'not_invited', 'link' => null, 'keys' => [],
            ])
            ->where('people.data.2.status', 'not_invited')
            ->where('people.data.2.keys.0.synced', true));

        expect($response->getContent())->not->toContain($this->phone->credential_id)->not->toContain($this->phone->public_key)
            ->not->toContain('credential_id')->not->toContain('public_key')->not->toContain('publicKey')
            ->not->toContain('ana@acme.test');
    });

    it('says where each person stands before their key: no link, a link sent and when it ends, or a link that expired', function (): void {
        // Bob's was sent an hour ago and lasts until Friday 13 at 17:00 UTC; Carla's expired yesterday.
        issuedLink($this->bob, '2026-11-11 17:00:00', '2026-11-13 17:00:00');
        issuedLink($this->carla, '2026-11-08 17:00:00', '2026-11-10 17:00:00');
        $this->actingAs($this->hr);

        $this->get(accessUrl())->assertInertia(fn (AssertableInertia $page) => $page
            ->where('counts', ['all' => 3, 'with_key' => 1, 'invited' => 1, 'not_invited' => 1])
            ->where('people.data.0.link', null)
            ->where('people.data.1.status', 'invited')
            ->where('people.data.1.link', ['state' => 'sent', 'sentAt' => '2026-11-11 11:00', 'hoursLeft' => 47])
            ->where('people.data.2.status', 'not_invited')
            ->where('people.data.2.link', ['state' => 'expired', 'sentAt' => '2026-11-08 11:00', 'hoursLeft' => null]));
    });

    it('does not count a link used, revoked or asked for by the person as an invitation waiting', function (): void {
        issuedLink($this->bob, '2026-11-11 17:00:00', '2026-11-13 17:00:00', ['used_at' => now()]);
        issuedLink($this->bob, '2026-11-11 17:00:00', '2026-11-13 17:00:00', ['revoked_at' => now()]);
        WorkerAccessLink::factory()->requested()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->bob->id]);
        $this->actingAs($this->hr);

        $this->get(accessUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('people.data.1.link', null)->where('people.data.1.status', 'not_invited'));
    });

    it('does not count another company\'s links', function (): void {
        $globex = Tenant::factory()->create();
        WorkerAccessLink::factory()->create(['tenant_id' => $globex->id]);
        workIn($this->tenant);
        $this->actingAs($this->hr);

        $this->get(accessUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('counts.invited', 0));
    });

    it('calls synced a key that may be backed up, even if it is not yet', function (): void {
        $this->phone->update(['backup_eligible' => true, 'backup_state' => false]);
        $this->actingAs($this->hr);

        $this->get(accessUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('people.data.0.keys.0.synced', true));
    });

    it('lists each state on its own tab: a revoked key is not one in use', function (string $status, array $names): void {
        issuedLink($this->bob, '2026-11-11 17:00:00', '2026-11-13 17:00:00');
        $this->actingAs($this->hr);

        $this->get(accessUrl(['status' => $status]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.status', $status === 'everything' ? 'all' : $status)
            ->where('people.data', fn ($people): bool => collect($people)->pluck('name')->all() === $names));
    })->with([
        'with a key' => ['with_key', ['Ana López']],
        'invited' => ['invited', ['Bob Díaz']],
        'not invited, or only revoked keys' => ['not_invited', ['Carla Ruiz']],
        'unknown, all of them' => ['everything', ['Ana López', 'Bob Díaz', 'Carla Ruiz']],
    ]);

    it('searches by name without minding accents or case', function (): void {
        $this->actingAs($this->hr);

        $this->get(accessUrl(['q' => 'diaz']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.q', 'diaz')->where('people.total', 1)->where('people.data.0.name', 'Bob Díaz'));
        $this->get(accessUrl(['q' => 'LÓPEZ']))->assertInertia(fn (AssertableInertia $page) => $page->where('people.data.0.name', 'Ana López'));
    });

    it('does not let anyone find a person by their email', function (): void {
        $this->actingAs($this->hr)
            ->get(accessUrl(['q' => 'carla@acme']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('people.total', 0));
    });

    it('narrows to one center, and answers 404 to one archived, of another company or not an id', function (): void {
        $this->actingAs($this->hr);

        $this->get(accessUrl(['center' => $this->office->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('center', ['id' => $this->office->id, 'name' => 'Oficinas'])
            ->where('counts', ['all' => 1, 'with_key' => 0, 'invited' => 0, 'not_invited' => 1])
            ->where('people.data.0.name', 'Carla Ruiz'));

        $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
        $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
        workIn($this->tenant);
        $this->get(accessUrl(['center' => $archived->id]))->assertNotFound();
        $this->get(accessUrl(['center' => $foreign->id]))->assertNotFound();
        $this->get(accessUrl(['center' => 'planta']))->assertNotFound();
    });

    it('pages the people 25 at a time', function (): void {
        EmployeeFactory::new()->count(25)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
        $this->actingAs($this->hr);

        $this->get(accessUrl(['page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page->where('people.total', 28)->has('people.data', 3));
    });

    it('lists only the company\'s people and keys', function (): void {
        $globex = Tenant::factory()->create();
        $stranger = EmployeeFactory::new()->create(['tenant_id' => $globex->id, 'work_center_id' => WorkCenter::factory()->for($globex)->create()->id, 'name' => 'Zoe Extraña']);
        WorkerCredential::factory()->create(['tenant_id' => $globex->id, 'employee_id' => $stranger->id]);
        workIn($this->tenant);

        $this->actingAs($this->hr)->get(accessUrl())->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.total', 3)->where('counts.with_key', 1));
    });

    it('lets a viewer read it, and not change it', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->get(accessUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('canManage', false));
    });

    it('keeps the committee out, answers 404 to a union and to another company', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Committee));
        $this->get(accessUrl())->assertForbidden();

        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));
        $this->get(route('tenant.worker-access.index', $union->slug))->assertNotFound();

        $this->actingAs($this->hr)->get(route('tenant.worker-access.index', Tenant::factory()->create()->slug))->assertNotFound();
    });

    it('takes the same queries with a few people or many, with their keys and links', function (int $more): void {
        foreach (EmployeeFactory::new()->count($more)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->office->id]) as $person) {
            WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id]);
            WorkerAccessLink::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id]);
        }
        $this->actingAs($this->hr);

        $this->expectsDatabaseQueryCount(ACCESS_PAGE_QUERIES);
        $this->get(accessUrl())->assertOk();
    })->with(['a few' => [2], 'many more' => [47]]);
});

describe('inviting a person', function (): void {
    it('emails the link to a person with an email: RH never sees it, in a prop, a flash, the log or the audit trail', function (): void {
        Log::spy();
        $this->actingAs($this->hr);

        $this->from(accessUrl())->post(route('tenant.worker-access.invite', 'acme'), ['employee_id' => $this->carla->id])
            ->assertRedirect(accessUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Enviamos la invitación al correo de Carla Ruiz.'])
            ->assertSessionMissing('inertia.flash_data.accessLink');

        $url = null;
        Notification::assertSentOnDemand(WorkerInvitation::class, function (WorkerInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use (&$url): bool {
            $url = $notification->toMail($notifiable)->actionUrl;

            return $notifiable->routes['mail'] === 'carla@acme.test';
        });
        $token = basename((string) $url);
        $page = $this->get(accessUrl())->assertOk()->getContent();

        expect($page)->not->toContain($token)
            ->and(json_encode(session()->all(), JSON_THROW_ON_ERROR))->not->toContain($token)
            ->and(auditContent(AuditEntry::query()->get()))->not->toContain($token);
        Log::shouldNotHaveReceived('info', fn (string $message, array $context = []): bool => str_contains(json_encode($context, JSON_THROW_ON_ERROR), $token));
    });

    it('shows RH the link once for a person without email, to hand over, and it is gone on the next visit and from the log', function (): void {
        Log::spy();
        $this->actingAs($this->hr);

        $this->from(accessUrl())->post(route('tenant.worker-access.invite', 'acme'), ['employee_id' => $this->bob->id])->assertRedirect(accessUrl());

        $link = $this->get(accessUrl())->assertOk()->inertiaPage()['flash']['accessLink'] ?? null;
        expect($link)->toBeArray()->and($link['name'])->toBe('Bob Díaz')->and($link['url'])->toStartWith(config('app.url').'/me/acme/link/')
            ->and(WorkerAccessLink::query()->sole()->token_hash)->toBe(hash('sha256', basename($link['url'])))
            ->and(AuditEntry::query()->where('event', 'worker_access.link_issued')->sole())->actor_id->toBe($this->hr->id)->metadata->toBe(['employee_id' => $this->bob->id, 'delivered_by_rh' => true])
            ->and(auditContent(AuditEntry::query()->get()))->not->toContain(basename($link['url']));
        Notification::assertNothingSent();
        Log::shouldNotHaveReceived('info', fn (string $message, array $context = []): bool => str_contains(json_encode($context, JSON_THROW_ON_ERROR), basename($link['url'])));

        $this->get(accessUrl())->assertInertia(fn (AssertableInertia $page) => $page->missingFlash('accessLink'));
    });

    it('answers 404 for a person who left, of another company or unknown, and 422 without one', function (): void {
        $left = EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id]);
        $foreign = EmployeeFactory::new()->create();
        workIn($this->tenant);
        $this->actingAs($this->hr);

        foreach ([$left->id, $foreign->id, '0b8a3a4e-0000-4000-8000-000000000000'] as $id) {
            $this->post(route('tenant.worker-access.invite', 'acme'), ['employee_id' => $id])->assertNotFound();
        }
        $this->post(route('tenant.worker-access.invite', 'acme'), [])->assertSessionHasErrors(['employee_id' => 'El campo persona es obligatorio.']);
        expect(WorkerAccessLink::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('cannot be done by a viewer', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->post(route('tenant.worker-access.invite', 'acme'), ['employee_id' => $this->carla->id])->assertForbidden();
        expect(WorkerAccessLink::query()->count())->toBe(0);
    });

    it('lets the owner do it', function (): void {
        $this->actingAs(ownerOf($this->tenant));

        $this->post(route('tenant.worker-access.invite', 'acme'), ['employee_id' => $this->carla->id])->assertRedirect();
        expect(WorkerAccessLink::query()->count())->toBe(1);
    });
});

describe('reissuing a person\'s access', function (): void {
    it('stops every key and session of the person, emails a new link and tells RH', function (): void {
        enteredAs($this->ana->id, EntryVia::Key, $this->phone->id);
        $this->travel(1)->seconds();
        $this->actingAs($this->hr);

        $this->from(accessUrl())->post(route('tenant.worker-access.reissue', 'acme'), ['employee_id' => $this->ana->id])
            ->assertRedirect(accessUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Reemitimos el acceso de Ana López: sus llaves y sesiones dejaron de servir y le enviamos un enlace nuevo a su correo.']);

        expect($this->phone->refresh()->revoked_reason)->toBe('reissued')
            ->and(resolve(WorkerSessionStore::class)->current())->toBeNull()
            ->and(WorkerAccessLink::query()->whereNull('revoked_at')->sole()->employee_id)->toBe($this->ana->id);
        Notification::assertSentOnDemandTimes(WorkerInvitation::class, 1);
    });

    it('shows RH the new link once for a person without email', function (): void {
        $key = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->bob->id]);
        $this->actingAs($this->hr);

        $this->from(accessUrl())->post(route('tenant.worker-access.reissue', 'acme'), ['employee_id' => $this->bob->id])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Reemitimos el acceso de Bob Díaz: sus llaves y sesiones dejaron de servir.'])
            ->assertSessionHas('inertia.flash_data.accessLink', fn (array $link): bool => $link['name'] === 'Bob Díaz' && str_starts_with($link['url'], config('app.url').'/me/acme/link/'));

        expect($key->refresh()->revoked_reason)->toBe('reissued');
    });

    it('cannot be done by a viewer, and answers 404 for a person of another company', function (): void {
        $foreign = EmployeeFactory::new()->create();
        $theirs = WorkerCredential::factory()->create(['tenant_id' => $foreign->tenant_id, 'employee_id' => $foreign->id]);
        workIn($this->tenant);

        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(route('tenant.worker-access.reissue', 'acme'), ['employee_id' => $this->ana->id])->assertForbidden();
        $this->actingAs($this->hr)->post(route('tenant.worker-access.reissue', 'acme'), ['employee_id' => $foreign->id])->assertNotFound();

        expect($this->phone->refresh()->revoked_at)->toBeNull()
            ->and(WorkerCredential::query()->withoutGlobalScopes()->whereKey($theirs->id)->value('revoked_at'))->toBeNull();
    });
});

describe('revoking a key', function (): void {
    it('stops it at once, writes the audit trail and tells RH', function (): void {
        $this->actingAs($this->hr);

        $this->from(accessUrl())->post(route('tenant.worker-access.revoke', ['acme', $this->phone->id]))
            ->assertRedirect(accessUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Llave revocada. Ya no sirve para entrar ni para marcar.']);

        expect($this->phone->fresh())->revoked_at->not->toBeNull()->revoked_reason->toBe('revoked_by_hr')
            ->and(AuditEntry::query()->where('event', 'worker_access.credential_revoked')->sole()->subject_id)->toBe($this->phone->id);
        $this->get(accessUrl(['status' => 'with_key']))->assertInertia(fn (AssertableInertia $page) => $page->where('people.total', 0));
    });

    it('cannot be done by a viewer, and answers 404 for a key of another company or unknown', function (): void {
        $theirs = WorkerCredential::factory()->create();
        workIn($this->tenant);

        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(route('tenant.worker-access.revoke', ['acme', $this->phone->id]))->assertForbidden();
        $this->actingAs($this->hr)->post(route('tenant.worker-access.revoke', ['acme', $theirs->id]))->assertNotFound();
        $this->post(route('tenant.worker-access.revoke', ['acme', '0b8a3a4e-0000-4000-8000-000000000000']))->assertNotFound();

        expect($this->phone->fresh()?->revoked_at)->toBeNull()
            ->and(WorkerCredential::query()->withoutGlobalScopes()->whereKey($theirs->id)->value('revoked_at'))->toBeNull();
    });
});
