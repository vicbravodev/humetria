<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Actions\RecordAudit;
use App\Modules\Engagement\Data\CommitmentData;
use App\Modules\Engagement\Enums\CommitmentGroup;
use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Results\SurveyResults;
use App\Modules\Engagement\Support\SurveyClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Writes down what the company will do after a closed survey. It is for the company or for a group the results show,
 * and a group takes at most three at a time (a dropped one frees its place). The survey is locked while the count is
 * checked, so two people saving at once never both take the last place. Editing never moves a commitment to another group.
 * Neither the log nor the audit trail carry what it says.
 */
final readonly class SaveCommitment
{
    public const int PER_GROUP = 3;

    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * @throws ValidationException when the survey is not closed, the group is not shown, the group is full or the date has passed.
     */
    public function handle(CommitmentData $data, ?string $commitmentId = null): Commitment
    {
        return DB::transaction(function () use ($data, $commitmentId): Commitment {
            $survey = Survey::query()->lockForUpdate()->findOrFail($data->surveyId);

            if ($survey->status !== SurveyStatus::Closed) {
                throw ValidationException::withMessages(['survey' => 'Los compromisos se registran cuando la encuesta ya cerró.']);
            }

            $commitment = $commitmentId === null
                ? $this->create($survey, $data)
                : tap(Commitment::query()->where('survey_id', $survey->id)->findOrFail($commitmentId))->update([
                    'driver' => $data->driver, 'heard' => $data->heard, 'will_do' => $data->willDo, 'owner_name' => $data->ownerName, 'due_on' => $data->dueOn->toDateString(),
                ]);

            Log::info('engagement.commitment_saved', ['survey_id' => $survey->id, 'commitment_id' => $commitment->id, 'group' => $commitment->group_type->value, 'created' => $commitmentId === null]);
            $this->recordAudit->handle('engagement.commitment_saved', $commitment, ['survey_id' => $survey->id, 'group' => $commitment->group_type->value, 'created' => $commitmentId === null]);

            return $commitment;
        });
    }

    /**
     * Whether the group of a survey, which the caller has locked, has a place left.
     */
    public static function hasRoom(string $surveyId, CommitmentGroup $group, ?string $groupId): bool
    {
        return Commitment::query()
            ->where('survey_id', $surveyId)
            ->where('group_type', $group)
            ->when($groupId === null, fn ($query) => $query->whereNull('group_id'), fn ($query) => $query->where('group_id', $groupId))
            ->where('status', '!=', CommitmentStatus::Dropped)
            ->count() < self::PER_GROUP;
    }

    private function create(Survey $survey, CommitmentData $data): Commitment
    {
        if (! SurveyResults::for($survey)->showsGroup($data->group, $data->groupId)) {
            throw ValidationException::withMessages(['group_id' => 'Ese grupo no aparece en los resultados.']);
        }

        if ($data->dueOn->lessThan(SurveyClock::day())) {
            throw ValidationException::withMessages(['due_on' => 'La fecha no puede ser anterior a hoy.']);
        }

        if (! self::hasRoom($survey->id, $data->group, $data->groupId)) {
            throw ValidationException::withMessages(['group_id' => 'Este grupo ya tiene '.self::PER_GROUP.' compromisos. Descarte uno para agregar otro.']);
        }

        return Commitment::query()->create([
            'driver' => $data->driver,
            'heard' => $data->heard,
            'will_do' => $data->willDo,
            'owner_name' => $data->ownerName,
            'due_on' => $data->dueOn->toDateString(),
            'tenant_id' => $survey->tenant_id,
            'survey_id' => $survey->id,
            'group_type' => $data->group,
            'group_id' => $data->groupId,
            'status' => CommitmentStatus::Open,
            'created_by' => $data->userId,
        ]);
    }
}
