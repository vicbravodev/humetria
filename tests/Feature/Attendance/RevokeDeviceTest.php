<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\RevokeDevice;
use App\Modules\Attendance\Exceptions\InvalidCredential;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Support\WebAuthnCeremony;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Tests\Support\SoftAuthenticator;

mutates(RevokeDevice::class);

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    $this->travelTo(CarbonImmutable::parse('2026-11-07 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
});

it('revokes a device at once: it no longer verifies, and the revocation goes to the audit trail and the log with ids only', function (): void {
    $phone = new SoftAuthenticator;
    $ceremony = resolve(WebAuthnCeremony::class);
    $key = $ceremony->verifyRegistration($this->ana->id, $phone->create($ceremony->registrationOptions($this->ana->id, 'Ana')));
    $device = AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'credential_id' => $key->credentialId, 'public_key' => $key->publicKey]);
    Log::spy();

    resolve(RevokeDevice::class)->handle($device->id);

    expect($device->fresh()?->revoked_at?->toIso8601String())->toBe('2026-11-07T15:00:00+00:00')
        ->and(fn () => $ceremony->verifyAssertion('s', $phone->get($ceremony->assertionOptions('s'), $this->ana->id)))->toThrow(InvalidCredential::class);
    $entry = AuditEntry::query()->where('event', 'attendance.device_revoked')->sole();
    expect($entry)->subject_id->toBe($device->id)->metadata->toBe(['employee_id' => $this->ana->id]);
    Log::shouldHaveReceived('info')->once()->with('attendance.device_revoked', ['device_id' => $device->id, 'employee_id' => $this->ana->id]);
});

it('keeps when a device was first revoked, writing nothing again', function (): void {
    $device = AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    resolve(RevokeDevice::class)->handle($device->id);
    $this->travel(1)->hour();

    resolve(RevokeDevice::class)->handle($device->id);

    expect($device->fresh()?->revoked_at?->toIso8601String())->toBe('2026-11-07T15:00:00+00:00')
        ->and(AuditEntry::query()->where('event', 'attendance.device_revoked')->count())->toBe(1);
});

it('does not revoke a device of another company', function (): void {
    $other = AttendanceDevice::factory()->create();

    expect(fn () => resolve(RevokeDevice::class)->handle($other->id))->toThrow(ModelNotFoundException::class);
    workIn(Tenant::query()->findOrFail($other->tenant_id));
    expect($other->fresh()?->revoked_at)->toBeNull();
});
