<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\SendEnrollmentLinks;
use App\Modules\Attendance\Jobs\SendEnrollmentLink;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Notifications\EnrollmentInvitation;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

mutates(SendEnrollmentLinks::class, SendEnrollmentLink::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $center = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id];
    // Ana and Carla are to be invited (Carla's only device was revoked); Bob has no email; Dan already linked his phone.
    $this->ana = EmployeeFactory::new()->create([...$center, 'name' => 'Ana López', 'email' => 'ana@acme.test']);
    $this->bob = EmployeeFactory::new()->create([...$center, 'name' => 'Bob Díaz', 'email' => null]);
    $this->carla = EmployeeFactory::new()->create([...$center, 'name' => 'Carla Ruiz', 'email' => 'carla@acme.test']);
    $this->dan = EmployeeFactory::new()->create([...$center, 'name' => 'Dan Soto', 'email' => 'dan@acme.test']);
    EmployeeFactory::new()->deactivated()->create([...$center, 'email' => 'ex@acme.test']);
    EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'email' => 'otro@acme.test']);
    AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->carla->id, 'revoked_at' => now()]);
    AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->dan->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    Notification::fake();
});

function enrollAll(string $centerId): Illuminate\Testing\TestResponse
{
    return test()->from(route('tenant.attendance.devices.index', 'acme'))->post(route('tenant.attendance.devices.enroll-all', 'acme'), ['work_center_id' => $centerId]);
}

it('queues one link per person of the center without a device, skipping who has no email or a device, and tells RH how many', function (): void {
    Queue::fake();
    Log::spy();
    $this->actingAs($this->hr);

    enrollAll($this->plant->id)
        ->assertRedirect(route('tenant.attendance.devices.index', 'acme'))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Enviamos 2 enlaces de alta. 1 persona no tiene correo: crea su enlace desde su renglón.']);

    $queued = [];
    Queue::assertPushed(SendEnrollmentLink::class, function (SendEnrollmentLink $job) use (&$queued): bool {
        $queued[] = $job->employeeId;

        return $job->tenantId === $this->tenant->id && $job->userId === $this->hr->id;
    });
    expect($queued)->toEqualCanonicalizing([$this->ana->id, $this->carla->id])
        ->and(EnrollmentLink::query()->count())->toBe(0);
    $entry = AuditEntry::query()->where('event', 'attendance.enrollments_sent')->sole();
    expect($entry)->subject_id->toBeNull()->metadata->toEqual(['work_center_id' => $this->plant->id, 'sent' => 2, 'without_email' => 1, 'linked' => 1]);
    Log::shouldHaveReceived('info')->once()->with('attendance.enrollments_sent', ['work_center_id' => $this->plant->id, 'sent' => 2, 'without_email' => 1, 'linked' => 1]);
});

it('says so when there is nobody to send to', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);
    $empty = WorkCenter::factory()->for($this->tenant)->create();

    enrollAll($empty->id)->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'No había a quién enviar: todas las personas del centro tienen dispositivo o no tienen correo.']);
    Queue::assertNothingPushed();
});

it('tells RH when only people without email were left', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);
    $store = WorkCenter::factory()->for($this->tenant)->create();
    EmployeeFactory::new()->count(2)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $store->id, 'email' => null]);

    enrollAll($store->id)->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'No enviamos ningún enlace. 2 personas no tienen correo: crea su enlace desde su renglón.']);
    Queue::assertNothingPushed();
});

it('queues only ids: the token is made in the worker, never serialized, and the link it emails works', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);
    enrollAll($this->plant->id);

    $job = null;
    Queue::assertPushed(SendEnrollmentLink::class, function (SendEnrollmentLink $pushed) use (&$job): bool {
        $job = $pushed->employeeId === $this->ana->id ? $pushed : $job;

        return true;
    });
    app()->call($job->handle(...));

    $url = null;
    Notification::assertSentOnDemand(EnrollmentInvitation::class, function (EnrollmentInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use (&$url): bool {
        $url = $notification->toMail($notifiable)->actionUrl;

        return $notifiable->routes['mail'] === 'ana@acme.test';
    });
    $token = basename((string) $url);

    expect(serialize($job))->not->toContain($token)
        ->and($job)->toBeInstanceOf(ShouldQueue::class)
        ->and(EnrollmentLink::query()->sole())->employee_id->toBe($this->ana->id)->token_hash->toBe(hash('sha256', $token))->issued_by->toBe($this->hr->id);
});

