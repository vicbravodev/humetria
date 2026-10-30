<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Actions\DrawSample;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Population;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * What RH does to one evaluation: draw its sample again, launch it, and later follow it up and close it.
 */
final class CampaignActionController extends Controller
{
    public function redraw(mixed $tenant, string $campaign, DrawSample $drawSample): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $draft = CampaignController::find($campaign, CampaignStatus::Draft);
        abort_if($draft->population !== Population::Sample, 409);

        $drawSample->handle($draft);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Muestra sorteada de nuevo.']);

        return redirect()->route('tenant.campaigns.show', [$tenant, $draft->id]);
    }
}
