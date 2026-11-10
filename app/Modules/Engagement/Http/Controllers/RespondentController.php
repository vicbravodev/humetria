<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Engagement\Actions\OpenInvitation;
use App\Modules\Engagement\Exceptions\SurveyUnavailable;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\SurveyPages;
use Inertia\Inertia;
use Inertia\Response;

/** The survey of a climate or pulse wave, reached from the emailed link. No account: the token of the link is the credential. */
final class RespondentController extends Controller
{
    public function show(string $token, OpenInvitation $openInvitation): Response
    {
        try {
            $opened = $openInvitation->handle($token);
        } catch (SurveyUnavailable $unavailable) {
            return Inertia::render('engagement/survey', [
                'token' => null,
                'unavailable' => $unavailable->reason->value,
                'company' => $unavailable->company,
                'closesOn' => null,
                'kind' => null,
                'minutes' => null,
                'minGroup' => null,
                'managerName' => null,
                'agreementLabels' => [],
                'openNotice' => '',
                'sections' => [],
            ]);
        }

        return Inertia::render('engagement/survey', [
            // Already in the URL: the page posts the answers back to it.
            'token' => $token,
            'unavailable' => null,
            'company' => $opened->company,
            'closesOn' => $opened->survey->closes_on?->isoFormat('D [de] MMMM'),
            'kind' => $opened->survey->kind->value,
            'minutes' => $opened->survey->kind->minutes(),
            'minGroup' => config()->integer('results.min_group'),
            'managerName' => $opened->managerName,
            'agreementLabels' => array_values(ItemBank::AGREEMENT_LABELS),
            'openNotice' => ItemBank::OPEN_NOTICE,
            'sections' => SurveyPages::sections($opened->presented(), $opened->managerName),
        ]);
    }
}
