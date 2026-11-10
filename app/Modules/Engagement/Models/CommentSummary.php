<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Engagement\Enums\SummaryStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Engagement\CommentSummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The AI summary of a group of comments, made once when the survey closed: themes and how many comments back each, never
 * a comment. `manager_group_id` is null for the comments about the company.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $survey_id
 * @property string|null $manager_group_id
 * @property SummaryStatus $status
 * @property array{valued: list<array{theme: string, count: int}>, change: list<array{theme: string, count: int}>}|null $themes
 * @property CarbonImmutable|null $generated_on
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'survey_id', 'manager_group_id', 'status', 'themes', 'generated_on'])] // @pest-mutate-ignore: RemoveArrayItem
#[Table('engagement_comment_summaries')]
#[UseFactory(CommentSummaryFactory::class)]
final class CommentSummary extends Model
{
    /** @use HasFactory<CommentSummaryFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => SummaryStatus::class, 'themes' => 'array', 'generated_on' => 'immutable_date'];
    }
}
