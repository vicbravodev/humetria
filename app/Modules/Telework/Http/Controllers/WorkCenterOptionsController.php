<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Support\VisibleCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The work centers a NOM-037 field may search: all in use for owner, hr and viewer, only the assigned ones for the
 * committee (which cannot use the company-wide options route).
 */
final class WorkCenterOptionsController extends Controller
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function __invoke(Request $request, VisibleCenters $centers): array
    {
        Gate::authorize('view-telework');
        $user = $request->user();
        abort_if($user === null, 403);

        return array_map(fn (WorkCenterSummary $center): array => ['value' => $center->id, 'label' => $center->name], $centers->search($user->id, $request->string('q')->limit(100, '')->toString()));
    }
}
