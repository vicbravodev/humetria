<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\CredentialData;
use App\Modules\Attendance\Data\VerifiedAssertion;
use App\Modules\Attendance\Exceptions\InvalidCredential;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Closure;
use Cose\Algorithms;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * The WebAuthn ceremonies of a person's linked device, on web-auth/webauthn-lib (spec «Seguridad del enlace personal»).
 * A person has no account: their key is discoverable (it carries their id as its user handle) and is created on the
 * device itself (platform), with the person verified (fingerprint, face or PIN), so it cannot be copied or forwarded.
 *
 * Each challenge is random, kept server-side for 60 seconds under its company and its context (the person who
 * enrolls, or the session that marks), and accepted once. A signature is checked for the origin, the relying party, the
 * person verified, the signature itself and a counter that only grows (when the authenticator keeps one), and its
 * device must not be revoked nor its person gone. Every failure gives the same InvalidCredential, and nothing here logs:
 * no credential id, public key or challenge reaches a log.
 */
final readonly class WebAuthnCeremony
{
    private const int TTL_SECONDS = 60;

    private const string RP_NAME = 'Humetría';

    public function __construct(private CurrentTenant $tenant, private Roster $roster) {}

    /**
     * The options for `navigator.credentials.create()` of a person's new key, as JSON for the browser.
     *
     * @return array<mixed>
     */
    public function registrationOptions(string $employeeId, string $name): array
    {
        $options = $this->creationOptions($employeeId, $name, random_bytes(32));
        $this->remember("register:{$employeeId}", $options->challenge);

        return $this->toBrowser($options);
    }

    /**
     * The new key a person's device answered with, verified against a challenge given to that person.
     *
     * @param  array<mixed>  $response  the browser's PublicKeyCredential as JSON
     *
     * @throws InvalidCredential for any answer that does not verify
     */
    public function verifyRegistration(string $employeeId, array $response): CredentialData
    {
        $attestation = $this->credential($response)->response;

        if (! $attestation instanceof AuthenticatorAttestationResponse) {
            throw new InvalidCredential;
        }

        $challenge = $attestation->clientDataJSON->challenge;
        $this->consume("register:{$employeeId}", $challenge);

        $record = self::verified(fn (): CredentialRecord => AuthenticatorAttestationResponseValidator::create($this->steps()->creationCeremony())
            ->check($attestation, $this->creationOptions($employeeId, $employeeId, $challenge), $this->rpId()));

        return new CredentialData(self::encode($record->publicKeyCredentialId), self::encode($record->credentialPublicKey), $record->counter);
    }

    /**
     * The options for `navigator.credentials.get()`: any key of this site, the person verified, no username. `$context`
     * ties the challenge to whoever asked (the session that marks).
     *
     * @return array<mixed>
     */
    public function assertionOptions(string $context): array
    {
        $options = $this->requestOptions(random_bytes(32));
        $this->remember("assert:{$context}", $options->challenge);

        return $this->toBrowser($options);
    }

    /**
     * Whose device signed, after verifying the signature against a challenge given in `$context`; the device's counter
     * and last use move on.
     *
     * @param  array<mixed>  $response  the browser's PublicKeyCredential as JSON
     *
     * @throws InvalidCredential for any answer that does not verify
     */
    public function verifyAssertion(string $context, array $response): VerifiedAssertion
    {
        $credential = $this->credential($response);
        $assertion = $credential->response;

        if (! $assertion instanceof AuthenticatorAssertionResponse) {
            throw new InvalidCredential;
        }

        $challenge = $assertion->clientDataJSON->challenge;
        $this->consume("assert:{$context}", $challenge);

        return DB::transaction(function () use ($credential, $assertion, $challenge): VerifiedAssertion {
            // Locked: two signatures of the same key at once are checked one after the other against the counter.
            $device = AttendanceDevice::query()->where('credential_id', self::encode($credential->rawId))->whereNull('revoked_at')->lockForUpdate()->first();
            $person = $device === null ? null : $this->roster->find($device->employee_id);

            if ($device === null || $person === null || ! $person->active) {
                throw new InvalidCredential;
            }

            $record = self::verified(fn (): CredentialRecord => AuthenticatorAssertionResponseValidator::create($this->steps()->requestCeremony())
                ->check($this->record($device), $assertion, $this->requestOptions($challenge), $this->rpId(), null));

            $device->update(['sign_count' => $record->counter, 'last_used_at' => CarbonImmutable::now()]);

            return new VerifiedAssertion($device->id, $device->employee_id);
        });
    }

    private function creationOptions(string $employeeId, string $name, string $challenge): PublicKeyCredentialCreationOptions
    {
        $type = PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY;

        return PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create(self::RP_NAME, $this->rpId()),
            // The person's id is the user handle: a later signature names whose key it is, with no username.
            user: PublicKeyCredentialUserEntity::create($name, $employeeId, $name),
            challenge: $challenge,
            pubKeyCredParams: [
                PublicKeyCredentialParameters::create($type, Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::create($type, Algorithms::COSE_ALGORITHM_RS256),
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            timeout: self::TTL_SECONDS * 1000,
        );
    }

    private function requestOptions(string $challenge): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->rpId(),
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::TTL_SECONDS * 1000,
        );
    }

    /** The device's key as the library checks it: no attestation kept, the person's id as its user handle. */
    private function record(AttendanceDevice $device): CredentialRecord
    {
        return CredentialRecord::create(
            publicKeyCredentialId: self::decode($device->credential_id),
            type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            transports: [],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: self::decode($device->public_key),
            userHandle: $device->employee_id,
            counter: $device->sign_count,
        );
    }

    private function remember(string $context, string $challenge): void
    {
        Cache::put($this->key($context, $challenge), true, self::TTL_SECONDS);
    }

    /**
     * Takes the challenge, once: `add` writes only where nothing is (atomic in every cache store), so of two answers with
     * the same challenge only the first passes. Both entries lapse on their own after the 60 seconds.
     *
     * @throws InvalidCredential when the challenge was not given in this context, expired or was used
     */
    private function consume(string $context, string $challenge): void
    {
        $key = $this->key($context, $challenge);

        if (! Cache::has($key) || ! Cache::add("{$key}:used", true, self::TTL_SECONDS)) {
            throw new InvalidCredential;
        }
    }

    private function key(string $context, string $challenge): string
    {
        return 'attendance-webauthn:'.$this->tenant->id().':'.hash('sha256', $context).':'.hash('sha256', $challenge);
    }

    /**
     * @param  array<mixed>  $response
     *
     * @throws InvalidCredential
     */
    private function credential(array $response): PublicKeyCredential
    {
        try {
            return $this->serializer()->denormalize($response, PublicKeyCredential::class, 'json');
        } catch (Throwable) {
            throw new InvalidCredential;
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $check
     * @return T
     *
     * @throws InvalidCredential whatever the library threw, so the answer never tells which check failed
     */
    private static function verified(Closure $check): mixed
    {
        try {
            return $check();
        } catch (Throwable) {
            throw new InvalidCredential;
        }
    }

    /**
     * @return array<mixed>
     */
    private function toBrowser(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): array
    {
        $json = $this->serializer()->normalize($options, 'json', [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);

        return is_array($json) ? $json : [];
    }

    private function steps(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory;
        $factory->setAllowedOrigins(array_values(array_filter(config()->array('fortify.passkeys.allowed_origins'), is_string(...))));

        return $factory;
    }

    private function serializer(): Serializer
    {
        $serializer = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create([NoneAttestationStatementSupport::create()])))->create();

        return $serializer instanceof Serializer ? $serializer : throw new InvalidCredential;
    }

    private function rpId(): string
    {
        return config()->string('fortify.passkeys.relying_party_id');
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $base64url): string
    {
        return base64_decode(strtr($base64url, '-_', '+/'), true) ?: '';
    }
}
