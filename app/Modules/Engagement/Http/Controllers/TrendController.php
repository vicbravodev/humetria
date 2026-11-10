<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Http\Requests\TrendRequest;
use App\Modules\Engagement\Support\Pulse\TrendPage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/** How the company moves from survey to survey. Everyone who views the pulse reads it; each view is audited, never what it said. */
final class TrendController extends Controller
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function show(TrendRequest $request, mixed $tenant, TrendPage $page): Response
    {
        Gate::authorize(SurveyKind::Pulse->viewPermission());

        $waves = $page->waves();

        if ($waves !== []) {
            Log::info('engagement.results_viewed', ['view' => 'trend', 'waves' => count($waves)]);
            $this->audit->handle('engagement.results_viewed', null, ['view' => 'trend', 'waves' => count($waves)]);
        }

        return Inertia::render('engagement/trend', [...$page->props($waves), 'canManage' => Gate::allows(SurveyKind::Pulse->managePermission())]);
    }
}
