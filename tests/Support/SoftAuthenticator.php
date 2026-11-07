<?php

declare(strict_types=1);

namespace Tests\Support;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use OpenSSLAsymmetricKey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;

/**
 * A platform authenticator in software, for the tests: an ES256 key and a counter, answering a server's options with the
 * standard WebAuthn JSON a browser sends (`PublicKeyCredential.toJSON()`), encoded with the WebAuthn library's own CBOR. The
 * package ships no fixed vectors, so these are produced here, and each test can bend one field of a genuine answer
 * (the origin, the flags, the counter, the signature) to see the server refuse it.
 */
final class SoftAuthenticator
{
    private const int FLAG_USER_PRESENT = 0x01;

    private const int FLAG_USER_VERIFIED = 0x04;

    private const int FLAG_BACKUP_ELIGIBLE = 0x08;

    private const int FLAG_BACKED_UP = 0x10;

    private const int FLAG_ATTESTED_DATA = 0x40;

    public string $credentialId;

    public int $counter = 0;

    /** A synced passkey (iCloud Keychain, Google Password Manager) is backup eligible, and backed up once synced. */
    public bool $backupEligible = false;

    public bool $backedUp = false;

    private OpenSSLAsymmetricKey $key;

    public function __construct(public string $origin = 'http://localhost', public string $rpId = 'localhost', public bool $countsSignatures = true)
    {
        $this->credentialId = random_bytes(16);
        $this->key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]) ?: throw new RuntimeException('No EC key.');
    }

    /**
     * The answer to `navigator.credentials.create()` with `$options` (as the server sent them).
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function create(array $options, bool $userVerified = true, ?string $origin = null, ?string $rpId = null, ?string $challenge = null): array
    {
        $clientData = $this->clientData('webauthn.create', $challenge ?? self::challengeOf($options), $origin);
        $authData = $this->authData($rpId, $userVerified, self::FLAG_ATTESTED_DATA)
            .str_repeat("\0", 16)
            .pack('n', strlen($this->credentialId)).$this->credentialId
            .$this->coseKey();

        $attestation = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return $this->credential([
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientData),
            'attestationObject' => Base64UrlSafe::encodeUnpadded((string) $attestation),
            'transports' => ['internal'],
        ]);
    }

    /**
     * The answer to `navigator.credentials.get()` with `$options`, the counter moved on as a real authenticator does.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function get(array $options, string $userHandle, bool $userVerified = true, ?string $origin = null, ?string $rpId = null, ?string $challenge = null, bool $tamper = false): array
    {
        if ($this->countsSignatures) {
            $this->counter++;
        }

        $clientData = $this->clientData('webauthn.get', $challenge ?? self::challengeOf($options), $origin);
        $authData = $this->authData($rpId, $userVerified, 0);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256) || throw new RuntimeException('Not signed.');

        if ($tamper) {
            // One bit inside the signature's r value (past its DER header): a well-formed signature that does not match.
            $signature[10] = chr(ord($signature[10]) ^ 0x01);
        }

        return $this->credential([
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientData),
            'authenticatorData' => Base64UrlSafe::encodeUnpadded($authData),
            'signature' => Base64UrlSafe::encodeUnpadded($signature),
            'userHandle' => Base64UrlSafe::encodeUnpadded($userHandle),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function challengeOf(array $options): string
    {
        return is_string($options['challenge'] ?? null) ? $options['challenge'] : throw new RuntimeException('No challenge.');
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function credential(array $response): array
    {
        $id = Base64UrlSafe::encodeUnpadded($this->credentialId);

        return ['id' => $id, 'rawId' => $id, 'type' => 'public-key', 'response' => $response, 'clientExtensionResults' => [], 'authenticatorAttachment' => 'platform'];
    }

    private function clientData(string $type, string $challenge, ?string $origin): string
    {
        return json_encode(['type' => $type, 'challenge' => $challenge, 'origin' => $origin ?? $this->origin, 'crossOrigin' => false], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function authData(?string $rpId, bool $userVerified, int $flags): string
    {
        $flags |= self::FLAG_USER_PRESENT | ($userVerified ? self::FLAG_USER_VERIFIED : 0)
            | ($this->backupEligible ? self::FLAG_BACKUP_ELIGIBLE : 0) | ($this->backedUp ? self::FLAG_BACKED_UP : 0);

        return hash('sha256', $rpId ?? $this->rpId, true).chr($flags).pack('N', $this->counter);
    }

    /** The public key as COSE: EC2, ES256, P-256, x and y. */
    private function coseKey(): string
    {
        $details = openssl_pkey_get_details($this->key) ?: throw new RuntimeException('No key details.');
        $point = fn (string $coordinate): string => str_pad($details['ec'][$coordinate], 32, "\0", STR_PAD_LEFT);

        return (string) MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($point('x')))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($point('y')));
    }
}
