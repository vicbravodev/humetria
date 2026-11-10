<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Billing\UsageReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Quantity held for one call before it is made: reserved until it is settled with the real figure, released or expired.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $meter
 * @property CarbonImmutable $period_start
 * @property string $idempotency_key
 * @property int $quantity
 * @property string $state
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['tenant_id', 'meter', 'period_start', 'idempotency_key', 'quantity', 'state', 'expires_at'])]
#[UseFactory(UsageReservationFactory::class)]
final class UsageReservation extends Model
{
    /** @use HasFactory<UsageReservationFactory> */
    use HasFactory, HasVersion4Uuids;

    public const string RESERVED = 'reserved';

    public const string SETTLED = 'settled';

    public const string RELEASED = 'released';

    public const string EXPIRED = 'expired';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'expires_at' => 'immutable_datetime'];
    }
}
