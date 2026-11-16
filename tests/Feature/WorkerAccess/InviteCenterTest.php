<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Actions\InviteCenter;
use App\Modules\WorkerAccess\Jobs\SendWorkerInvitation;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Notifications\WorkerInvitation;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

mutates(InviteCenter::class, SendWorkerInvitation::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $center = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id];
    // Ana and Carla are to be invited (Carla's only key was revoked); Bob has no email; Dan already has his key.
    $this->ana = EmployeeFactory::new()->create([...$center, 'name' => 'Ana López', 'email' => 'ana@acme.test']);
    $this->bob = EmployeeFactory::new()->create([...$center, 'name' => 'Bob Díaz', 'email' => null]);
    $this->carla = EmployeeFactory::new()->create([...$center, 'name' => 'Carla Ruiz', 'email' => 'carla@acme.test']);
    $this->dan = EmployeeFactory::new()->create([...$center, 'name' => 'Dan Soto', 'email' => 'dan@acme.test']);
    EmployeeFactory::new()->deactivated()->create([...$center, 'email' => 'ex@acme.test']);
    EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'email' => 'otro@acme.test']);
    WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->carla->id, 'revoked_at' => now()]);
    WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->dan->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    Notification::fake();
});

function waitingLink(string $employeeId, string $expires = '+24 hours'): WorkerAccessLink
{
    return WorkerAccessLink::factory()->withToken("token-{$employeeId}")->create([
        'tenant_id' => test()->tenant->id,
        'employee_id' => $employeeId,
        'issued_by' => test()->hr->id,
        'expires_at' => now()->modify($expires),
    ]);
}

function inviteCenter(string $centerId): Illuminate\Testing\TestResponse
{
    return test()->from(route('tenant.worker-access.index', 'acme'))->post(route('tenant.worker-access.invite-center', 'acme'), ['work_center_id' => $centerId]);
}

/** @return list<string> the people a job was queued for */
function invitedPeople(): array
{
    $queued = [];
    Queue::assertPushed(SendWorkerInvitation::class, function (SendWorkerInvitation $job) use (&$queued): bool {
        $queued[] = $job->employeeId;

        return true;
    });

    return $queued;
}

it('queues one invitation per active person of the center without a key, skipping who has no email or a key, and tells RH how many', function (): void {
    Queue::fake();
    Log::spy();
    $this->actingAs($this->hr);

    inviteCenter($this->plant->id)
        ->assertRedirect(route('tenant.worker-access.index', 'acme'))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Enviamos 2 invitaciones. 1 persona no tiene correo: crea su enlace desde su renglón y entrégaselo.']);

    Queue::assertPushed(SendWorkerInvitation::class, fn (SendWorkerInvitation $job): bool => $job->tenantId === $this->tenant->id && $job->userId === $this->hr->id);
    expect(invitedPeople())->toEqualCanonicalizing([$this->ana->id, $this->carla->id])
        ->and(WorkerAccessLink::query()->count())->toBe(0);
    $entry = AuditEntry::query()->where('event', 'worker_access.center_invited')->sole();
    expect($entry)->subject_id->toBeNull()->metadata->toEqual(['work_center_id' => $this->plant->id, 'sent' => 2, 'without_email' => 1, 'with_key' => 1, 'with_link' => 0]);
    Log::shouldHaveReceived('info')->once()->with('worker_access.center_invited', ['work_center_id' => $this->plant->id, 'sent' => 2, 'without_email' => 1, 'with_key' => 1, 'with_link' => 0]);
});

it('says how many it skipped when the only ones left already have a link', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);
    $store = WorkCenter::factory()->for($this->tenant)->create();
    $one = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $store->id, 'email' => 'uno@acme.test']);
    waitingLink($one->id);

    inviteCenter($store->id)->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'No enviamos ninguna invitación. Omitimos 1 persona que ya tenía un enlace vigente.']);
    Queue::assertNothingPushed();
});

it('says so when there is nobody to invite', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);
    $empty = WorkCenter::factory()->for($this->tenant)->create();

    inviteCenter($empty->id)->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'No había a quién invitar: todas las personas del centro tienen llave o no tienen correo.']);
    Queue::assertNothingPushed();
});

