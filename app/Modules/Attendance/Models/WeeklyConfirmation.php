<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Support\WeekPeriod;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\WeeklyConfirmationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A person's answer about one week of their record (criterio 5.2): pending, confirmed or disputed with the reason, with the
 * moment and the device that signed it. Its current state only; the audit trail keeps the history. No hours are kept: they
 * are counted again from the marks every time.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property CarbonImmutable $week_start
 * @property WeekStatus $status
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $disputed_at
 * @property string|null $reason
 * @property string|null $device_id
 * @property string|null $digest
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'week_start', 'status', 'confirmed_at', 'disputed_at', 'reason', 'device_id', 'digest'])]
#[UseFactory(WeeklyConfirmationFactory::class)]
final class WeeklyConfirmation extends Model
{
    /** @use HasFactory<WeeklyConfirmationFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    protected $table = 'attendance_weekly_confirmations';

    protected $attributes = ['status' => 'pending'];

    /** The row of a person's week, created pending when there is none, locked until the transaction ends. */
    public static function lockedFor(string $tenantId, string $employeeId, WeekPeriod $week): self
    {
        $keys = ['employee_id' => $employeeId, 'week_start' => $week->start()->toDateString()];
        self::query()->createOrFirst($keys, ['tenant_id' => $tenantId]);

        return self::query()->where($keys)->lockForUpdate()->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['week_start' => 'immutable_date', 'status' => WeekStatus::class, 'confirmed_at' => 'immutable_datetime', 'disputed_at' => 'immutable_datetime'];
    }
}
