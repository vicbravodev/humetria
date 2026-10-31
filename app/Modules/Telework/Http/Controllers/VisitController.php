<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\AttachVisitDocument;
use App\Modules\Telework\Actions\CancelVisitAuthorization;
use App\Modules\Telework\Actions\RecordVisit;
use App\Modules\Telework\Actions\RequestVisitAuthorization;
use App\Modules\Telework\Http\Requests\VisitDocumentRequest;
use App\Modules\Telework\Http\Requests\VisitRecordRequest;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\ReviewAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * In-person visits: the Comisión asks the person to authorize one, RH or the Comisión may attach the signed paper, and
 * the Comisión records the visit and its decision. Only the assigned members decide; RH may also attach the paper.
 */
final class VisitController extends Controller
{
    public function __construct(private readonly ReviewAccess $access) {}

    public function requestAuthorization(Request $request, mixed $tenant, string $workplace, RequestVisitAuthorization $ask): RedirectResponse
    {
        $found = $this->workplace($request, $workplace);

        $ask->handle($found, $this->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se pidió la autorización de la visita. La persona la verá en su portal.']);

        return back();
    }

    public function document(VisitDocumentRequest $request, mixed $tenant, string $authorization, AttachVisitDocument $attach): RedirectResponse
    {
        $attach->handle($this->authorization($authorization), $request->documentPath());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Autorización guardada.']);

        return back();
    }

    public function cancel(Request $request, mixed $tenant, string $authorization, CancelVisitAuthorization $cancel): RedirectResponse
    {
        $found = $this->authorization($authorization);
        $this->access->reader($this->userId($request), $this->access->workCenterOf(Workplace::query()->findOrFail($found->workplace_id)));

        $cancel->handle($found, $this->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitud de visita cancelada.']);

        return back();
    }

    public function record(VisitRecordRequest $request, mixed $tenant, string $workplace, RecordVisit $visit): RedirectResponse
    {
        $found = $this->workplace($request, $workplace);
        $authorization = VisitAuthorization::query()->where('workplace_id', $found->id)->find($request->authorizationId());

        $visit->handle(
            $found,
            $authorization ?? throw ValidationException::withMessages(['visit_authorization_id' => 'Se necesita la autorización escrita de la persona.']),
            $request->visitedOn(),
            $request->decision(),
            $request->notes(),
            $request->actionPlan(),
            $request->planDueOn(),
            $this->userId($request),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Visita registrada.']);

        return back();
    }

    private function authorization(string $id): VisitAuthorization
    {
        $found = Str::isUuid($id) ? VisitAuthorization::query()->find($id) : null;
        abort_if($found === null, 404);

        return $found;
    }

    private function workplace(Request $request, string $id): Workplace
    {
        $found = Str::isUuid($id) ? Workplace::query()->find($id) : null;
        abort_if($found === null, 404);
        $this->access->member($this->userId($request), $this->access->workCenterOf($found));

        return $found;
    }

    private function userId(Request $request): int
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user->id;
    }
}
