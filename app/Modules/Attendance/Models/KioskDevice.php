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
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'token_hash', 'activated_at', 'last_seen_at', 'revoked_at'])]
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

    /** The current company's activated screen whose cookie holds `$secret`, while RH has not revoked it. Never by its link. */
    public static function findActive(string $secret): ?self
    {
        return self::query()->where('token_hash', self::hashToken($secret))->whereNotNull('activated_at')->whereNull('revoked_at')->first();
    }

    /** How many screens of a center are in use: opened at least once and not revoked. */
    public static function inUseIn(string $workCenterId): int
    {
        return self::query()->where('work_center_id', $workCenterId)->whereNotNull('last_seen_at')->whereNull('revoked_at')->count();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['activated_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
