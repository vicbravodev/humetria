<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Engagement\Enums\SummaryStatus;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\ManagerSurvey;
use App\Modules\Engagement\Support\Results\ManagerFeedback;
use App\Modules\Engagement\Support\Results\ManagerFeedbackData;
use App\Modules\Telework\Contracts\PortalAccess;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The retroalimentación a manager reads in their portal: the aggregate of their group in the latest closed climate survey,
 * and the AI summary of its comments. The link says who they are: nothing else picks the group. A manager whose group is
 * hidden, with no surveys, or who is nobody's manager gets the very same screen, so it says nothing about which is the
 * case. Never an answer or a comment, and no count of people. A link that is not valid is a 404 that says nothing.
 */
final class PortalFeedbackController extends Controller
{
    public function show(string $token, PortalAccess $access): Response
    {
        $identity = $access->open($token);
        abort_if($identity === null, 404);

        $survey = ManagerSurvey::latest();
        $feedback = $survey === null ? null : ManagerFeedback::for($survey, $identity->employeeId);

        return Inertia::render('engagement/portal-feedback', [
            'token' => $token,
            'company' => $identity->company,
            'survey' => $survey === null || $feedback === null ? null : ['name' => $survey->name],
            'feedback' => $feedback === null ? null : $this->props($feedback),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function props(ManagerFeedbackData $feedback): array
    {
        // Only a summary that is ready, or on its way, is told; any other reason it is missing stays unsaid.
        $summary = in_array($feedback->summaryStatus, [SummaryStatus::Ready, SummaryStatus::Pending], true)
            ? ['status' => $feedback->summaryStatus->value, 'themes' => $feedback->themes]
            : null;

        return [
            'items' => $feedback->items,
            'strengths' => $feedback->strengths,
            'opportunities' => $feedback->opportunities,
            'line' => $feedback->line,
            'summary' => $summary,
        ];
    }
}
