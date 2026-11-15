<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Controllers;

use App\Http\Analytics;
use App\Http\Controllers\Controller;
use App\Modules\Demos\Actions\SubmitDemoRequest;
use App\Modules\Demos\Http\Requests\StoreDemoRequestRequest;
use Illuminate\Http\RedirectResponse;
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
            Analytics::rememberLead($submitDemoRequest->handle($request->toData())->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recibimos tu solicitud · te escribiremos para agendar la demo.']);

        return redirect()->to(route('home').'#agenda-una-demo');
    }
}
