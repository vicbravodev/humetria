<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Actions\AdjustBalance;
use App\Modules\Leave\Actions\SettleTermination;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Http\Requests\AdjustmentRequest;
use App\Modules\Leave\Http\Requests\SettleRequest;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\RecordPages;
use App\Modules\Organization\Contracts\Roster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The record of one person: owner, hr and viewer read it, whoever left included. A person that is not of the company, or
 * does not exist, is a 404.
 */
final readonly class EmployeeRecordController
{
    public function show(mixed $tenant, string $employee, Roster $roster, LeaveDefaults $defaults, RecordPages $pages): Response
    {
        Gate::authorize('view-vacations');
        $defaults->ensure();
        $person = $roster->find($employee) ?? abort(404);

        return Inertia::render('leave/employee', $pages->show($person, Gate::allows('manage-vacations')));
    }

    /**
     * RH corrects a tranche of one period of the person, with its note. A period that is not the person's is a 404.
     */
    public function adjust(AdjustmentRequest $request, mixed $tenant, string $employee, Roster $roster, AdjustBalance $adjust): RedirectResponse
    {
        $roster->find($employee) ?? abort(404);
        $period = LeaveEntitlement::query()->where('employee_id', $employee)->findOrFail($request->periodId());
        $adjust->handle($period->id, $request->tranche(), $request->days(), $request->note(), $request->userId());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ajuste registrado.']);

        return back();
    }

    /**
     * RH settles the days of a person who left: what is left of each period goes out of the ledger, once.
     */
    public function settle(SettleRequest $request, mixed $tenant, string $termination, SettleTermination $settle): RedirectResponse
    {
        try {
            $settle->handle($termination, $request->userId());
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Días finiquitados.']);
        } catch (AlreadyDecided) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Estos días ya se habían finiquitado o la baja se canceló.']);
        }

        return back();
    }
}
