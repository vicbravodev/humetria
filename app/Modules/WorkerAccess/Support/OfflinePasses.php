<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Throwable;

/**
 * The pass of the device key (plan B of the offline marks), with no table: `v1.{payload}.{hmac}`. The payload is the base64url of
 * a JSON with the company (`t`), the person (`e`), the key (`k`), the id of this pass (`i`, a random uuid) and when it was issued
 * (`n`) and expires (`x`), in Unix seconds. The HMAC-SHA256 is under its own domain (`worker-access-offline-pass|v1.`) and is made
 * with `APP_KEY` and checked also with every key of `APP_PREVIOUS_KEYS`, so rotating `APP_KEY` with the old one kept there keeps the
 * pending passes. The pass vouches that the phone was registered and for whom; it does not decide whether it is still valid: that
 * is for Attendance, with the declared time of each mark.
 */
final readonly class OfflinePasses
{
    private const string DOMAIN = 'worker-access-offline-pass|';

    private const int MAX_LENGTH = 512;

    /**
     * @return array{pass: string, passId: string, expiresAt: CarbonImmutable}
     */
    public static function issue(WorkerDeviceKey $key): array
    {
        $issuedAt = CarbonImmutable::now();
        $expiresAt = $issuedAt->addHours(config()->integer('worker_access.pass_hours'));
        $passId = Str::uuid()->toString();
        $payload = Base64UrlSafe::encodeUnpadded(json_encode([
            't' => $key->tenant_id, 'e' => $key->employee_id, 'k' => $key->id, 'i' => $passId,
            'n' => $issuedAt->getTimestamp(), 'x' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return ['pass' => "v1.{$payload}.".self::sign($payload, self::keys()[0] ?? ''), 'passId' => $passId, 'expiresAt' => $expiresAt];
    }

    /**
     * What a pass says, or null when it is not a pass of this app: badly formed, another version, an HMAC no key of ours makes, or
     * missing a field. It does not compare with now.
     *
     * @return array{tenant: string, employee: string, key: string, id: string, issuedAt: CarbonImmutable, expiresAt: CarbonImmutable}|null
     */
    public static function read(string $pass): ?array
    {
        $parts = explode('.', $pass);

        if (strlen($pass) > self::MAX_LENGTH || count($parts) !== 3 || $parts[0] !== 'v1' || ! self::signedByOurKey($parts[1], $parts[2])) {
            return null;
        }

        try {
            $data = json_decode(Base64UrlSafe::decodeNoPadding($parts[1]), true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($data) || ! is_string($data['t'] ?? null) || ! is_string($data['e'] ?? null) || ! is_string($data['k'] ?? null) || ! is_string($data['i'] ?? null) || ! is_int($data['n'] ?? null) || ! is_int($data['x'] ?? null)) {
            return null;
        }

        return [
            'tenant' => $data['t'], 'employee' => $data['e'], 'key' => $data['k'], 'id' => $data['i'],
            'issuedAt' => CarbonImmutable::createFromTimestampUTC($data['n']), 'expiresAt' => CarbonImmutable::createFromTimestampUTC($data['x']),
        ];
    }

    private static function signedByOurKey(string $payload, string $hmac): bool
    {
        foreach (self::keys() as $key) {
            if (hash_equals(self::sign($payload, $key), $hmac)) {
                return true;
            }
        }

        return false;
    }

    private static function sign(string $payload, string $key): string
    {
        return Base64UrlSafe::encodeUnpadded(hash_hmac('sha256', self::DOMAIN."v1.{$payload}", $key, true));
    }

    /**
     * The keys that sign or check a pass: the app key first, then the previous ones (the pattern of `PageLink::keys()`).
     *
     * @return list<string>
     */
    private static function keys(): array
    {
        return array_values(array_filter([config()->string('app.key'), ...config()->array('app.previous_keys')], is_string(...)));
    }
}
