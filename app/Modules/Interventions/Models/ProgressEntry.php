<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Interventions\Enums\ActionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Interventions\ProgressEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One step an action took (num. 8.4 d): the day, the state it moved to, a note and a file of evidence. The log is insert-only
 * (criterio C-35): a row is never edited or deleted. It carries the day, not the hour.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $action_id
 * @property CarbonImmutable $recorded_on
 * @property ActionStatus $status
 * @property string|null $note
 * @property string|null $evidence_path
 * @property int $recorded_by
 * @property CarbonImmutable $created_at
 */
#[Table('intervention_progress_entries')]
#[Fillable(['tenant_id', 'action_id', 'recorded_on', 'status', 'note', 'evidence_path', 'recorded_by'])]
#[UseFactory(ProgressEntryFactory::class)]
final class ProgressEntry extends Model
{
    /** @use HasFactory<ProgressEntryFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public const ?string UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('The progress log is insert-only.'));
        self::deleting(fn (): never => throw new LogicException('The progress log is insert-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['recorded_on' => 'immutable_date', 'status' => ActionStatus::class];
    }
}
