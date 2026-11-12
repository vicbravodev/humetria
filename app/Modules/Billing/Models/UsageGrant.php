<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Billing\UsageGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Extra quota the superadmin gave a company for one month, with the reason.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $meter
 * @property CarbonImmutable $period_start
 * @property int $quantity
 * @property string $reason
 * @property int|null $granted_by
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'meter', 'period_start', 'quantity', 'reason', 'granted_by', 'created_at'])]
#[UseFactory(UsageGrantFactory::class)]
final class UsageGrant extends Model
{
    /** @use HasFactory<UsageGrantFactory> */
    use HasFactory, HasVersion4Uuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'created_at' => 'immutable_datetime'];
    }
}
