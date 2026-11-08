<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Http\Controllers\DeviceController;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Notifications\EnrollmentInvitation;
use App\Modules\Attendance\Support\DevicePages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

mutates(DeviceController::class, DevicePages::class);

/** The queries of the devices page, whatever the number of people or devices. */
const DEVICES_PAGE_QUERIES = 18;

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->office = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
    $this->ana = devicesPerson($this->plant, 'Ana López', 'Operadora', 'ana@acme.test');
    $this->bob = devicesPerson($this->plant, 'Bob Díaz', 'Almacenista', null);
    $this->carla = devicesPerson($this->office, 'Carla Ruiz', 'Analista', 'carla@acme.test');
    EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ex Empleado']);
    // Ana: her phone (a passkey of one device) and an old one revoked. Carla: a synced passkey of her account, revoked.
    $this->phone = AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone de Ana', 'enrolled_at' => CarbonImmutable::parse('2026-11-02 15:00:00', 'UTC'), 'last_used_at' => CarbonImmutable::parse('2026-11-10 14:05:00', 'UTC')]);
    $this->old = AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'Moto viejo', 'enrolled_at' => CarbonImmutable::parse('2026-10-01 15:00:00', 'UTC'), 'revoked_at' => CarbonImmutable::parse('2026-11-01 15:00:00', 'UTC')]);
    $this->synced = AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->carla->id, 'label' => 'Pixel', 'backup_eligible' => true, 'backup_state' => true, 'enrolled_at' => CarbonImmutable::parse('2026-10-20 15:00:00', 'UTC'), 'revoked_at' => CarbonImmutable::parse('2026-11-05 15:00:00', 'UTC')]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    Notification::fake();
});

function devicesPerson(WorkCenter $center, string $name, string $jobTitle, ?string $email): Employee
{
    return EmployeeFactory::new()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => $name, 'job_title' => $jobTitle, 'email' => $email]);
}

function devicesUrl(array $query = []): string
{
    return route('tenant.attendance.devices.index', ['acme', ...$query]);
}

