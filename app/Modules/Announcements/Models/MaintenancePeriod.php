<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A time the whole platform was in maintenance: every company's panel reads but changes nothing. Platform data: no company owns it.
 *
 * @property string $id
 * @property string $reason
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['reason', 'started_at', 'ended_at'])]
final class MaintenancePeriod extends Model
{
    use HasVersion4Uuids;

    protected $table = 'platform_maintenance_periods';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
