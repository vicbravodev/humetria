<?php

declare(strict_types=1);

use App\Modules\WorkerAccess\Support\DeviceKeyCrypto;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Support\SoftDeviceKey;
use Tests\TestCase;

uses(TestCase::class);

mutates(DeviceKeyCrypto::class);

/**
 * The device key of the app (plan B) signs with WebCrypto: ECDSA P-256, the signature as r‖s of 64 bytes. The server verifies it with
 * OpenSSL, which wants it in DER. tests/Fixtures/worker-access/device-key-vectors.json holds signatures WebCrypto made (the app's
 * tests read the same file).
 */
dataset('device key vectors', function (): array {
    $fixture = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/worker-access/device-key-vectors.json'), true, flags: JSON_THROW_ON_ERROR);

    return collect($fixture['vectors'])->mapWithKeys(fn (array $vector): array => [$vector['name'] => [$vector]])->all();
});

/** An SPKI public key of another curve or type, in base64url. */
function spkiOf(array $options): string
{
    $key = openssl_pkey_new($options) ?: throw new RuntimeException('No key.');
    $pem = (string) (openssl_pkey_get_details($key)['key'] ?? '');

    return Base64UrlSafe::encodeUnpadded((string) base64_decode((string) preg_replace('/-----[A-Z ]+-----|\s/', '', $pem), true));
}

it('verifies the signatures WebCrypto made, and not the altered one', function (array $vector): void {
    $key = DeviceKeyCrypto::publicKeyFrom($vector['spki']);

    expect($key)->not->toBeNull()
        ->and(DeviceKeyCrypto::verifies($key, (string) hex2bin($vector['message']), $vector['signature']))->toBe($vector['valid']);
})->with('device key vectors');

it('writes a DER integer with a zero in front when its high bit is set, and without leading zeros', function (): void {
    $r = str_repeat("\x80", 32);
    $s = "\x00\x00".str_repeat("\x01", 30);

    expect(bin2hex((string) DeviceKeyCrypto::derFromRaw($r.$s)))
        ->toBe('3043'.'0221'.'00'.str_repeat('80', 32).'021e'.str_repeat('01', 30));
});

it('keeps a zero byte that is not a leading one, and the single byte of a zero integer', function (): void {
    $r = "\x01\x00".str_repeat("\x02", 30);
    $zero = str_repeat("\x00", 32);

    expect(bin2hex((string) DeviceKeyCrypto::derFromRaw($r.$zero)))->toBe('3025'.'0220'.'0100'.str_repeat('02', 30).'0201'.'00');
});

it('refuses a signature that is not exactly 64 bytes', function (int $length): void {
    expect(DeviceKeyCrypto::derFromRaw(str_repeat("\x01", $length)))->toBeNull();
})->with(['63' => [63], '65' => [65], 'empty' => [0]]);

it('only takes a P-256 key: an RSA key, a P-384 key and garbage give null', function (): void {
    expect(DeviceKeyCrypto::publicKeyFrom(spkiOf(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048])))->toBeNull()
        ->and(DeviceKeyCrypto::publicKeyFrom(spkiOf(['curve_name' => 'secp384r1', 'private_key_type' => OPENSSL_KEYTYPE_EC])))->toBeNull()
        ->and(DeviceKeyCrypto::publicKeyFrom(Base64UrlSafe::encodeUnpadded(random_bytes(91))))->toBeNull()
        ->and(DeviceKeyCrypto::publicKeyFrom('not base64url!'))->toBeNull()
        ->and(DeviceKeyCrypto::publicKeyFrom(''))->toBeNull();
});

it('goes round with the key of the tests, and refuses another message and another key', function (): void {
    $phone = new SoftDeviceKey;
    $message = random_bytes(32);
    $signature = $phone->sign($message);
    $key = DeviceKeyCrypto::publicKeyFrom($phone->publicKey());

    expect($key)->not->toBeNull()
        ->and(strlen(Base64UrlSafe::decodeNoPadding($signature)))->toBe(64)
        ->and(DeviceKeyCrypto::verifies($key, $message, $signature))->toBeTrue()
        ->and(DeviceKeyCrypto::verifies($key, random_bytes(32), $signature))->toBeFalse()
        ->and(DeviceKeyCrypto::verifies(DeviceKeyCrypto::publicKeyFrom((new SoftDeviceKey)->publicKey()) ?? $key, $message, $signature))->toBeFalse();
});

it('answers false, never an exception, for a signature that is not base64url or not 64 bytes', function (string $signature): void {
    $phone = new SoftDeviceKey;
    $key = DeviceKeyCrypto::publicKeyFrom($phone->publicKey());

    expect($key)->not->toBeNull()
        ->and(DeviceKeyCrypto::verifies($key, random_bytes(32), $signature))->toBeFalse();
})->with(['not base64url' => ['***'], 'short' => ['AAAA'], 'empty' => ['']]);
