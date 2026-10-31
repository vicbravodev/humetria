<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\ReviewWorkplace;
use App\Modules\Telework\Http\Requests\ReviewRequest;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\ReviewAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * The Comisión reviews the checklists of its work center. Everything here is for the assigned members of that center
 * (`ReviewAccess`); a checklist or workplace outside the company does not exist.
 */
final class ReviewController extends Controller
{
    public function __construct(private readonly ReviewAccess $access) {}

    public function store(ReviewRequest $request, mixed $tenant, string $application, ReviewWorkplace $review): RedirectResponse
    {
        $found = Str::isUuid($application) ? ChecklistApplication::query()->find($application) : null;
        abort_if($found === null, 404);
        $workplace = Workplace::query()->findOrFail($found->workplace_id);
        $this->access->member($this->userId($request), $this->access->workCenterOf($workplace));

        $review->handle($workplace, $found, $request->decision(), $request->notes(), $request->actionPlan(), $request->planDueOn(), $this->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Decisión registrada.']);

        return redirect()->route('tenant.telework.committee.show', [$tenant, $this->access->workCenterOf($workplace)]);
    }

    public function remoteCheck(ReviewRequest $request, mixed $tenant, string $workplace, ReviewWorkplace $review): RedirectResponse
    {
        $found = Str::isUuid($workplace) ? Workplace::query()->find($workplace) : null;
        abort_if($found === null, 404);
        $this->access->member($this->userId($request), $this->access->workCenterOf($found));

        $review->handle($found, null, $request->decision(), $request->notes(), $request->actionPlan(), $request->planDueOn(), $this->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Comprobación a distancia registrada.']);

        return redirect()->route('tenant.telework.committee.show', [$tenant, $this->access->workCenterOf($found)]);
    }

    private function userId(Request $request): int
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user->id;
    }
}
