<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\ReportSeat;
use App\Modules\Seating\Actions\ResolveSeatReport;
use App\Modules\Seating\Http\Requests\ResolveSeatReportRequest;
use App\Modules\Seating\Http\Requests\SeatReportRequest;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\SeatReportPages;
use App\Modules\Telework\Contracts\PortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reports of damaged seats: a person sends one from their portal; RH and the Comisión of the center see them open; RH
 * resolves them. A report is never deleted (criterio C-17).
 */
final class SeatReportController extends Controller
{
    public function store(string $token, SeatReportRequest $request, PortalAccess $access, ReportSeat $report): RedirectResponse
    {
        $person = $access->open($token);
        abort_if($person === null, 404);

        $report->handle($person, $request->description());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gracias. Avisamos a la empresa y a la Comisión.']);

        return to_route('portal.seating.show', $token);
    }

    public function index(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, SeatReportPages $pages): Response
    {
        Gate::authorize('assess-seating');
        $center = $access->find($request, $workCenter);

        return Inertia::render('seating/reports', $pages->index($center, $request->string('status')->toString() === 'resolved' ? 'resolved' : 'open'));
    }

    public function resolve(ResolveSeatReportRequest $request, mixed $tenant, string $report, CenterAccess $access, ResolveSeatReport $resolve): RedirectResponse
    {
        $found = SeatReport::query()->find($report);
        abort_if($found === null, 404);
        $center = $access->find($request, $found->work_center_id);

        $resolve->handle($found, $request->resolution());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reporte resuelto.']);

        return redirect()->route('tenant.seating.reports.index', [$tenant, $center->id]);
    }
}
