<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\SearchesText;
use App\Modules\Instruments\Data\SeatPlacement;
use App\Modules\Seating\Enums\BipedestationType;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\StandingPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A position where people work standing, with the answers of Figura 1 and the result that follows from them. The result
 * (`placement`) and `figure_one_applies` are stored calculated, and the database refuses a row where they disagree with the
 * answers. `placement` is null only for an industrial position whose nature does not allow seating.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property string $name
 * @property string $tasks
 * @property BipedestationType $bipedestation_type
 * @property int $continuous_minutes
 * @property bool $can_work_seated
 * @property bool $has_space_for_seat
 * @property bool $needs_to_be_nearby
 * @property SeatPlacement|null $placement
 * @property bool $figure_one_applies
 * @property int $max_people_at_once
 * @property array<string, string|null> $area_conditions
 * @property string|null $tools
 * @property string|null $physical_load
 * @property string|null $attention_level
 * @property int|null $default_standing_time
 * @property int|null $default_posture_change
 * @property int|null $default_surface
 * @property int|null $default_movement_space
 * @property int|null $default_pauses
 * @property bool|null $nature_allows
 * @property string|null $nature_reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'name', 'tasks', 'bipedestation_type', 'continuous_minutes', 'can_work_seated', 'has_space_for_seat', 'needs_to_be_nearby', 'placement', 'figure_one_applies', 'max_people_at_once', 'area_conditions', 'tools', 'physical_load', 'attention_level', 'default_standing_time', 'default_posture_change', 'default_surface', 'default_movement_space', 'default_pauses', 'nature_allows', 'nature_reason'])]
#[UseFactory(StandingPositionFactory::class)]
final class StandingPosition extends Model
{
    /** @use HasFactory<StandingPositionFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, SearchesText;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bipedestation_type' => BipedestationType::class,
            'placement' => SeatPlacement::class,
            'can_work_seated' => 'boolean',
            'has_space_for_seat' => 'boolean',
            'needs_to_be_nearby' => 'boolean',
            'figure_one_applies' => 'boolean',
            'nature_allows' => 'boolean',
            'area_conditions' => 'array',
        ];
    }
}
