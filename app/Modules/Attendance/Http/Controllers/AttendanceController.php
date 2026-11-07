<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Support\AttendancePages;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RH's overview of the registro de jornada: owner, hr and viewer read it. A center that is not one in use of the company is
 * a 404, as is all of it for a union.
 */
final readonly class AttendanceController
{
    public function __construct(private WorkCenters $workCenters) {}

    public function index(Request $request, AttendancePages $pages): Response
    {
        Gate::authorize('view-attendance');

        return Inertia::render('attendance/index', $pages->index(
            $this->center($request->string('center')->toString()),
            $request->string('week')->toString(),
            $request->string('status')->toString(),
            $request->string('q')->trim()->limit(100, '')->toString(),
        ));
    }

    private function center(string $id): ?WorkCenterSummary
    {
        if ($id === '') {
            return null;
        }

        return (Str::isUuid($id) ? $this->workCenters->findActive($id) : null) ?? abort(404);
    }
}
