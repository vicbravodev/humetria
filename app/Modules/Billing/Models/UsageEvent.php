<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Billing\UsageEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What a company really used, once, by key: append-only (the database refuses to change or delete it). Never what was asked or answered.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $meter
 * @property CarbonImmutable $period_start
 * @property int $quantity
 * @property string $idempotency_key
 * @property CarbonImmutable $occurred_at
 */
#[Fillable(['tenant_id', 'meter', 'period_start', 'quantity', 'idempotency_key', 'occurred_at'])]
#[UseFactory(UsageEventFactory::class)]
final class UsageEvent extends Model
{
    /** @use HasFactory<UsageEventFactory> */
    use HasFactory, HasVersion4Uuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'occurred_at' => 'immutable_datetime'];
    }
}
