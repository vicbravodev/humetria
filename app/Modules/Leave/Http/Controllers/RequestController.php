<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Actions\CancelRequest;
use App\Modules\Leave\Actions\DecideRequest;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Exceptions\RequestRefused;
use App\Modules\Leave\Http\Requests\DecisionRequest;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\RequestPages;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RH's queue of vacation and leave requests: owner, hr and viewer read it, owner and hr answer, correct and record. A work
 * center that is not one in use of the company is a 404.
 */
final readonly class RequestController
{
    public function __construct(private WorkCenters $centers) {}

    public function index(Request $request, LeaveDefaults $defaults, RequestPages $pages): Response
    {
        Gate::authorize('view-vacations');
        $defaults->ensure();

        return Inertia::render('leave/requests', $pages->index(
            $request->string('tab')->toString(),
            $request->string('q')->trim()->limit(100, '')->toString(),
            $this->uuid($request, 'center', fn (string $id): bool => $this->centers->findActive($id) !== null),
            $this->uuid($request, 'area', fn (): bool => true),
            Gate::allows('manage-vacations'),
        ));
    }

    public function approve(DecisionRequest $request, mixed $tenant, string $leaveRequest, DecideRequest $decide): RedirectResponse
    {
        return $this->answer($leaveRequest, true, null, $request, $decide);
    }

    public function reject(DecisionRequest $request, mixed $tenant, string $leaveRequest, DecideRequest $decide): RedirectResponse
    {
        return $this->answer($leaveRequest, false, $request->string('reason')->toString(), $request, $decide);
    }

    /**
     * RH corrects: it cancels what still holds its dates, an enjoyed one too, and the days go back to the same periods.
     */
    public function cancel(DecisionRequest $request, mixed $tenant, string $leaveRequest, CancelRequest $cancel): RedirectResponse
    {
        try {
            $cancel->handle($leaveRequest, Decider::hr($request->userId()));
            Inertia::flash('toast', ['type' => 'success', 'message' => 'La solicitud quedó cancelada.']);
        } catch (AlreadyDecided) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Esta solicitud ya no se puede cancelar.']);
        }

        return back();
    }

    /**
     * One answer wins: a second one is told so and changes nothing. A request that cannot be approved without days says so.
     */
    private function answer(string $requestId, bool $approve, ?string $reason, DecisionRequest $request, DecideRequest $decide): RedirectResponse
    {
        try {
            $decide->handle($requestId, $approve, Decider::hr($request->userId()), $reason);
            Inertia::flash('toast', ['type' => 'success', 'message' => $approve ? 'Solicitud aprobada.' : 'Solicitud rechazada.']);
        } catch (AlreadyDecided $refused) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $refused->getMessage()]);
        } catch (RequestRefused $refused) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $refused->reason === 'insufficient_balance' ? 'La persona no tiene días disponibles y su política no permite adelantar vacaciones.' : $refused->getMessage()]);
        }

        return back();
    }

    /**
     * The id a filter names: none when it is not asked for, a 404 when it is not a uuid or is not one `valid` accepts.
     *
     * @param  callable(string): bool  $valid
     */
    private function uuid(Request $request, string $key, callable $valid): ?string
    {
        $id = $request->string($key)->toString();

        if ($id === '') {
            return null;
        }

        return Str::isUuid($id) && $valid($id) ? $id : abort(404);
    }
}
