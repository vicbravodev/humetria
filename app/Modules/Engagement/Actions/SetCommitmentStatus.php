<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Actions\RecordAudit;
use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Survey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Moves a commitment along: to do, under way, done or dropped. Bringing back a dropped one takes a place in its group, so
 * it is refused when the group already has three.
 */
final readonly class SetCommitmentStatus
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * @throws ValidationException when bringing it back would pass the places of its group.
     */
    public function handle(string $commitmentId, CommitmentStatus $status): Commitment
    {
        return DB::transaction(function () use ($commitmentId, $status): Commitment {
            $commitment = Commitment::query()->findOrFail($commitmentId);
            // The same lock as saving, so a place freed and taken at once is counted once.
            Survey::query()->lockForUpdate()->findOrFail($commitment->survey_id);
            $commitment->refresh();

            if ($commitment->status === CommitmentStatus::Dropped && $status !== CommitmentStatus::Dropped && ! SaveCommitment::hasRoom($commitment->survey_id, $commitment->group_type, $commitment->group_id)) {
                throw ValidationException::withMessages(['status' => 'Este grupo ya tiene '.SaveCommitment::PER_GROUP.' compromisos. Descarte uno para recuperar este.']);
            }

            $from = $commitment->status;
            $commitment->update(['status' => $status]);

            Log::info('engagement.commitment_status_changed', ['survey_id' => $commitment->survey_id, 'commitment_id' => $commitment->id, 'from' => $from->value, 'to' => $status->value]);
            $this->recordAudit->handle('engagement.commitment_status_changed', $commitment, ['survey_id' => $commitment->survey_id, 'from' => $from->value, 'to' => $status->value]);

            return $commitment;
        });
    }
}
