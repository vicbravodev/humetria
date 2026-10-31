<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\SurveyUnavailableReason;
use App\Modules\Campaigns\Exceptions\SurveyUnavailable;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Responses\Contracts\ResponseRecorder;
use App\Modules\Responses\Data\AnonymousAnswers;
use App\Modules\Responses\Data\TraumaAnswers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Takes one guide of a person, whole and once (II.2 c, III.2 c, criterio C-18). Guía I is stored under the person's
 * name with whether they need a clinical assessment; Guías II and III anonymously, under the campaign's work center
 * and the person's current area. Everything happens under a lock on the participant, so two submissions of the same
 * guide cannot both pass.
 */
final readonly class SubmitSurvey
{
    public function __construct(
        private OpenSurvey $openSurvey,
        private Instruments $instruments,
        private Roster $roster,
        private ResponseRecorder $recorder,
    ) {}

    /**
     * @param  array<array-key, mixed>  $answers
     *
     * @throws SurveyUnavailable when the link no longer works, or the guide is not one the person has left.
     * @throws InvalidArgumentException when the answers are not the complete, coherent guide.
     */
    public function handle(string $token, string $instrument, array $answers): void
    {
        DB::transaction(function () use ($token, $instrument, $answers): void {
            $survey = $this->openSurvey->handle($token, forUpdate: true);
            $guide = $survey->participant->instruments()->where('instrument', $instrument)->first();

            if ($guide === null || $guide->status === InstrumentStatus::Cancelled) {
                throw new SurveyUnavailable(SurveyUnavailableReason::Invalid, $survey->company);
            }

            if ($guide->status === InstrumentStatus::Completed) {
                throw new SurveyUnavailable(SurveyUnavailableReason::Done, $survey->company);
            }

            $definition = $this->instruments->definition($instrument);

            if ($definition->identified) {
                $outcome = $this->instruments->screenTrauma($answers);
                $this->recorder->recordTraumaScreening(new TraumaAnswers($survey->campaign->id, $survey->participant->employee_id, $definition->version, $answers, $outcome->requiresClinicalAssessment));
            } else {
                $this->instruments->validate($instrument, $answers);
                $person = $this->roster->find($survey->participant->employee_id) ?? throw new SurveyUnavailable(SurveyUnavailableReason::Invalid, $survey->company);
                $this->recorder->recordAnonymous(new AnonymousAnswers($survey->campaign->id, $survey->campaign->work_center_id, $person->areaId, $instrument, $definition->version, $answers));
            }

            $guide->update(['status' => InstrumentStatus::Completed, 'completed_at' => now()]);
            Log::info('campaign_participant.completed', ['participant_id' => $survey->participant->id, 'instrument' => $instrument]);
        }, Campaign::DEADLOCK_ATTEMPTS);
    }
}
