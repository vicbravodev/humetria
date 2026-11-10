<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Listeners;

use App\Modules\Engagement\Events\SurveyClosed;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\ManagerFeedbackReady;
use App\Modules\Engagement\Support\Results\ItemResult;
use App\Modules\Engagement\Support\Results\SurveyResults;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * When a survey closes, each manager whose group is shown (and who is still active and has an email) is told, with the
 * link of their portal, that their team's feedback is ready. Nobody else: not a manager whose group was hidden, and not
 * one who left.
 */
final readonly class NotifyManagers
{
    public function __construct(private Roster $roster, private PortalAccess $access, private Companies $companies) {}

    public function handle(SurveyClosed $event): void
    {
        $survey = Survey::query()->find($event->surveyId);
        $results = $survey === null ? null : SurveyResults::for($survey);

        if ($survey === null || $results === null || ! $results->available()) {
            return;
        }

        $groups = $results->managerGroups()->visible;
        $people = $this->roster->findMany(array_keys($groups));
        $company = $this->companies->current();
        $told = 0;

        foreach ($groups as $managerId => $rows) {
            $person = $people[$managerId] ?? null;
            $reads = array_filter($results->managerItems($rows), fn (ItemResult $item): bool => $item->shown) !== [];

            if ($person === null || ! $person->active || ($person->email ?? '') === '' || ! $reads) {
                continue;
            }

            Notification::route('mail', $person->email)->notify(new ManagerFeedbackReady(
                $company,
                $person->name,
                $survey->name,
                route('portal.feedback.show', $this->access->token($managerId)),
            ));
            $told++;
        }

        Log::info('engagement.managers_notified', ['survey_id' => $survey->id, 'notified' => $told]);
    }
}
