<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Demos\Actions\ListDemoRequests;
use App\Modules\Demos\Actions\UpdateDemoRequest;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Http\Requests\UpdateDemoRequestRequest;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DemoRequestConsoleController extends Controller
{
    public function index(Request $request, ListDemoRequests $listDemoRequests): Response
    {
        Gate::authorize('view-demo-requests');

        $status = DemoStatus::tryFrom($request->string('status')->toString());

        return Inertia::render('demos/index', [
            'filters' => ['status' => $status->value ?? 'all'],
            'counts' => $listDemoRequests->counts(),
            'can_manage' => Gate::allows('manage-demo-requests'),
            'demoRequests' => $listDemoRequests->handle($status)->through(fn (DemoRequest $demoRequest): array => [
                'id' => $demoRequest->id,
                'name' => $demoRequest->name,
                'email' => $demoRequest->email,
                'whatsapp' => $demoRequest->whatsapp,
                'company' => $demoRequest->company,
                'profile' => $demoRequest->profile->label(),
                'workforce' => $demoRequest->workforce->label(),
                'tools' => $demoRequest->tools->map(fn (Tool $tool): string => $tool->label())->values()->all(),
                'message' => $demoRequest->message,
                'status' => $demoRequest->status->value,
                'status_label' => $demoRequest->status->label(),
                'note' => $demoRequest->note,
                'tenant_id' => $demoRequest->tenant_id,
                // Carbon's Spanish months end in a dot ("oct."); the design system writes "27 oct 2026".
                'received_at' => str_replace('.', '', $demoRequest->created_at->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY, HH:mm')),
            ]),
        ]);
    }

    public function update(UpdateDemoRequestRequest $request, DemoRequest $demoRequest, UpdateDemoRequest $updateDemoRequest): RedirectResponse
    {
        $updateDemoRequest->handle($demoRequest, $request->status(), $request->note());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitud actualizada.']);

        return back();
    }
}
