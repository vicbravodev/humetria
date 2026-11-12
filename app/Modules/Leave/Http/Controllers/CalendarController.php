<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Support\CalendarPages;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The team's calendar of a month: owner, hr and viewer read it. A work center that is not one in use of the company, or an
 * id that is not a uuid, is a 404.
 */
final readonly class CalendarController
{
    public function __construct(private WorkCenters $centers) {}

    public function index(Request $request, LeaveDefaults $defaults, CalendarPages $pages): Response
    {
        Gate::authorize('view-vacations');
        $defaults->ensure();
        $center = $request->string('work_center')->toString();
        $area = $request->string('area')->toString();

        return Inertia::render('leave/calendar', $pages->index(
            CalendarPages::monthOf($request->string('month')->toString()),
            $center === '' ? null : (Str::isUuid($center) && $this->centers->findActive($center) !== null ? $center : abort(404)),
            $area === '' ? null : (Str::isUuid($area) ? $area : abort(404)),
            Gate::allows('manage-vacations'),
            $request->boolean('with_absence', true),
        ));
    }
}
