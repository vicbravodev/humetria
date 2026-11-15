<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Billing\UsageCounterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What a company has used and holds reserved of a meter in one month. A reservation moves it with a single conditional UPDATE.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $meter
 * @property CarbonImmutable $period_start
 * @property int $used
 * @property int $reserved
 * @property int|null $quota
 */
#[Fillable(['tenant_id', 'meter', 'period_start', 'used', 'reserved', 'quota'])]
#[UseFactory(UsageCounterFactory::class)]
final class UsageCounter extends Model
{
    /** @use HasFactory<UsageCounterFactory> */
    use HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['period_start' => 'immutable_date'];
    }
}