it('does nothing for a person who left, lost their email or is not the company\'s by the time it runs', function (): void {
    $this->ana->forceFill(['deactivated_at' => now()])->save();
    $this->carla->forceFill(['email' => null])->save();
    $foreign = EmployeeFactory::new()->create(['email' => 'x@otra.test']);

    foreach ([$this->ana->id, $this->carla->id, $foreign->id] as $id) {
        app()->call((new SendEnrollmentLink($this->tenant->id, $id, $this->hr->id))->handle(...));
    }

    Notification::assertNothingSent();
    workIn($this->tenant);
    expect(EnrollmentLink::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('does nothing when the company may not be used by the time it runs', function (): void {
    Tenant::query()->whereKey($this->tenant->id)->update(['deactivated_at' => now()]);

    app()->call((new SendEnrollmentLink($this->tenant->id, $this->ana->id, $this->hr->id))->handle(...));

    Notification::assertNothingSent();
    expect(EnrollmentLink::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('cannot be sent by a viewer, and answers 404 for a center archived, of another company or unknown', function (): void {
    Queue::fake();
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
    workIn($this->tenant);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    enrollAll($this->plant->id)->assertForbidden();

    $this->actingAs($this->hr);
    foreach ([$archived->id, $foreign->id, '0b8a3a4e-0000-4000-8000-000000000000'] as $id) {
        enrollAll($id)->assertNotFound();
    }
    enrollAll('')->assertSessionHasErrors(['work_center_id' => 'El campo centro de trabajo es obligatorio.']);
    Queue::assertNothingPushed();
});

it('queues one link per person even when RH sends the batch twice before the worker runs, and the worker sends one email each', function (): void {
    Queue::fake();
    $this->actingAs($this->hr);

    enrollAll($this->plant->id);
    enrollAll($this->plant->id);

    Queue::assertPushed(SendEnrollmentLink::class, 2);
    expect((new SendEnrollmentLink($this->tenant->id, $this->ana->id, $this->hr->id))->uniqueId())->toBe("{$this->tenant->id}:{$this->ana->id}")
        ->and(new SendEnrollmentLink($this->tenant->id, $this->ana->id, $this->hr->id))->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing::class);
});

it('sends nothing to a person who linked a device between the batch and the worker, and logs why with ids', function (): void {
    AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    Log::spy();

    app()->call((new SendEnrollmentLink($this->tenant->id, $this->ana->id, $this->hr->id))->handle(...));

    Notification::assertNothingSent();
    expect(EnrollmentLink::query()->count())->toBe(0);
    Log::shouldHaveReceived('info')->once()->with('attendance.enrollment_skipped', ['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'reason' => 'has_device']);
});

it('still sends to a person whose only device was revoked by the time it runs', function (): void {
    app()->call((new SendEnrollmentLink($this->tenant->id, $this->carla->id, $this->hr->id))->handle(...));

    Notification::assertSentOnDemandTimes(EnrollmentInvitation::class, 1);
});

it('logs ids only and fails the job when the email cannot be sent after the link was made', function (): void {
    // The real mail channel, against a server that is not there.
    app()->forgetInstance(Illuminate\Notifications\ChannelManager::class);
    Notification::clearResolvedInstance(Illuminate\Notifications\ChannelManager::class);
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
    Log::spy();

    expect(fn () => app()->call((new SendEnrollmentLink($this->tenant->id, $this->ana->id, $this->hr->id))->handle(...)))
        ->toThrow(Symfony\Component\Mailer\Exception\TransportException::class);

    expect(EnrollmentLink::query()->sole()->employee_id)->toBe($this->ana->id);
    Log::shouldHaveReceived('error')->once()->with('attendance.enrollment_email_failed', ['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
});

it('writes the batch to the audit trail before queueing anything, so a failed audit leaves no job behind', function (): void {
    Queue::fake();
    AuditEntry::creating(function (): never {
        throw new RuntimeException('audit down');
    });

    expect(fn () => resolve(SendEnrollmentLinks::class)->handle($this->plant->id, $this->hr->id))->toThrow(RuntimeException::class, 'audit down');

    Queue::assertNothingPushed();
});
