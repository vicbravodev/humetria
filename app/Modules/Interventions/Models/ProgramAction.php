<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Enums\Professional;
use Carbon\CarbonImmutable;
use Database\Factories\Interventions\ProgramActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An action of the Programa (num. 8.4 and 8.5): what, at which level, for the whole center or for areas, from when to when,
 * who answers for it and where it stands. A third-level action names the kind of professional who gives it. It is cancelled
 * with a reason, never deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $program_id
 * @property string $item_id
 * @property ActionLevel $level
 * @property ActionKind $kind
 * @property string $title
 * @property string $description
 * @property ActionSubjects $subjects
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable $due_on
 * @property string $responsible_name
 * @property int|null $responsible_user_id
 * @property Professional|null $professional
 * @property ActionStatus $status
 * @property CarbonImmutable|null $completed_on
 * @property string|null $cancelled_reason
 * @property int $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('intervention_actions')]
#[Fillable(['tenant_id', 'program_id', 'item_id', 'level', 'kind', 'title', 'description', 'subjects', 'starts_on', 'due_on', 'responsible_name', 'responsible_user_id', 'professional', 'status', 'completed_on', 'cancelled_reason', 'created_by'])]
#[UseFactory(ProgramActionFactory::class)]
final class ProgramAction extends Model
{
    /** @use HasFactory<ProgramActionFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<ProgramItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ProgramItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return HasMany<ActionArea, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(ActionArea::class, 'action_id');
    }

    /**
     * @return HasMany<ProgressEntry, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(ProgressEntry::class, 'action_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ActionLevel::class,
            'kind' => ActionKind::class,
            'subjects' => ActionSubjects::class,
            'professional' => Professional::class,
            'status' => ActionStatus::class,
            'starts_on' => 'immutable_date',
            'due_on' => 'immutable_date',
            'completed_on' => 'immutable_date',
        ];
    }
}
