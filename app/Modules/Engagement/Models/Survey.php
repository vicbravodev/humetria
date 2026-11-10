<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Exceptions\SurveyStateChanged;
use App\Modules\Engagement\Policies\SurveyPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\Engagement\SurveyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A climate or pulse survey of the company. The questions it was launched with are kept as refs of the item bank, so
 * rewording an item later never changes what was asked.
 *
 * @property string $id
 * @property string $tenant_id
 * @property SurveyKind $kind
 * @property SurveyStatus $status
 * @property string $name
 * @property string $bank_version
 * @property list<string> $items
 * @property CarbonImmutable|null $closes_on
 * @property CarbonImmutable|null $launched_at
 * @property CarbonImmutable|null $closed_at
 * @property int|null $invited
 * @property list<array{driver: string, level: string}>|null $priorities
 * @property bool $sensitive_mentions
 * @property CarbonImmutable|null $results_published_at
 * @property int $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'kind', 'status', 'name', 'bank_version', 'items', 'closes_on', 'launched_at', 'closed_at', 'invited', 'priorities', 'sensitive_mentions', 'results_published_at', 'created_by'])] // @pest-mutate-ignore: RemoveArrayItem
#[Table('engagement_surveys')]
#[UseFactory(SurveyFactory::class)]
#[UsePolicy(SurveyPolicy::class)]
final class Survey extends Model
{
    /** @use HasFactory<SurveyFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * Re-reads the survey under lock, inside the caller's transaction, so a state checked on a copy read earlier cannot
     * have changed meanwhile: two launches, or a delete racing a launch, never both pass.
     *
     * @throws SurveyStateChanged when it is no longer in `$status`.
     */
    public static function lockIn(string $surveyId, SurveyStatus $status): self
    {
        $locked = self::query()->lockForUpdate()->findOrFail($surveyId);

        if ($locked->status !== $status) {
            throw new SurveyStateChanged;
        }

        return $locked;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SurveyKind::class,
            'status' => SurveyStatus::class,
            'items' => 'array',
            'priorities' => 'array',
            'sensitive_mentions' => 'boolean',
            'closes_on' => 'immutable_date',
            'launched_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'results_published_at' => 'immutable_datetime',
        ];
    }
}
