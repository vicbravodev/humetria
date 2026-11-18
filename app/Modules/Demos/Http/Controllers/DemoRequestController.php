<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Controllers;

use App\Http\Analytics;
use App\Http\Controllers\Controller;
use App\Modules\Demos\Actions\SubmitDemoRequest;
use App\Modules\Demos\Data\DemoRequestData;
use App\Modules\Demos\Http\Requests\StoreDemoRequestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

final class DemoRequestController extends Controller
{
    public function store(StoreDemoRequestRequest $request, SubmitDemoRequest $submitDemoRequest): RedirectResponse
    {
        if ($request->isFromBot()) {
            // Answer like a success, so the bot learns nothing.
            Log::info('demo_request.rejected', ['reason' => 'honeypot']);
        } else {
            // Only now is there a lead: the browser reports the conversion with this id, nothing else (no personal data).
            $data = $request->toData();
            Analytics::rememberLead($submitDemoRequest->handle($data)->id);
            $this->measure($request, $data);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recibimos tu solicitud · te escribiremos para agendar la demo.']);

        return redirect()->to(route('home').'#agenda-una-demo');
    }

    /** The request stored, as the server sees it: the kind of company and the channel, never who asked (no name, no contact). */
    private function measure(Request $request, DemoRequestData $data): void
    {
        $touch = $data->attribution?->credited();

        Analytics::capture($request, 'demo_request_stored', [
            'profile' => $data->profile->value,
            'workforce' => $data->workforce->value,
            'tools_count' => count($data->tools),
            'utm_source' => $touch?->source,
            'utm_medium' => $touch?->medium,
            'utm_campaign' => $touch?->campaign,
            'referrer_host' => $touch?->referrerHost,
            'utm_term' => $data->attribution?->keyword?->term,
            'utm_content' => $data->attribution?->keyword?->content,
            'matchtype' => $data->attribution?->keyword?->matchType,
        ]);
    }
}
