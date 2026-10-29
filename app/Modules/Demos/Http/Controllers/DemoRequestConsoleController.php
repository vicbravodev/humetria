<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Demos\Actions\ListDemoRequests;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DemoRequestConsoleController extends Controller
{
    public function index(ListDemoRequests $listDemoRequests): Response
    {
        Gate::authorize('view-demo-requests');

        return Inertia::render('demos/index', [
            'demoRequests' => $listDemoRequests->handle()->through(fn (DemoRequest $demoRequest): array => [
                'id' => $demoRequest->id,
                'name' => $demoRequest->name,
                'email' => $demoRequest->email,
                'whatsapp' => $demoRequest->whatsapp,
                'company' => $demoRequest->company,
                'profile' => $demoRequest->profile->label(),
                'workforce' => $demoRequest->workforce->label(),
                'tools' => $demoRequest->tools->map(fn (Tool $tool): string => $tool->label())->values()->all(),
                'message' => $demoRequest->message,
                // Carbon's Spanish months end in a dot ("oct."); the design system writes "27 oct 2026".
                'received_at' => str_replace('.', '', $demoRequest->created_at->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY, HH:mm')),
            ]),
        ]);
    }
}