describe('the list', function (): void {
    it('shows RH each person with their devices, active first, and whether the key is synced, never a credential', function (): void {
        $this->actingAs($this->hr);

        $response = $this->get(devicesUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/devices')
            ->where('center', null)
            ->where('filters', ['q' => '', 'status' => 'all'])
            ->where('counts', ['all' => 3, 'linked' => 1, 'unlinked' => 1, 'revoked' => 1])
            ->where('canManage', true)
            ->where('people.total', 3)
            ->where('people.per_page', 25)
            ->where('people.data.0', [
                'id' => $this->ana->id, 'name' => 'Ana López', 'jobTitle' => 'Operadora', 'center' => 'Planta Norte', 'hasEmail' => true, 'status' => 'linked',
                'devices' => [
                    ['id' => $this->phone->id, 'label' => 'iPhone de Ana', 'synced' => false, 'enrolledAt' => '2026-11-02 09:00', 'lastUsedAt' => '2026-11-10 08:05', 'revokedAt' => null],
                    ['id' => $this->old->id, 'label' => 'Moto viejo', 'synced' => false, 'enrolledAt' => '2026-10-01 09:00', 'lastUsedAt' => null, 'revokedAt' => '2026-11-01 09:00'],
                ],
            ])
            ->where('people.data.1', [
                'id' => $this->bob->id, 'name' => 'Bob Díaz', 'jobTitle' => 'Almacenista', 'center' => 'Planta Norte', 'hasEmail' => false, 'status' => 'unlinked', 'devices' => [],
            ])
            ->where('people.data.2.status', 'revoked')
            ->where('people.data.2.devices.0.synced', true));

        expect($response->getContent())->not->toContain($this->phone->credential_id)->not->toContain($this->phone->public_key)
            ->not->toContain('credential')->not->toContain('public_key')->not->toContain('publicKey')
            ->not->toContain('latitude')->not->toContain('ana@acme.test');
    });

    it('calls synced a key that may be backed up, even if it is not yet', function (): void {
        $this->phone->update(['backup_eligible' => true, 'backup_state' => false]);
        $this->actingAs($this->hr);

        $this->get(devicesUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('people.data.0.devices.0.synced', true));
    });

    it('lists each state on its own tab: a revoked device is not an active one', function (string $status, array $names): void {
        $this->actingAs($this->hr);

        $this->get(devicesUrl(['status' => $status]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.status', $status === 'everything' ? 'all' : $status)
            ->where('people.data', fn ($people): bool => collect($people)->pluck('name')->all() === $names));
    })->with([
        'with a device' => ['linked', ['Ana López']],
        'never linked' => ['unlinked', ['Bob Díaz']],
        'only revoked ones' => ['revoked', ['Carla Ruiz']],
        'unknown, all of them' => ['everything', ['Ana López', 'Bob Díaz', 'Carla Ruiz']],
    ]);

    it('searches by name without minding accents or case', function (): void {
        $this->actingAs($this->hr);

        $this->get(devicesUrl(['q' => 'diaz']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.q', 'diaz')->where('people.total', 1)->where('people.data.0.name', 'Bob Díaz'));
        $this->get(devicesUrl(['q' => 'LÓPEZ']))->assertInertia(fn (AssertableInertia $page) => $page->where('people.data.0.name', 'Ana López'));
    });

    it('narrows to one center, and answers 404 to one archived, of another company or not an id', function (): void {
        $this->actingAs($this->hr);

        $this->get(devicesUrl(['center' => $this->office->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('center', ['id' => $this->office->id, 'name' => 'Oficinas'])
            ->where('counts', ['all' => 1, 'linked' => 0, 'unlinked' => 0, 'revoked' => 1])
            ->where('people.data.0.name', 'Carla Ruiz'));

        $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
        $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
        workIn($this->tenant);
        $this->get(devicesUrl(['center' => $archived->id]))->assertNotFound();
        $this->get(devicesUrl(['center' => $foreign->id]))->assertNotFound();
        $this->get(devicesUrl(['center' => 'planta']))->assertNotFound();
    });

    it('pages the people 25 at a time', function (): void {
        EmployeeFactory::new()->count(25)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
        $this->actingAs($this->hr);

        $this->get(devicesUrl(['page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page->where('people.total', 28)->has('people.data', 3));
    });

    it('lets a viewer read it, and not change it', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->get(devicesUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('canManage', false));
    });

    it('keeps the committee out and answers 404 to a union', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Committee));
        $this->get(devicesUrl())->assertForbidden();

        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));
        $this->get(route('tenant.attendance.devices.index', $union->slug))->assertNotFound();
    });

    it('takes the same queries with a few people or many, with their devices', function (int $more): void {
        foreach (EmployeeFactory::new()->count($more)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->office->id]) as $person) {
            AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id]);
        }
        $this->actingAs($this->hr);

        $this->expectsDatabaseQueryCount(DEVICES_PAGE_QUERIES);
        $this->get(devicesUrl())->assertOk();
    })->with(['a few' => [2], 'many more' => [47]]);
});

