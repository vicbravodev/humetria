<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\TrainingModality;
use App\Modules\Telework\Enums\TrainingTopic;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\TrainingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A training given at a work center: the day, the modality, the 8.1 topics it covered and its attendees. Never deleted:
 * it is the evidence of the yearly training (criterio C-24).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property CarbonImmutable $held_on
 * @property TrainingModality $modality
 * @property list<TrainingTopic> $topics
 * @property string|null $evidence_path
 * @property int $registered_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'held_on', 'modality', 'topics', 'evidence_path', 'registered_by'])]
#[UseFactory(TrainingFactory::class)]
final class Training extends Model
{
    /** @use HasFactory<TrainingFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return HasMany<TrainingAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(TrainingAttendee::class);
    }

    /**
     * The topics are stored as a list of enum values; they are read and written as enums (or their values).
     *
     * @return Attribute<list<TrainingTopic>, list<TrainingTopic|string>>
     */
    protected function topics(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): array => is_string($value) ? array_values(array_map(fn (mixed $topic): TrainingTopic => TrainingTopic::from(is_string($topic) ? $topic : ''), (array) json_decode($value, true))) : [],
            set: fn (array $topics): string => json_encode(array_map(fn (mixed $topic): string => $topic instanceof TrainingTopic ? $topic->value : (is_string($topic) ? $topic : ''), $topics), JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['held_on' => 'immutable_date', 'modality' => TrainingModality::class];
    }
}
