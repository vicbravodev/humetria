<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\ResolveCorrection;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Http\Requests\ResolveCorrectionRequest;
use App\Modules\Attendance\Support\CorrectionPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RH's queue of the people's requests to correct their record (criterio 5.4): owner, hr and viewer read it; owner and hr
 * approve or reject. A request of another company is a 404, as is any of it for a union.
 */
final readonly class CorrectionController
{
    private const array REFUSED = [
        'already_resolved' => 'Esta solicitud ya se resolvió.',
        'sequence_invalid' => 'Con esa hora el día queda en desorden (una salida antes de su entrada o una entrada con otra abierta). Rechaza la solicitud o pide a la persona otra hora.',
        'mark_changed' => 'La marca ya se corrigió desde que se pidió. Rechaza la solicitud o pide a la persona que la revise.',
    ];

    public function index(Request $request, CorrectionPages $pages): Response
    {
        Gate::authorize('view-attendance');

        return Inertia::render('attendance/corrections', $pages->index($request->string('status')->toString(), $request->string('q')->trim()->toString()));
    }

    public function approve(ResolveCorrectionRequest $request, mixed $tenant, string $correction, ResolveCorrection $resolve): RedirectResponse
    {
        return $this->resolve($request, $correction, $resolve, true, 'Solicitud aprobada. La marca quedó corregida.');
    }

    public function reject(ResolveCorrectionRequest $request, mixed $tenant, string $correction, ResolveCorrection $resolve): RedirectResponse
    {
        return $this->resolve($request, $correction, $resolve, false, 'Solicitud rechazada. La persona verá tu nota.');
    }

    private function resolve(ResolveCorrectionRequest $request, string $correction, ResolveCorrection $resolve, bool $approve, string $done): RedirectResponse
    {
        try {
            $resolve->handle($correction, $approve, ($request->user() ?? abort(401))->id, $request->note());
            Inertia::flash('toast', ['type' => 'success', 'message' => $done]);
        } catch (CorrectionRejected $refused) {
            Inertia::flash('toast', ['type' => 'error', 'message' => self::REFUSED[$refused->reason] ?? 'No se pudo resolver la solicitud.']);
        }

        return back();
    }
}
