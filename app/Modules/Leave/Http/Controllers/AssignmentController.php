<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Actions\AssignPolicy;
use App\Modules\Leave\Http\Requests\AssignmentRequest;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Organization\Data\JobGradeData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Whom each policy goes to: owner and hr set it; the job grades are looked up on the server, as many as the company has.
 */
final readonly class AssignmentController
{
    public const int OPTIONS_LIMIT = 20;

    public function __construct(private JobGrades $jobGrades) {}

    public function update(AssignmentRequest $request, AssignPolicy $assign): RedirectResponse
    {
        $assign->handle($request->scope(), $request->policyId(), $request->staffGroup(), $request->jobGradeId());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asignación guardada. Los periodos ya otorgados no cambian.']);

        return back();
    }

    /**
     * The categories and levels that have what was typed, for the field that picks a grade to assign a policy to.
     *
     * @return list<array{value: string, label: string}>
     */
    public function grades(Request $request): array
    {
        Gate::authorize('manage-vacations');

        return array_values(array_map(
            fn (JobGradeData $grade): array => ['value' => $grade->id, 'label' => "{$grade->category} · {$grade->level}"],
            $this->jobGrades->page($request->string('q')->trim()->limit(100, '')->toString(), self::OPTIONS_LIMIT)->items(),
        ));
    }
}
