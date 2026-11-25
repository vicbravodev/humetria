<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\WorkerAccess\WorkerDeviceKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The device key of a person's phone (plan B of the offline marks): an ECDSA P-256 key the app made and keeps, not extractable,
 * registered online after a recent proof. Up to three in use per person. `public_key` is SPKI in base64url and never reaches a
 * page, a log or the audit trail. «Reissue», «No fui yo», a replacement by a fourth key and the person leaving the company revoke
 * it (`revoked_reason`); «Salir» does not.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $public_key
 * @property string $label
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoked_reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'public_key', 'label', 'last_used_at', 'revoked_at', 'revoked_reason'])]
#[Hidden(['public_key'])]
#[UseFactory(WorkerDeviceKeyFactory::class)]
final class WorkerDeviceKey extends Model
{
    /** @use HasFactory<WorkerDeviceKeyFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * Revokes every device key in use of a person, now, for `$reason`; one revoked before keeps its reason and date.
     *
     * @return int how many were revoked
     */
    public static function revokeAllOf(string $employeeId, string $reason): int
    {
        return self::query()->where('employee_id', $employeeId)->whereNull('revoked_at')
            ->update(['revoked_at' => CarbonImmutable::now(), 'revoked_reason' => $reason]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
