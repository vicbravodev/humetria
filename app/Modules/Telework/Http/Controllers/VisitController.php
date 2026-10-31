<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\AttachVisitDocument;
use App\Modules\Telework\Actions\RecordVisit;
use App\Modules\Telework\Actions\RequestVisitAuthorization;
use App\Modules\Telework\Http\Requests\ReviewRequest;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\ReviewAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

    public function document(Request $request, mixed $tenant, string $authorization, AttachVisitDocument $attach): RedirectResponse
    {
        $found = Str::isUuid($authorization) ? VisitAuthorization::query()->find($authorization) : null;
        abort_if($found === null, 404);
        $this->access->reader($this->userId($request), $this->access->workCenterOf(Workplace::query()->findOrFail($found->workplace_id)));

        // mimetypes reads the file's content, not its extension.
        $request->validate(['document' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config()->integer('telework.max_document_kb')]]);
        $file = $request->file('document');
        abort_unless($file instanceof UploadedFile, 422);

        $attach->handle($found, $file->getPathname());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Autorización guardada.']);

        return back();
    }

    public function record(ReviewRequest $request, mixed $tenant, string $workplace, RecordVisit $visit): RedirectResponse
    {
        $found = $this->workplace($request, $workplace);
        $request->validate(['visit_authorization_id' => ['required', 'uuid'], 'visited_on' => ['required', 'date_format:Y-m-d']]);
        $authorization = VisitAuthorization::query()->where('workplace_id', $found->id)->find($request->string('visit_authorization_id')->toString());

        $visit->handle(
            $found,
            $authorization ?? throw ValidationException::withMessages(['visit_authorization_id' => 'Se necesita la autorización escrita de la persona.']),
            CarbonImmutable::parse($request->string('visited_on')->toString()),
            $request->decision(),
            $request->notes(),
            $request->actionPlan(),
            $request->planDueOn(),
            $this->userId($request),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Visita registrada.']);

        return back();
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
