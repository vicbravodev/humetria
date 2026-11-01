<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\NoticeKind;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\WorkerNoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A written notice of the person (numerals 6.3, 6.7, 6.9). Evidence: never deleted. Its description and the review
 * note are personal data and never reach a log.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $teleworker_id
 * @property NoticeKind $kind
 * @property CarbonImmutable $written_on
 * @property CarbonImmutable $occurred_on
 * @property string $description
 * @property bool $traumatic_event
 * @property bool $temporary
 * @property string|null $new_workplace_id
 * @property CarbonImmutable $submitted_at
 * @property string|null $ip
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property CarbonImmutable|null $replaced_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'teleworker_id', 'kind', 'written_on', 'occurred_on', 'description', 'traumatic_event', 'temporary', 'new_workplace_id', 'submitted_at', 'ip', 'reviewed_by', 'reviewed_at', 'review_note', 'replaced_at'])]
#[UseFactory(WorkerNoticeFactory::class)]
final class WorkerNotice extends Model
{
    /** @use HasFactory<WorkerNoticeFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => NoticeKind::class,
            'written_on' => 'immutable_date',
            'occurred_on' => 'immutable_date',
            'traumatic_event' => 'boolean',
            'temporary' => 'boolean',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'replaced_at' => 'immutable_datetime',
        ];
    }
}
