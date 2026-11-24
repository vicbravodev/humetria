<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\WorkerAccess\Contracts\DeviceKeySignatures;
use App\Modules\WorkerAccess\Data\DeviceSigned;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Verifies the device key of the current company (plan B of the offline marks). The pass says which key and whose; the key must be
 * the person's and not revoked at or before the ticket; the signature is checked over the 32 bytes of the challenge. It only reads:
 * nothing is written, not even a log, so no key, pass or signature can reach one.
 */
final readonly class CompanyDeviceKeySignatures implements DeviceKeySignatures
{
    public function __construct(private CurrentTenant $tenant) {}

    public function passId(string $pass): ?string
    {
        $read = OfflinePasses::read($pass);

        return $read !== null && $read['tenant'] === $this->tenant->id() ? $read['id'] : null;
    }

    public function verify(string $expected, string $signature, string $pass, CarbonImmutable $ticketIssuedAt): ?DeviceSigned
    {
        $read = OfflinePasses::read($pass);

        if ($read === null || $read['tenant'] !== $this->tenant->id()) {
            return null;
        }

        $device = WorkerDeviceKey::query()->whereKey($read['key'])
            ->where(fn (Builder $query) => $query->whereNull('revoked_at')->orWhere('revoked_at', '>', $ticketIssuedAt))
            ->first();

        if ($device === null || $device->employee_id !== $read['employee']) {
            return null;
        }

        $key = DeviceKeyCrypto::publicKeyFrom($device->public_key);

        if ($key === null || ! DeviceKeyCrypto::verifies($key, $expected, $signature)) {
            return null;
        }

        return new DeviceSigned($device->id, $device->employee_id, $read['id'], $read['issuedAt'], $read['expiresAt'], $device->revoked_at);
    }
}
