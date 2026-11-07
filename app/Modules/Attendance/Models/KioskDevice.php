<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\KioskDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The QR screen of a work center: a fixed tablet whose token (shown once to RH, only its hash kept) authorizes it to show
 * the center's rotating code. Not a person's device: those enroll with WebAuthn.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property string $token_hash
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'token_hash', 'last_seen_at', 'revoked_at'])]
#[Table('attendance_kiosk_devices')]
#[UseFactory(KioskDeviceFactory::class)]
final class KioskDevice extends Model
{
    /** @use HasFactory<KioskDeviceFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The current company's screen of `$token`, while RH has not revoked it. */
    public static function findActive(string $token): ?self
    {
        return self::query()->where('token_hash', self::hashToken($token))->whereNull('revoked_at')->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_seen_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
