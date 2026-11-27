<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
use App\Modules\WorkerAccess\Data\SignedBy;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Support\CeremonySignatures;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Support\SoftAuthenticator;

mutates(CeremonySignatures::class);

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

/** The phone's answer to the 32 raw bytes an offline mark signs, given as the WebAuthn challenge. */
function offlineAnswer(SoftAuthenticator $phone, string $employeeId, string $expected, bool $tamper = false): array
{
    return $phone->get(['challenge' => Base64UrlSafe::encodeUnpadded($expected)], $employeeId, tamper: $tamper);
}

describe('verifyChallenge', function (): void {
    beforeEach(function (): void {
        $this->expected = hash('sha256', 'humetria-offline-mark-v1|a mark', true);
        $this->issuedAt = CarbonImmutable::parse('2026-11-07 15:00:00', 'UTC');
    });

    it('verifies a challenge of its own and tells which key and which person signed, with no revocation', function (): void {
        $signed = $this->signatures->verifyChallenge($this->expected, offlineAnswer($this->phone, $this->ana->id, $this->expected), $this->issuedAt);

        expect($signed)->toBeInstanceOf(SignedBy::class)
            ->credentialId->toBe($this->credential->id)
            ->employeeId->toBe($this->ana->id)
            ->revokedAt->toBeNull();
    });

    it('verifies the same signature twice: no cache keeps it', function (): void {
        $answer = offlineAnswer($this->phone, $this->ana->id, $this->expected);

        expect($this->signatures->verifyChallenge($this->expected, $answer, $this->issuedAt))->not->toBeNull()
            ->and($this->signatures->verifyChallenge($this->expected, $answer, $this->issuedAt))->not->toBeNull();
    });

    it('answers null for another challenge, for a signature that was altered and for a key of another company', function (): void {
        $answer = offlineAnswer($this->phone, $this->ana->id, $this->expected);

        expect($this->signatures->verifyChallenge(hash('sha256', 'another mark', true), $answer, $this->issuedAt))->toBeNull()
            ->and($this->signatures->verifyChallenge($this->expected, offlineAnswer($this->phone, $this->ana->id, $this->expected, tamper: true), $this->issuedAt))->toBeNull();

        workIn(Tenant::factory()->create());

        expect(resolve(WorkerSignatures::class)->verifyChallenge($this->expected, $answer, $this->issuedAt))->toBeNull();
    });

    it('answers null, never an exception, for anything that is not a signature', function (): void {
        $broken = ['id' => 'AQ', 'rawId' => 'AQ', 'type' => 'public-key', 'response' => ['clientDataJSON' => 'e30', 'authenticatorData' => 'AA', 'signature' => 'AA']];

        expect($this->signatures->verifyChallenge($this->expected, [], $this->issuedAt))->toBeNull()
            ->and($this->signatures->verifyChallenge($this->expected, $broken, $this->issuedAt))->toBeNull();
    });

    it('still verifies a key revoked after the ticket was issued, and says when', function (): void {
        $this->credential->update(['revoked_at' => $this->issuedAt->addSecond()]);

        $signed = $this->signatures->verifyChallenge($this->expected, offlineAnswer($this->phone, $this->ana->id, $this->expected), $this->issuedAt);

        expect($signed)->not->toBeNull()->and($signed?->revokedAt?->equalTo($this->issuedAt->addSecond()))->toBeTrue();
    });

    it('answers null for a key revoked before the ticket was issued or at that very instant', function (int $seconds): void {
        $this->credential->update(['revoked_at' => $this->issuedAt->addSeconds($seconds)]);

        expect($this->signatures->verifyChallenge($this->expected, offlineAnswer($this->phone, $this->ana->id, $this->expected), $this->issuedAt))->toBeNull();
    })->with(['a second before' => [-1], 'at the same instant' => [0]]);

    it('verifies a person who left: whether they are still active is for Attendance to say', function (): void {
        DB::table('employees')->where('id', $this->ana->id)->update(['deactivated_at' => now()]);

        expect($this->signatures->verifyChallenge($this->expected, offlineAnswer($this->phone, $this->ana->id, $this->expected), $this->issuedAt))->not->toBeNull();
    });

    it('writes nothing: neither the counter, the backup state nor the last use', function (): void {
        $columns = ['sign_count', 'backup_state', 'last_used_at', 'updated_at'];
        $before = $this->credential->fresh()?->only($columns);

        $this->signatures->verifyChallenge($this->expected, offlineAnswer($this->phone, $this->ana->id, $this->expected), $this->issuedAt);

        expect($this->credential->fresh()?->only($columns))->toEqual($before);
    });

    it('leaves verify, the online one, with no revocation to report', function (): void {
        $signed = $this->signatures->verify('s', $this->phone->get($this->signatures->options('s'), $this->ana->id));

        expect($signed)->revokedAt->toBeNull();
    });
});
