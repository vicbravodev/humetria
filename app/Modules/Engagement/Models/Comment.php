<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use App\Concerns\BelongsToCurrentTenant;
use Database\Factories\Engagement\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An open comment, stored on its own: nothing ties it to the response of the person who wrote it, nor to any date.
 * Only the comments about a manager carry the group of that manager. Insert-only, like the responses.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $survey_id
 * @property string $item_key
 * @property string|null $comment_group_id
 * @property string $body
 */
#[Fillable(['tenant_id', 'survey_id', 'item_key', 'comment_group_id', 'body'])] // @pest-mutate-ignore: RemoveArrayItem
#[WithoutTimestamps]
#[UseFactory(CommentFactory::class)]
final class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    protected $table = 'engagement_comments';
}
