<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use App\Concerns\BelongsToCurrentTenant;
use Database\Factories\Engagement\ResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One person's answers to a climate or pulse survey, with no way back to them: no employee, invitation or token, and no
 * timestamps, since when it was answered would tell who answered. The segment is the coarse one copied from the
 * invitation. Insert-only: a trigger rejects updates, deletes and truncates.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $survey_id
 * @property string|null $work_center_id
 * @property string|null $area_id
 * @property string|null $tenure_band
 * @property string|null $manager_group_id
 * @property array<string, int|null> $answers
 * @property int|null $minutes
 */
#[Fillable(['tenant_id', 'survey_id', 'work_center_id', 'area_id', 'tenure_band', 'manager_group_id', 'answers', 'minutes'])] // @pest-mutate-ignore: RemoveArrayItem
#[WithoutTimestamps]
#[UseFactory(ResponseFactory::class)]
final class Response extends Model
{
    /** @use HasFactory<ResponseFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    protected $table = 'engagement_responses';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['answers' => 'array', 'minutes' => 'integer'];
    }
}
