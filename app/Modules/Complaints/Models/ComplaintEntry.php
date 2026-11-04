<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Complaints\ComplaintEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry of a case: a message of the person (a day, never an hour), an answer of a handler, an internal note, or a line of
 * the case's own history. It is ordered by its position in the case, not by a time, and its text is encrypted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $complaint_id
 * @property int $position
 * @property string $kind
 * @property int|null $author_id
 * @property string|null $body
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable|null $occurred_at
 * @property CarbonImmutable|null $occurred_on
 */
#[Fillable(['tenant_id', 'complaint_id', 'position', 'kind', 'author_id', 'body', 'meta', 'occurred_at', 'occurred_on'])]
#[WithoutTimestamps]
#[UseFactory(ComplaintEntryFactory::class)]
final class ComplaintEntry extends Model
{
    /** @use HasFactory<ComplaintEntryFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer', 'body' => 'encrypted', 'meta' => 'array', 'occurred_at' => 'immutable_datetime', 'occurred_on' => 'immutable_date'];
    }
}
