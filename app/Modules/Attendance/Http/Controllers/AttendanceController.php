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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
            $this->filter($request->string('center')->toString()),
            $request->string('week')->toString(),
            $request->string('status')->toString(),
            $request->string('q')->trim()->limit(100, '')->toString(),
        ));
    }

    /**
     * A center's zones and screens. The panel's Permissions-Policy denies geolocation; this page's document allows it to its
     * own origin, for «Usar mi ubicación actual». A page opened inside the panel keeps the document it was opened from, so an
     * Inertia visit from any other page gets the whole document instead; one from this page (a save, another page of zones)
     * stays a visit, and what it flashed reaches it.
     */
    public function center(Request $request, mixed $tenant, string $workCenter, AttendancePages $pages): SymfonyResponse
    {
        Gate::authorize('view-attendance');
        $center = $this->workCenters->findActive($workCenter) ?? abort(404);

        $from = parse_url($request->headers->get('referer') ?? '', PHP_URL_PATH);

        if ($request->header('X-Inertia') !== null && $from !== '/'.$request->path()) {
            return Inertia::location($request->fullUrl());
        }

        $response = Inertia::render('attendance/center', $pages->center($center))->toResponse($request);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=()');

        return $response;
    }

    private function filter(string $id): ?WorkCenterSummary
    {
        if ($id === '') {
            return null;
        }

        return (Str::isUuid($id) ? $this->workCenters->findActive($id) : null) ?? abort(404);
    }
}
