<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Results\Actions\RecordScreeningReferral;
use App\Modules\Results\Http\Requests\ScreeningReferralRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Records the channeling of a person whose Guía I requires a clinical assessment. Whoever may not see the list, or a
 * person who does not require it (or belongs to another company), gets the same 404 as one that does not exist.
 */
final class ScreeningReferralController extends Controller
{
    public function update(ScreeningReferralRequest $request, mixed $tenant, string $campaign, string $employee, CampaignRecords $campaigns, ResponseReader $responses, RecordScreeningReferral $record): RedirectResponse
    {
        $found = $campaigns->find($campaign) ?? abort(404);
        abort_unless($responses->requiresAssessment($found->id, $employee), 404);

        $record->handle($found->id, $employee, $request->status(), $request->string('referred_on')->toString(), $request->institution(), ($request->user() ?? abort(403))->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Canalización registrada.']);

        return redirect()->route('tenant.campaigns.screenings', [$tenant, $found->id]);
    }
}
