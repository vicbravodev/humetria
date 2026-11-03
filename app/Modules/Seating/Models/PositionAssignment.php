<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\PositionAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A person holding a standing position over a period. A person holds one at a time (a partial unique index); ending it
 * sets `ends_on` and keeps the history (criterio C-17).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $position_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'position_id', 'starts_on', 'ends_on'])]
#[UseFactory(PositionAssignmentFactory::class)]
final class PositionAssignment extends Model
{
    /** @use HasFactory<PositionAssignmentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }
}
