<?php

declare(strict_types=1);

use App\Modules\Attendance\Data\CredentialData;
use App\Modules\Attendance\Exceptions\InvalidCredential;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Support\WebAuthnCeremony;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Support\SoftAuthenticator;

mutates(WebAuthnCeremony::class);

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    $this->travelTo(CarbonImmutable::parse('2026-11-07 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->ceremony = resolve(WebAuthnCeremony::class);
    $this->phone = new SoftAuthenticator;
});

/** The phone's key, registered for `$employeeId` and saved as a device of the current company. */
function enrolledDevice(SoftAuthenticator $phone, string $employeeId): AttendanceDevice
{
    $ceremony = resolve(WebAuthnCeremony::class);
    $credential = $ceremony->verifyRegistration($employeeId, $phone->create($ceremony->registrationOptions($employeeId, 'Ana')));

    return AttendanceDevice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'employee_id' => $employeeId,
        'credential_id' => $credential->credentialId,
        'public_key' => $credential->publicKey,
        'sign_count' => $credential->signCount,
    ]);
}

/** That `$attempt` is refused with the one message every refusal gives, whatever check failed. */
function expectRefused(Closure $attempt): void
{
    expect($attempt)->toThrow(InvalidCredential::class, 'The credential could not be verified.');
}

describe('registration', function (): void {
    it('asks for a key of this device, discoverable and verified (fingerprint, face or PIN), with no attestation', function (): void {
        $options = $this->ceremony->registrationOptions($this->ana->id, 'Ana López');

        expect(strlen(Base64UrlSafe::decodeNoPadding($options['challenge'])))->toBe(32)
            ->and($options['rp'])->toBe(['id' => 'localhost', 'name' => 'Humetría'])
            ->and($options['user'])->toBe(['id' => Base64UrlSafe::encodeUnpadded($this->ana->id), 'name' => 'Ana López', 'displayName' => 'Ana López'])
            ->and($options['pubKeyCredParams'])->toBe([['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]])
            ->and($options['authenticatorSelection'])->toMatchArray(['authenticatorAttachment' => 'platform', 'residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required'])
            ->and($options['attestation'])->toBe('none')
            ->and($options['timeout'])->toBe(60000)
            ->and($this->ceremony->registrationOptions($this->ana->id, 'Ana López')['challenge'])->not->toBe($options['challenge']);
    });

    it('verifies a new key and gives its id, public key and counter', function (): void {
        $credential = $this->ceremony->verifyRegistration($this->ana->id, $this->phone->create($this->ceremony->registrationOptions($this->ana->id, 'Ana')));

        expect($credential)->toBeInstanceOf(CredentialData::class)
            ->credentialId->toBe(Base64UrlSafe::encodeUnpadded($this->phone->credentialId))
            ->signCount->toBe(0)
            ->and(strlen(Base64UrlSafe::decodeNoPadding($credential->publicKey)))->toBeGreaterThan(64);
    });

    it('refuses a key made without verifying the person', function (): void {
        $options = $this->ceremony->registrationOptions($this->ana->id, 'Ana');

        expectRefused(fn () => $this->ceremony->verifyRegistration($this->ana->id, $this->phone->create($options, userVerified: false)));
    });

    it('refuses a key made for another site or another relying party', function (): void {
        $options = $this->ceremony->registrationOptions($this->ana->id, 'Ana');
        expectRefused(fn () => $this->ceremony->verifyRegistration($this->ana->id, $this->phone->create($options, origin: 'https://evil.test')));

        $options = $this->ceremony->registrationOptions($this->ana->id, 'Ana');
        expectRefused(fn () => $this->ceremony->verifyRegistration($this->ana->id, $this->phone->create($options, rpId: 'evil.test')));
    });

    it('accepts each challenge once, only for its person and company, and for 60 seconds', function (): void {
        $answer = $this->phone->create($this->ceremony->registrationOptions($this->ana->id, 'Ana'));
        $this->ceremony->verifyRegistration($this->ana->id, $answer);
        expectRefused(fn () => $this->ceremony->verifyRegistration($this->ana->id, $answer));

        $pepe = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
        $forAna = $this->phone->create($this->ceremony->registrationOptions($this->ana->id, 'Ana'));
        expectRefused(fn () => $this->ceremony->verifyRegistration($pepe->id, $forAna));

        $late = $this->phone->create($this->ceremony->registrationOptions($this->ana->id, 'Ana'));
        $this->travel(61)->seconds();
        expectRefused(fn () => $this->ceremony->verifyRegistration($this->ana->id, $late));

        $inTime = $this->phone->create($this->ceremony->registrationOptions($this->ana->id, 'Ana'));
        $this->travel(59)->seconds();
        expect($this->ceremony->verifyRegistration($this->ana->id, $inTime))->toBeInstanceOf(CredentialData::class);

        $elsewhere = $this->phone->create($this->ceremony->registrationOptions($this->ana->id, 'Ana'));
        workIn(Tenant::factory()->create());
        expectRefused(fn () => resolve(WebAuthnCeremony::class)->verifyRegistration($this->ana->id, $elsewhere));
    });

    it('refuses a challenge it never gave', function (): void {
        $this->ceremony->registrationOptions($this->ana->id, 'Ana');

        expectRefused(fn () => $this->ceremony->verifyRegistration($this->ana->id, $this->phone->create([], challenge: Base64UrlSafe::encodeUnpadded(random_bytes(32)))));
    });

    it('refuses anything that is not a new key, with the same answer', function (array $answer): void {
        $this->ceremony->registrationOptions($this->ana->id, 'Ana');

        expectRefused(fn () => $this->ceremony->verifyRegistration($this->ana->id, $answer));
    })->with([
        'nothing' => [[]],
        'not a credential' => [['id' => 'x', 'rawId' => 'x', 'type' => 'password', 'response' => []]],
        'a broken attestation' => [['id' => 'AQ', 'rawId' => 'AQ', 'type' => 'public-key', 'response' => ['clientDataJSON' => 'e30', 'attestationObject' => '_w']]],
        'an assertion' => [['id' => 'AQ', 'rawId' => 'AQ', 'type' => 'public-key', 'response' => ['clientDataJSON' => 'e30', 'authenticatorData' => 'AA', 'signature' => 'AA']]],
    ]);
});

describe('assertion', function (): void {
    it('asks for a verified key of this site, with no username', function (): void {
        $options = $this->ceremony->assertionOptions('session-1');

        expect(strlen(Base64UrlSafe::decodeNoPadding($options['challenge'])))->toBe(32)
            ->and($options)->toMatchArray(['rpId' => 'localhost', 'userVerification' => 'required', 'timeout' => 60000])
            ->and($options['allowCredentials'] ?? [])->toBe([]);
    });

    it('tells whose device signed, and moves its counter on', function (): void {
        $device = enrolledDevice($this->phone, $this->ana->id);
        $this->travelTo(CarbonImmutable::parse('2026-11-07 16:00:00', 'UTC'));

        $verified = $this->ceremony->verifyAssertion('session-1', $this->phone->get($this->ceremony->assertionOptions('session-1'), $this->ana->id));

        expect($verified)->deviceId->toBe($device->id)->employeeId->toBe($this->ana->id)
            ->and($device->fresh())->sign_count->toBe(1)
            ->and($device->fresh()->last_used_at?->toIso8601String())->toBe('2026-11-07T16:00:00+00:00');
    });

    it('accepts a key whose authenticator keeps no counter, as synced passkeys do', function (): void {
        $phone = new SoftAuthenticator(countsSignatures: false);
        $device = enrolledDevice($phone, $this->ana->id);

        foreach ([1, 2] as $ignored) {
            expect($this->ceremony->verifyAssertion('s', $phone->get($this->ceremony->assertionOptions('s'), $this->ana->id))->deviceId)->toBe($device->id);
        }
        expect($device->fresh()->sign_count)->toBe(0);
    });

    it('refuses a signature that was altered', function (): void {
        enrolledDevice($this->phone, $this->ana->id);

        expectRefused(fn () => $this->ceremony->verifyAssertion('s', $this->phone->get($this->ceremony->assertionOptions('s'), $this->ana->id, tamper: true)));
    });

    it('refuses a counter equal to or below the last one, as a cloned key would give', function (int $behind): void {
        $device = enrolledDevice($this->phone, $this->ana->id);
        $this->ceremony->verifyAssertion('s', $this->phone->get($this->ceremony->assertionOptions('s'), $this->ana->id));
        $this->ceremony->verifyAssertion('s', $this->phone->get($this->ceremony->assertionOptions('s'), $this->ana->id));

        $this->phone->counter -= $behind + 1;

        expectRefused(fn () => $this->ceremony->verifyAssertion('s', $this->phone->get($this->ceremony->assertionOptions('s'), $this->ana->id)));
        expect($device->fresh()->sign_count)->toBe(2);
    })->with(['equal' => [0], 'below' => [1]]);

    it('accepts each challenge once, only in the context it was given and for 60 seconds', function (): void {
        enrolledDevice($this->phone, $this->ana->id);

        $answer = $this->phone->get($this->ceremony->assertionOptions('s'), $this->ana->id);
        $this->ceremony->verifyAssertion('s', $answer);
        expectRefused(fn () => $this->ceremony->verifyAssertion('s', $answer));

        $other = $this->phone->get($this->ceremony->assertionOptions('s'), $this->ana->id);
        expectRefused(fn () => $this->ceremony->verifyAssertion('another session', $other));

        $late = $this->phone->get($this->ceremony->assertionOptions('s'), $this->ana->id);
        $this->travel(61)->seconds();
        expectRefused(fn () => $this->ceremony->verifyAssertion('s', $late));
    });

    it('refuses a signature without verifying the person, or for another site or relying party', function (array $bend): void {
        enrolledDevice($this->phone, $this->ana->id);

        expectRefused(fn () => $this->ceremony->verifyAssertion('s', $this->phone->get($this->ceremony->assertionOptions('s'), $this->ana->id, ...$bend)));
    })->with([
        'not verified' => [['userVerified' => false]],
        'another origin' => [['origin' => 'https://evil.test']],
        'another relying party' => [['rpId' => 'evil.test']],
    ]);

    it('refuses a revoked device, a person who left, an unknown key and a key that claims another person', function (Closure $spoil): void {
        $device = enrolledDevice($this->phone, $this->ana->id);
        $handle = $spoil($device, $this->phone) ?? $this->ana->id;

        expectRefused(fn () => $this->ceremony->verifyAssertion('s', $this->phone->get($this->ceremony->assertionOptions('s'), $handle)));
    })->with([
        'revoked' => [fn (AttendanceDevice $device) => $device->update(['revoked_at' => now()]) ? null : null],
        'person left' => [fn (AttendanceDevice $device) => DB::table('employees')->where('id', $device->employee_id)->update(['deactivated_at' => now()]) > 0 ? null : null],
        'unknown key' => [function (AttendanceDevice $device, SoftAuthenticator $phone): null {
            $phone->credentialId = random_bytes(16);

            return null;
        }],
        'another person' => [fn (AttendanceDevice $device) => EmployeeFactory::new()->create(['tenant_id' => $device->tenant_id])->id],
    ]);

    it('does not verify a device of another company', function (): void {
        enrolledDevice($this->phone, $this->ana->id);
        workIn(Tenant::factory()->create());
        $ceremony = resolve(WebAuthnCeremony::class);

        expectRefused(fn () => $ceremony->verifyAssertion('s', $this->phone->get($ceremony->assertionOptions('s'), $this->ana->id)));
    });

    it('refuses anything that is not a signature, with the same answer', function (array $answer): void {
        $this->ceremony->assertionOptions('s');

        expectRefused(fn () => $this->ceremony->verifyAssertion('s', $answer));
    })->with([
        'nothing' => [[]],
        'ids that differ' => [['id' => 'AQ', 'rawId' => 'Ag', 'type' => 'public-key', 'response' => []]],
        'a broken signature' => [['id' => 'AQ', 'rawId' => 'AQ', 'type' => 'public-key', 'response' => ['clientDataJSON' => 'e30', 'authenticatorData' => 'AA', 'signature' => 'AA']]],
    ]);
});

it('writes nothing to the log, so no credential id, public key or challenge reaches it', function (): void {
    Log::spy();

    enrolledDevice($this->phone, $this->ana->id);
    $this->ceremony->verifyAssertion('s', $this->phone->get($this->ceremony->assertionOptions('s'), $this->ana->id));
    expectRefused(fn () => $this->ceremony->verifyAssertion('s', $this->phone->get($this->ceremony->assertionOptions('s'), $this->ana->id, tamper: true)));

    foreach (['debug', 'info', 'notice', 'warning', 'error', 'log'] as $level) {
        Log::shouldNotHaveReceived($level);
    }
});