it('counts every person without email, for RH to hand theirs over one by one', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);
    $store = WorkCenter::factory()->for($this->tenant)->create();
    EmployeeFactory::new()->count(2)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $store->id, 'email' => null]);

    inviteCenter($store->id)->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'No enviamos ninguna invitación. 2 personas no tienen correo: crea su enlace desde su renglón y entrégaselo.']);
    Queue::assertNothingPushed();
});

it('queues only ids: the token is made in the worker, never serialized, and the link it emails opens', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);
    inviteCenter($this->plant->id);

    $job = null;
    Queue::assertPushed(SendWorkerInvitation::class, function (SendWorkerInvitation $pushed) use (&$job): bool {
        $job = $pushed->employeeId === $this->ana->id ? $pushed : $job;

        return true;
    });
    app()->call($job->handle(...));

    $url = null;
    Notification::assertSentOnDemand(WorkerInvitation::class, function (WorkerInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use (&$url): bool {
        $url = $notification->toMail($notifiable)->actionUrl;

        return $notifiable->routes['mail'] === 'ana@acme.test';
    });
    $token = basename((string) $url);

    expect(serialize($job))->not->toContain($token)
        ->and($job)->toBeInstanceOf(ShouldQueue::class)
        ->and(WorkerAccessLink::findPending($token))->employee_id->toBe($this->ana->id)->issued_by->toBe($this->hr->id);
});

