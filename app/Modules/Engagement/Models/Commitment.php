<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Engagement\Enums\CommitmentGroup;
use App\Modules\Engagement\Enums\CommitmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Engagement\CommitmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What the company commits to do after a closed survey, for everyone or for one group of the results: what people said
 * (`heard`), what will be done, who answers for it and by when.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $survey_id
 * @property CommitmentGroup $group_type
 * @property string|null $group_id
 * @property string|null $driver
 * @property string $heard
 * @property string $will_do
 * @property string $owner_name
 * @property CarbonImmutable $due_on
 * @property CommitmentStatus $status
 * @property int $created_by
 */
#[Fillable(['tenant_id', 'survey_id', 'group_type', 'group_id', 'driver', 'heard', 'will_do', 'owner_name', 'due_on', 'status', 'created_by'])] // @pest-mutate-ignore: RemoveArrayItem
#[Table('engagement_commitments')]
#[UseFactory(CommitmentFactory::class)]
final class Commitment extends Model
{
    /** @use HasFactory<CommitmentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['group_type' => CommitmentGroup::class, 'status' => CommitmentStatus::class, 'due_on' => 'immutable_date'];
    }
}
