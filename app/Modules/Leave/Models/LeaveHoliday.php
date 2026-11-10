<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeaveHolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A day the company does not work besides those of LFT art. 74 (criterio C-04): it is not counted as a working day of a
 * request made from then on.
 *
 * @property string $id
 * @property string $tenant_id
 * @property CarbonImmutable $date
 * @property string $name
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'date', 'name'])]
#[UseFactory(LeaveHolidayFactory::class)]
final class LeaveHoliday extends Model
{
    /** @use HasFactory<LeaveHolidayFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }
}