it('does nothing for a person who left, lost their email or is not the company\'s by the time it runs', function (): void {
    $this->ana->forceFill(['deactivated_at' => now()])->save();
    $this->carla->forceFill(['email' => null])->save();
    $foreign = EmployeeFactory::new()->create(['email' => 'x@otra.test']);

    foreach ([$this->ana->id, $this->carla->id, $foreign->id] as $id) {
        app()->call((new SendWorkerInvitation($this->tenant->id, $id, $this->hr->id))->handle(...));
    }

    Notification::assertNothingSent();
    workIn($this->tenant);
    expect(WorkerAccessLink::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('does nothing when the company may not be used by the time it runs', function (): void {
    Tenant::query()->whereKey($this->tenant->id)->update(suspension());

    app()->call((new SendWorkerInvitation($this->tenant->id, $this->ana->id, $this->hr->id))->handle(...));

    Notification::assertNothingSent();
    expect(WorkerAccessLink::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('cannot be sent by a viewer, and answers 404 for a center archived, of another company or unknown', function (): void {
    Queue::fake();
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
    workIn($this->tenant);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    inviteCenter($this->plant->id)->assertForbidden();

    $this->actingAs($this->hr);
    foreach ([$archived->id, $foreign->id, '0b8a3a4e-0000-4000-8000-000000000000'] as $id) {
        inviteCenter($id)->assertNotFound();
    }
    inviteCenter('')->assertSessionHasErrors(['work_center_id' => 'El campo centro de trabajo es obligatorio.']);
    Queue::assertNothingPushed();
});

it('queues one invitation per person even when RH sends the batch twice before the worker runs', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);

    inviteCenter($this->plant->id);
    inviteCenter($this->plant->id);

    Queue::assertPushed(SendWorkerInvitation::class, 2);
    expect((new SendWorkerInvitation($this->tenant->id, $this->ana->id, $this->hr->id))->uniqueId())->toBe("{$this->tenant->id}:{$this->ana->id}")
        ->and(new SendWorkerInvitation($this->tenant->id, $this->ana->id, $this->hr->id))->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class);
});

it('sends nothing to a person who created a key between the batch and the worker, and logs why with ids', function (): void {
    WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    Log::spy();

    app()->call((new SendWorkerInvitation($this->tenant->id, $this->ana->id, $this->hr->id))->handle(...));

    Notification::assertNothingSent();
    expect(WorkerAccessLink::query()->count())->toBe(0);
    Log::shouldHaveReceived('info')->once()->with('worker_access.invitation_skipped', ['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'reason' => 'has_key']);
});

it('still sends to a person whose only key was revoked by the time it runs', function (): void {
    app()->call((new SendWorkerInvitation($this->tenant->id, $this->carla->id, $this->hr->id))->handle(...));

    Notification::assertSentOnDemandTimes(WorkerInvitation::class, 1);
});

it('logs ids only and fails the job when the email cannot be sent', function (): void {
    // The real mail channel, against a server that is not there.
    app()->forgetInstance(Illuminate\Notifications\ChannelManager::class);
    Notification::clearResolvedInstance(Illuminate\Notifications\ChannelManager::class);
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
    Log::spy();

    expect(fn () => app()->call((new SendWorkerInvitation($this->tenant->id, $this->ana->id, $this->hr->id))->handle(...)))
        ->toThrow(Symfony\Component\Mailer\Exception\TransportException::class);

    Log::shouldHaveReceived('error')->once()->with('worker_access.invitation_email_failed', ['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
});

it('writes the batch to the audit trail before queueing anything, so a failed audit leaves no job behind', function (): void {
    Queue::fake();
    AuditEntry::creating(function (): never {
        throw new RuntimeException('audit down');
    });

    expect(fn () => resolve(InviteCenter::class)->handle($this->plant->id, $this->hr->id))->toThrow(RuntimeException::class, 'audit down');

    Queue::assertNothingPushed();
});

it('leaves alone who already has a link that still opens: the batch queues nobody for them, keeps their link and says how many it skipped', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);
    $eva = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'email' => 'eva@acme.test']);
    $evaLink = waitingLink($eva->id);
    $bobLink = waitingLink($this->bob->id);

    inviteCenter($this->plant->id)
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Enviamos 2 invitaciones. Omitimos 2 personas que ya tenían un enlace vigente.']);

    expect(invitedPeople())->toEqualCanonicalizing([$this->ana->id, $this->carla->id])
        ->and(WorkerAccessLink::query()->whereNull('revoked_at')->pluck('id')->all())->toEqualCanonicalizing([$evaLink->id, $bobLink->id])
        ->and(AuditEntry::query()->where('event', 'worker_access.center_invited')->sole()->metadata)->toEqual(['work_center_id' => $this->plant->id, 'sent' => 2, 'without_email' => 0, 'with_key' => 1, 'with_link' => 2]);
});

it('does not replace a waiting link when the worker runs: the person keeps the same link and gets no new email', function (): void {
    $waiting = waitingLink($this->ana->id);
    Log::spy();

    app()->call((new SendWorkerInvitation($this->tenant->id, $this->ana->id, $this->hr->id))->handle(...));

    Notification::assertNothingSent();
    expect(WorkerAccessLink::query()->sole())->id->toBe($waiting->id)->revoked_at->toBeNull();
    Log::shouldHaveReceived('info')->once()->with('worker_access.invitation_skipped', ['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'reason' => 'has_link']);
});

it('invites again who has a link that expired, was used or is only one they asked for', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);
    $expired = waitingLink($this->ana->id, '-1 hour');
    waitingLink($this->carla->id)->forceFill(['used_at' => now()])->save();
    WorkerAccessLink::factory()->requested()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->carla->id]);

    inviteCenter($this->plant->id)->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Enviamos 2 invitaciones. 1 persona no tiene correo: crea su enlace desde su renglón y entrégaselo.']);

    $queued = invitedPeople();
    expect($queued)->toEqualCanonicalizing([$this->ana->id, $this->carla->id]);
    foreach ($queued as $id) {
        app()->call((new SendWorkerInvitation($this->tenant->id, $id, $this->hr->id))->handle(...));
    }
    Notification::assertSentOnDemandTimes(WorkerInvitation::class, 2);
    expect($expired->refresh()->revoked_at)->not->toBeNull();
});

it('only counts the links of the company: another company\'s waiting link does not stop the batch', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);
    WorkerAccessLink::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    workIn($this->tenant);

    inviteCenter($this->plant->id);

    Queue::assertPushed(SendWorkerInvitation::class, 2);
});
