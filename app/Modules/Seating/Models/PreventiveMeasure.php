<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Seating\Enums\MeasureKind;
use App\Modules\Seating\Enums\MeasureStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\PreventiveMeasureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A measure of the action plan (Quinta G): what, for which position or assessment, who answers for it, by when and with what evidence. It is cancelled, never deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property string|null $assessment_id
 * @property string|null $position_id
 * @property MeasureKind $kind
 * @property string|null $note
 * @property MeasureStatus $status
 * @property int|null $owner_user_id
 * @property CarbonImmutable|null $due_on
 * @property string|null $evidence_path
 * @property int $proposed_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'assessment_id', 'position_id', 'kind', 'note', 'status', 'owner_user_id', 'due_on', 'evidence_path', 'proposed_by'])]
#[UseFactory(PreventiveMeasureFactory::class)]
final class PreventiveMeasure extends Model
{
    /** @use HasFactory<PreventiveMeasureFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => MeasureKind::class, 'status' => MeasureStatus::class, 'due_on' => 'immutable_date'];
    }
}
