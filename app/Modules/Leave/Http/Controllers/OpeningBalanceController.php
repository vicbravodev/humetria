<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Actions\ImportOpeningBalances;
use App\Modules\Leave\Actions\PlanOpeningBalances;
use App\Modules\Leave\Http\Requests\OpeningBalanceRequest;
use App\Modules\Leave\Support\ImportPages;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\OpeningCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Loading the opening balances from a CSV, for owner and hr: the file is checked first (what would come in and why each other
 * row cannot) and written only when confirmed. Confirming sends the file again and plans it again here: the client never
 * sends rows, so none can be written that this check did not pass. Nothing is kept of the file.
 */
final readonly class OpeningBalanceController
{
    public function show(LeaveDefaults $defaults): Response
    {
        Gate::authorize('manage-vacations');
        $defaults->ensure();

        return Inertia::render('leave/import', ['maxRows' => config()->integer('leave.import_max_rows')]);
    }

    public function template(): HttpResponse
    {
        Gate::authorize('manage-vacations');

        return new HttpResponse(OpeningCsv::template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-saldos-iniciales.csv"',
        ]);
    }

    public function plan(OpeningBalanceRequest $request, PlanOpeningBalances $plan, ImportPages $pages): RedirectResponse
    {
        Inertia::flash('leave_import', $pages->summary($plan->handle($request->rows())));

        return back();
    }

    /**
     * @param  mixed  $tenant  The company, already resolved by Tenancy from the URL; only routed through.
     */
    public function store(OpeningBalanceRequest $request, mixed $tenant, PlanOpeningBalances $plan, ImportOpeningBalances $import): RedirectResponse
    {
        $planned = $plan->handle($request->rows());
        $loaded = $import->handle($planned['ready'], $request->userId());

        if ($loaded === 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No había nada nuevo que cargar: revisa el archivo.']);

            return back();
        }

        $out = count($planned['rejected']);
        Inertia::flash('toast', ['type' => 'success', 'message' => sprintf(
            'Se %s %d %s.%s',
            $loaded === 1 ? 'cargó' : 'cargaron', $loaded, $loaded === 1 ? 'periodo' : 'periodos',
            $out === 0 ? '' : sprintf(' %d %s no %s.', $out, $out === 1 ? 'fila' : 'filas', $out === 1 ? 'entró' : 'entraron'),
        )]);

        return to_route('tenant.vacations.balances.index', ['tenant' => $tenant]);
    }
}
