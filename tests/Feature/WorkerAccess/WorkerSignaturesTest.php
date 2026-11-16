<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
use App\Modules\WorkerAccess\Data\SignedBy;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Database\Factories\Organization\EmployeeFactory;
use Tests\Support\SoftAuthenticator;

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->phone = new SoftAuthenticator;
    $keys = resolve(WorkerKeys::class);
    $this->key = $keys->verifyRegistration($this->ana->id, $this->phone->create($keys->registrationOptions($this->ana->id, 'Ana')));
    $this->credential = WorkerCredential::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->ana->id,
        'credential_id' => $this->key?->credentialId,
        'public_key' => $this->key?->publicKey,
    ]);
    $this->signatures = resolve(WorkerSignatures::class);
});

it('gives the browser a challenge to sign in a context', function (): void {
    expect($this->signatures->options('session-1'))->toHaveKeys(['challenge', 'rpId', 'userVerification'])
        ->and($this->signatures->options('session-1')['rpId'])->toBe('localhost');
});

it('tells which key and which person signed', function (): void {
    $signed = $this->signatures->verify('s', $this->phone->get($this->signatures->options('s'), $this->ana->id));

    expect($signed)->toBeInstanceOf(SignedBy::class)
        ->credentialId->toBe($this->credential->id)
        ->employeeId->toBe($this->ana->id);
});

it('answers null, never an exception, for a signature that does not verify', function (): void {
    $answer = $this->phone->get($this->signatures->options('s'), $this->ana->id, tamper: true);

    expect($this->signatures->verify('s', $answer))->toBeNull();
});

it('answers null for a key that was revoked', function (): void {
    $this->credential->update(['revoked_at' => now()]);

    expect($this->signatures->verify('s', $this->phone->get($this->signatures->options('s'), $this->ana->id)))->toBeNull();
});

it('answers null for a challenge given in another context', function (): void {
    $answer = $this->phone->get($this->signatures->options('one'), $this->ana->id);

    expect($this->signatures->verify('another', $answer))->toBeNull();
});
