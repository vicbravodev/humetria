<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * What a phone does with a push: reads the body of an `aes128gcm` message (RFC 8188 with the keys of RFC 8291) with the private key
 * and the secret of its subscription. A test that sends a notice reads it back here, so «it is encrypted» and «the phone can read it»
 * are one check.
 */
final class PushPayload
{
    /** @param array{public: string, private: string, auth: string} $phone the keys of `PushSubscriptionFactory::keyPair()` */
    public static function read(string $body, array $phone): string
    {
        $salt = substr($body, 0, 16);
        $idLength = ord($body[20]);
        $sender = substr($body, 21, $idLength);
        $ciphertext = substr($body, 21 + $idLength);
        $own = self::unurl($phone['public']);
        $auth = self::unurl($phone['auth']);

        $shared = openssl_pkey_derive(
            openssl_pkey_get_public(self::pem($sender)) ?: throw new RuntimeException('sender key'),
            openssl_pkey_get_private(self::privatePem($phone['private'], $own)) ?: throw new RuntimeException('own key'),
        );
        $ikm = hash_hkdf('sha256', (string) $shared, 32, "WebPush: info\0".$own.$sender, $auth);
        $key = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt(substr($ciphertext, 0, -16), 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $nonce, substr($ciphertext, -16));

        return rtrim($plain === false ? throw new RuntimeException('the notice does not decrypt with these keys') : $plain, "\0\x02");
    }

    private static function unurl(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }

    /** The SEC1 PEM of a P-256 private key from its raw 32-byte scalar and its public point. */
    private static function privatePem(string $scalar, string $point): string
    {
        $der = hex2bin('30770201010420').self::unurl($scalar).hex2bin('a00a06082a8648ce3d030107a144034200').$point;

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode((string) $der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }

    /** The SPKI PEM of a raw uncompressed P-256 point. */
    private static function pem(string $point): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$point;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode((string) $der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }
}
