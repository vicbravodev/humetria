<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use OpenSSLAsymmetricKey;

/**
 * The math of the device key (plan B of the offline marks), with no database: the app makes an ECDSA P-256 key with WebCrypto and
 * signs with it; the server verifies with OpenSSL. WebCrypto gives the signature as r‖s (64 bytes); OpenSSL wants DER. The message
 * signed is always bytes: the 32 raw bytes of the challenge of an offline mark, or of the possession challenge at registration.
 * WebCrypto and `openssl_verify` each apply their own SHA-256 over those bytes, so they agree.
 */
final class DeviceKeyCrypto
{
    /**
     * The public key from its SPKI DER in base64url, or null when it is not a P-256 key (an RSA key, another curve or garbage).
     */
    public static function publicKeyFrom(string $spkiBase64Url): ?OpenSSLAsymmetricKey
    {
        $der = self::decode($spkiBase64Url);

        if ($der === null) {
            return null;
        }

        $key = @openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----');

        if ($key === false) {
            return null;
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false || ! is_array($details['ec'] ?? null)) {
            return null;
        }

        return ($details['type'] ?? null) === OPENSSL_KEYTYPE_EC && ($details['ec']['curve_name'] ?? null) === 'prime256v1' ? $key : null;
    }

    /**
     * `SEQUENCE { INTEGER r, INTEGER s }` from the 64 bytes r‖s: each integer without leading zeros and with a zero in front when
     * its high bit is set. Null when it is not exactly 64 bytes.
     */
    public static function derFromRaw(string $rs): ?string
    {
        if (strlen($rs) !== 64) {
            return null;
        }

        $body = self::integer(substr($rs, 0, 32)).self::integer(substr($rs, 32));

        return "\x30".pack('C', strlen($body)).$body;
    }

    /**
     * Whether `$rsBase64Url` (r‖s of 64 bytes) is the signature of `$message` by `$key`; false for anything that does not verify.
     */
    public static function verifies(OpenSSLAsymmetricKey $key, string $message, string $rsBase64Url): bool
    {
        $raw = self::decode($rsBase64Url);
        $der = $raw === null ? null : self::derFromRaw($raw);

        return $der !== null && openssl_verify($message, $der, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /** One DER INTEGER of a positive 32-byte value. */
    private static function integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\0");
        $bytes = $bytes === '' ? "\0" : $bytes;
        $bytes = ord($bytes[0]) >= 0x80 ? "\0".$bytes : $bytes;

        return "\x02".pack('C', strlen($bytes)).$bytes;
    }

    private static function decode(string $base64Url): ?string
    {
        if ($base64Url === '' || preg_match('/^[A-Za-z0-9_-]+$/', $base64Url) !== 1) {
            return null;
        }

        $bytes = base64_decode(strtr($base64Url, '-_', '+/'), true);

        return $bytes === false ? null : $bytes;
    }
}
