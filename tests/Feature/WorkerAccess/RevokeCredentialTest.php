<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Actions\RevokeCredential;
use App\Modules\WorkerAccess\Exceptions\InvalidCredential;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use App\Modules\WorkerAccess\Support\WebAuthnCeremony;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Tests\Support\SoftAuthenticator;

mutates(RevokeCredential::class);

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
    $device = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'credential_id' => $key->credentialId, 'public_key' => $key->publicKey]);
    Log::spy();

    resolve(RevokeCredential::class)->handle($device->id);

    expect($device->fresh()?->revoked_at?->toIso8601String())->toBe('2026-11-07T15:00:00+00:00')
        ->and($device->fresh()?->revoked_reason)->toBe('revoked_by_hr')
        ->and(fn () => $ceremony->verifyAssertion('s', $phone->get($ceremony->assertionOptions('s'), $this->ana->id)))->toThrow(InvalidCredential::class);
    $entry = AuditEntry::query()->where('event', 'worker_access.credential_revoked')->sole();
    expect($entry)->subject_id->toBe($device->id)->metadata->toBe(['employee_id' => $this->ana->id]);
    Log::shouldHaveReceived('info')->once()->with('worker_access.credential_revoked', ['key_id' => $device->id, 'employee_id' => $this->ana->id]);
});

it('keeps when a device was first revoked, writing nothing again', function (): void {
    $device = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    resolve(RevokeCredential::class)->handle($device->id);
    $this->travel(1)->hour();

    resolve(RevokeCredential::class)->handle($device->id);

    expect($device->fresh()?->revoked_at?->toIso8601String())->toBe('2026-11-07T15:00:00+00:00')
        ->and(AuditEntry::query()->where('event', 'worker_access.credential_revoked')->count())->toBe(1);
});

it('does not revoke a device of another company', function (): void {
    $other = WorkerCredential::factory()->create();

    expect(fn () => resolve(RevokeCredential::class)->handle($other->id))->toThrow(ModelNotFoundException::class);
    workIn(Tenant::query()->findOrFail($other->tenant_id));
    expect($other->fresh()?->revoked_at)->toBeNull();
});

it('leaves the device keys active: they are not tied to one passkey, and RH revokes them by reissuing', function (): void {
    $device = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $phoneKey = WorkerDeviceKey::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

    resolve(RevokeCredential::class)->handle($device->id);

    expect($phoneKey->refresh()->revoked_at)->toBeNull();
});
