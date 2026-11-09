<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\MatchesManyIds;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The link a person opens on their phone to link it (enrollment): good once and for the hours of config/attendance.php
 * (48 by default), and only the last one RH issued for them. Only the hash of its token is kept.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $token_hash
 * @property int $issued_by
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'token_hash', 'issued_by', 'expires_at', 'used_at', 'revoked_at'])]
#[Table('attendance_enrollment_links')]
final class EnrollmentLink extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids, MatchesManyIds;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The current company's link of `$token` while it may still be opened: not used, not replaced and within its hours. */
    public static function findPending(string $token): ?self
    {
        return self::query()->where('token_hash', self::hashToken($token))->whereNull('used_at')->whereNull('revoked_at')
            ->where('expires_at', '>', CarbonImmutable::now())->first();
    }

    /**
     * Of these people, the ones with a link that still opens (the same rule as findPending).
     *
     * @param  list<string>  $employeeIds
     * @return array<int, string>
     */
    public static function openAmong(array $employeeIds): array
    {
        return self::query()->among('employee_id', $employeeIds)->whereNull('used_at')->whereNull('revoked_at')
            ->where('expires_at', '>', CarbonImmutable::now())->get(['employee_id'])
            ->map(fn (self $link): string => $link->employee_id)->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_by' => 'integer', 'expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
