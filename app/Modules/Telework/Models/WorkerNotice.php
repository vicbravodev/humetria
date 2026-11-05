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
 * A written notice of the person (numerals 6.3, 6.7, 6.9). Evidence: never deleted. Its description, the review note
 * and what was done about an accident are personal data and never reach a log. An accident (6.9) is followed up
 * (rediseño v2, board V3-Aviso): what was done, which the person reads in their portal, then its closing with evidence.
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
 * @property list<string>|null $review_actions
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property string|null $evidence_path
 * @property CarbonImmutable|null $reminded_at
 * @property CarbonImmutable|null $place_review_requested_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'teleworker_id', 'kind', 'written_on', 'occurred_on', 'description', 'traumatic_event', 'temporary', 'new_workplace_id', 'submitted_at', 'ip', 'reviewed_by', 'reviewed_at', 'review_note', 'replaced_at', 'review_actions', 'closed_by', 'closed_at', 'evidence_path', 'reminded_at', 'place_review_requested_at'])]
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
            'review_actions' => 'array',
            'closed_at' => 'immutable_datetime',
            'reminded_at' => 'immutable_datetime',
            'place_review_requested_at' => 'immutable_datetime',
        ];
    }
}
