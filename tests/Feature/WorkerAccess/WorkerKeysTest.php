<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use App\Modules\WorkerAccess\Data\CredentialData;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Tests\Support\SoftAuthenticator;

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    $this->travelTo(CarbonImmutable::parse('2026-11-07 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->phone = new SoftAuthenticator;
    $this->keys = resolve(WorkerKeys::class);
});

it('gives the browser the options to create a key for a person', function (): void {
    expect($this->keys->registrationOptions($this->ana->id, 'Ana López'))->toHaveKeys(['challenge', 'rp', 'user', 'pubKeyCredParams']);
});

it('verifies the key a phone created', function (): void {
    $key = $this->keys->verifyRegistration($this->ana->id, $this->phone->create($this->keys->registrationOptions($this->ana->id, 'Ana')));

    expect($key)->toBeInstanceOf(CredentialData::class)->signCount->toBe(0);
});

it('answers null, never an exception, for a key that does not verify', function (): void {
    expect($this->keys->verifyRegistration($this->ana->id, ['id' => 'nothing']))->toBeNull();
});

it('saves a verified key with its label, to the audit trail and the log with ids only', function (): void {
    $key = $this->keys->verifyRegistration($this->ana->id, $this->phone->create($this->keys->registrationOptions($this->ana->id, 'Ana')));
    Log::spy();

    $id = $this->keys->save($this->ana->id, $key ?? throw new RuntimeException, 'iPhone');

    $credential = WorkerCredential::query()->sole();
    expect($id)->toBe($credential->id)
        ->and($credential)->employee_id->toBe($this->ana->id)->label->toBe('iPhone')->sign_count->toBe(0)->revoked_at->toBeNull()->revoked_reason->toBeNull()
        ->registered_via->toBe('issued_link')
        ->and($credential->registered_at->toIso8601String())->toBe('2026-11-07T15:00:00+00:00');
    $entry = AuditEntry::query()->where('event', 'worker_access.credential_registered')->sole();
    expect($entry)->subject_id->toBe($credential->id)->metadata->toBe(['employee_id' => $this->ana->id]);
    Log::shouldHaveReceived('info')->once()->with('worker_access.credential_registered', ['key_id' => $credential->id, 'employee_id' => $this->ana->id]);
});

it('does not save the same key twice: the second answer is null and nothing is written', function (): void {
    $key = $this->keys->verifyRegistration($this->ana->id, $this->phone->create($this->keys->registrationOptions($this->ana->id, 'Ana'))) ?? throw new RuntimeException;
    $this->keys->save($this->ana->id, $key, 'iPhone');

    expect($this->keys->save($this->ana->id, $key, 'iPhone again'))->toBeNull()
        ->and(WorkerCredential::query()->count())->toBe(1)
        ->and(AuditEntry::query()->where('event', 'worker_access.credential_registered')->count())->toBe(1);
});
