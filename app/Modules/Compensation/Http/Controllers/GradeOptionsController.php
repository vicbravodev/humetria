<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Organization\Data\JobGradeData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The categories and levels of the pay scale that have what was typed, for the field that narrows a benefit to some of them. */
final class GradeOptionsController extends Controller
{
    public const int LIMIT = 20;

    /**
     * @param  mixed  $tenant  The company, already resolved by Tenancy from the URL; only routed through.
     * @return list<array{value: string, label: string}>
     */
    public function __invoke(Request $request, mixed $tenant, JobGrades $jobGrades): array
    {
        Gate::authorize('view-compensation');

        $search = $request->string('q')->trim()->limit(100, '')->toString();

        return array_values(array_map(
            fn (JobGradeData $grade): array => ['value' => $grade->id, 'label' => "{$grade->category} · {$grade->level}"],
            $jobGrades->page($search, self::LIMIT)->items(),
        ));
    }
}