describe('the enrollment link', function (): void {
    it('is only emailed to a person with an email: RH never sees it, in a prop, a flash, the log or the audit trail', function (): void {
        Log::spy();
        $this->actingAs($this->hr);

        $this->from(devicesUrl())->post(route('tenant.attendance.devices.enroll', 'acme'), ['employee_id' => $this->ana->id])
            ->assertRedirect(devicesUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Enviamos el enlace de alta al correo de Ana López.'])
            ->assertSessionMissing('inertia.flash_data.enrollmentLink');

        $url = null;
        Notification::assertSentOnDemand(EnrollmentInvitation::class, function (EnrollmentInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use (&$url): bool {
            $url = $notification->toMail($notifiable)->actionUrl;

            return true;
        });
        $token = basename((string) $url);
        $page = $this->get(devicesUrl())->assertOk()->getContent();

        expect($page)->not->toContain($token)
            ->and(json_encode(session()->all(), JSON_THROW_ON_ERROR))->not->toContain($token)
            ->and(json_encode(AuditEntry::query()->get()->toArray(), JSON_THROW_ON_ERROR))->not->toContain($token);
        Log::shouldNotHaveReceived('info', fn (string $message, array $context = []): bool => str_contains(json_encode($context, JSON_THROW_ON_ERROR), $token));
    });

    it('is shown once to RH for a person without email, to hand over, and gone on the next visit', function (): void {
        $this->actingAs($this->hr);

        $this->from(devicesUrl())->post(route('tenant.attendance.devices.enroll', 'acme'), ['employee_id' => $this->bob->id])->assertRedirect(devicesUrl());

        $link = $this->get(devicesUrl())->assertOk()->inertiaPage()['flash']['enrollmentLink'] ?? null;
        expect($link)->toBeArray()->and($link['name'])->toBe('Bob Díaz')->and($link['url'])->toStartWith(config('app.url').'/a/acme/enroll/')
            ->and(EnrollmentLink::query()->sole()->token_hash)->toBe(hash('sha256', basename($link['url'])));
        Notification::assertNothingSent();

        $this->get(devicesUrl())->assertInertia(fn (AssertableInertia $page) => $page->missingFlash('enrollmentLink'));
    });

    it('answers 404 for a person who left, of another company or unknown, and 422 without one', function (): void {
        $left = EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id]);
        $foreign = EmployeeFactory::new()->create();
        workIn($this->tenant);
        $this->actingAs($this->hr);

        foreach ([$left->id, $foreign->id, '0b8a3a4e-0000-4000-8000-000000000000'] as $id) {
            $this->post(route('tenant.attendance.devices.enroll', 'acme'), ['employee_id' => $id])->assertNotFound();
        }
        $this->post(route('tenant.attendance.devices.enroll', 'acme'), [])->assertSessionHasErrors(['employee_id' => 'El campo persona es obligatorio.']);
        expect(EnrollmentLink::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('cannot be sent by a viewer', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->post(route('tenant.attendance.devices.enroll', 'acme'), ['employee_id' => $this->ana->id])->assertForbidden();
        expect(EnrollmentLink::query()->count())->toBe(0);
    });
});

describe('revoking a device', function (): void {
    it('stops it at once, writes the audit trail and tells RH', function (): void {
        $this->actingAs($this->hr);

        $this->from(devicesUrl())->post(route('tenant.attendance.devices.revoke', ['acme', $this->phone->id]))
            ->assertRedirect(devicesUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Dispositivo revocado. Ya no puede marcar.']);

        expect($this->phone->fresh()?->revoked_at)->not->toBeNull()
            ->and(AuditEntry::query()->where('event', 'attendance.device_revoked')->sole()->subject_id)->toBe($this->phone->id);
        $this->get(devicesUrl(['status' => 'linked']))->assertInertia(fn (AssertableInertia $page) => $page->where('people.total', 0));
    });

    it('cannot be done by a viewer, and answers 404 for a device of another company or unknown', function (): void {
        $theirs = AttendanceDevice::factory()->create();
        workIn($this->tenant);

        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(route('tenant.attendance.devices.revoke', ['acme', $this->phone->id]))->assertForbidden();
        $this->actingAs($this->hr)->post(route('tenant.attendance.devices.revoke', ['acme', $theirs->id]))->assertNotFound();
        $this->post(route('tenant.attendance.devices.revoke', ['acme', '0b8a3a4e-0000-4000-8000-000000000000']))->assertNotFound();

        expect($this->phone->fresh()?->revoked_at)->toBeNull()
            ->and(AttendanceDevice::query()->withoutGlobalScopes()->whereKey($theirs->id)->value('revoked_at'))->toBeNull();
    });
});
