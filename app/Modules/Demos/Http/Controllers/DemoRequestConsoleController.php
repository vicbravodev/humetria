<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Demos\Actions\DiscardDemoRequest;
use App\Modules\Demos\Actions\ExportAdConversions;
use App\Modules\Demos\Actions\ListDemoRequests;
use App\Modules\Demos\Actions\ScheduleDemo;
use App\Modules\Demos\Actions\UpdateDemoRequest;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Http\Requests\DiscardDemoRequestRequest;
use App\Modules\Demos\Http\Requests\ScheduleDemoRequest;
use App\Modules\Demos\Http\Requests\UpdateDemoRequestRequest;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Demos\Support\DemoOwners;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DemoRequestConsoleController extends Controller
{
    public function index(Request $request, ListDemoRequests $listDemoRequests, DemoOwners $owners): Response
    {
        Gate::authorize('view-demo-requests');

        $status = DemoStatus::tryFrom($request->string('status')->toString());
        $search = $request->string('q')->trim()->limit(100, '')->toString();

        return Inertia::render('demos/index', [
            'filters' => ['status' => $status->value ?? 'all', 'q' => $search],
            'counts' => $listDemoRequests->counts(),
            'can_manage' => Gate::allows('manage-demo-requests'),
            'can_open_room' => Gate::allows('manage-demo'),
            'demoRequests' => $listDemoRequests->handle($status, $search)->through($this->row(...)),
            // Who of the staff may own a request: only for the form, after the page shows.
            'staff' => Inertia::defer(fn (): array => $owners->options()),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(DemoRequest $demoRequest): array
    {
        return [
            'id' => $demoRequest->id,
            'name' => $demoRequest->name,
            'email' => $demoRequest->email,
            'whatsapp' => $demoRequest->whatsapp,
            'company' => $demoRequest->company,
            'profile' => $demoRequest->profile?->label(),
            'workforce' => $demoRequest->workforce?->label(),
            'tools' => $demoRequest->tools->map(fn (Tool $tool): string => $tool->label())->values()->all(),
            'message' => $demoRequest->message,
            'status' => $demoRequest->status->value,
            'status_label' => $demoRequest->status->label(),
            'note' => $demoRequest->note,
            'tenant_id' => $demoRequest->tenant_id,
            // Null is "no data", never direct traffic; the first contact only when it is not the same channel.
            'arrived_by' => $demoRequest->arrivedBy(),
            'first_contact' => $demoRequest->firstContact(),
            // Carbon's Spanish months end in a dot ("oct."); the design system writes "27 oct 2026".
            'received_at' => str_replace('.', '', $demoRequest->created_at->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY, HH:mm')),
            'owner' => $demoRequest->owner === null ? null : ['id' => $demoRequest->owner->id, 'name' => $demoRequest->owner->name],
            'next_step' => $demoRequest->next_step,
            'scheduled_for' => $demoRequest->scheduled_at === null ? null : str_replace('.', '', $demoRequest->scheduled_at->setTimezone(config()->string('app.display_timezone'))->isoFormat('ddd D MMM, HH:mm')),
            'discard_reason' => $demoRequest->discard_reason,
        ];
    }

    /** The click conversions for Google Ads, as a CSV to upload by hand. */
    public function adConversions(ExportAdConversions $export): StreamedResponse
    {
        Gate::authorize('view-demo-requests');

        return $this->download($export->handle(), 'conversiones-google-ads');
    }

    /** The same for the clicks of iOS, which Google identifies by gbraid or wbraid instead of gclid. */
    public function adConversionsIos(ExportAdConversions $export): StreamedResponse
    {
        Gate::authorize('view-demo-requests');

        return $this->download($export->handleIos(), 'conversiones-google-ads-ios');
    }

    private function download(string $csv, string $name): StreamedResponse
    {
        $day = CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, "{$name}-{$day}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function update(UpdateDemoRequestRequest $request, DemoRequest $demoRequest, UpdateDemoRequest $updateDemoRequest): RedirectResponse
    {
        $updateDemoRequest->handle($demoRequest, $request->status(), $request->note(), $request->ownerId(), $request->nextStep());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitud actualizada.']);

        return back();
    }

    public function schedule(ScheduleDemoRequest $request, DemoRequest $demoRequest, ScheduleDemo $scheduleDemo): RedirectResponse
    {
        $scheduleDemo->handle($demoRequest, $request->at());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Demo agendada.']);

        return back();
    }

    public function discard(DiscardDemoRequestRequest $request, DemoRequest $demoRequest, DiscardDemoRequest $discardDemoRequest): RedirectResponse
    {
        $discardDemoRequest->handle($demoRequest, $request->reason());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitud descartada.']);

        return back();
    }
}
