<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Interventions\ActionAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An area an action is for (criterio C-33). It points at the area only: never at people.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $action_id
 * @property string $area_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('intervention_action_areas')]
#[Fillable(['tenant_id', 'action_id', 'area_id'])]
#[UseFactory(ActionAreaFactory::class)]
final class ActionArea extends Model
{
    /** @use HasFactory<ActionAreaFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;
}
