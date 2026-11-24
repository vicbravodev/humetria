<?php

declare(strict_types=1);

namespace Tests\Support;

use OpenSSLAsymmetricKey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;

/**
 * The device key of the app (plan B) in software, for the tests: an EC P-256 key in memory that signs as WebCrypto does. The public
 * key goes out as SPKI in base64url and a signature as the 64 bytes r‖s in base64url, which is what the app sends (WebCrypto
 * gives r‖s, not the DER `openssl_sign` writes).
 */
final class SoftDeviceKey
{
    private OpenSSLAsymmetricKey $key;

    public function __construct()
    {
        $this->key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]) ?: throw new RuntimeException('No EC key.');
    }

    /** The public key, SPKI DER in base64url. */
    public function publicKey(): string
    {
        $details = openssl_pkey_get_details($this->key) ?: throw new RuntimeException('No key details.');
        $pem = is_string($details['key'] ?? null) ? $details['key'] : throw new RuntimeException('No public key.');

        return Base64UrlSafe::encodeUnpadded((string) base64_decode(preg_replace('/-----[A-Z ]+-----|\s/', '', $pem) ?? '', true));
    }

    /** The signature of `$bytes`, r‖s of 64 bytes in base64url. */
    public function sign(string $bytes): string
    {
        openssl_sign($bytes, $der, $this->key, OPENSSL_ALGO_SHA256) || throw new RuntimeException('Not signed.');

        return Base64UrlSafe::encodeUnpadded(self::rawFromDer($der));
    }

    /** SEQUENCE { INTEGER r, INTEGER s } as the 64 bytes r‖s, each left-padded with zeros to 32. */
    private static function rawFromDer(string $der): string
    {
        $offset = 3;
        $parts = [];

        foreach ([0, 1] as $ignored) {
            $length = ord($der[$offset]);
            $parts[] = str_pad(ltrim(substr($der, $offset + 1, $length), "\0"), 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $parts[0].$parts[1];
    }
}
