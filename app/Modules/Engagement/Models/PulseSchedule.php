<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Enums\PulseMode;
use Carbon\CarbonImmutable;
use Database\Factories\Engagement\PulseScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * When the pulse of a company goes out by itself. `next_mode` is what RH chose for the next one only; `announced_for`
 * is the run the heads-up email was already sent for.
 *
 * @property string $id
 * @property string $tenant_id
 * @property PulseFrequency $frequency
 * @property int $day_of_month
 * @property int $open_days
 * @property bool $active
 * @property CarbonImmutable $next_run_on
 * @property PulseMode|null $next_mode
 * @property CarbonImmutable|null $announced_for
 * @property int $created_by
 */
#[Fillable(['tenant_id', 'frequency', 'day_of_month', 'open_days', 'active', 'next_run_on', 'next_mode', 'announced_for', 'created_by'])] // @pest-mutate-ignore: RemoveArrayItem
#[Table('engagement_pulse_schedules')]
#[UseFactory(PulseScheduleFactory::class)]
final class PulseSchedule extends Model
{
    /** @use HasFactory<PulseScheduleFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => PulseFrequency::class,
            'day_of_month' => 'integer',
            'open_days' => 'integer',
            'active' => 'boolean',
            'next_run_on' => 'immutable_date',
            'next_mode' => PulseMode::class,
            'announced_for' => 'immutable_date',
        ];
    }
}
