<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\MatchesManyIds;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\AttendanceDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A person's linked device: a WebAuthn key (passkey) of the person, with which they mark (spec «Seguridad del enlace
 * personal»). A synced passkey (backup_eligible) lives in the person's passkey account, not on one device only. `credential_id` and `public_key` are base64url; they identify the key and never reach a page, a log
 * or the audit trail. RH revokes a device and it stops verifying at once.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $credential_id
 * @property string $public_key
 * @property int $sign_count
 * @property bool $backup_eligible
 * @property bool $backup_state
 * @property string $label
 * @property CarbonImmutable $enrolled_at
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'credential_id', 'public_key', 'sign_count', 'backup_eligible', 'backup_state', 'label', 'enrolled_at', 'last_used_at', 'revoked_at'])]
#[Hidden(['credential_id', 'public_key'])]
#[UseFactory(AttendanceDeviceFactory::class)]
final class AttendanceDevice extends Model
{
    /** @use HasFactory<AttendanceDeviceFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, MatchesManyIds;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sign_count' => 'integer', 'backup_eligible' => 'boolean', 'backup_state' => 'boolean', 'enrolled_at' => 'immutable_datetime', 'last_used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
