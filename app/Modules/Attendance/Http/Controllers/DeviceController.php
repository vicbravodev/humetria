<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\IssueEnrollment;
use App\Modules\Attendance\Actions\RevokeDevice;
use App\Modules\Attendance\Exceptions\EmployeeNotAvailable;
use App\Modules\Attendance\Http\Requests\EnrollPersonRequest;
use App\Modules\Attendance\Support\DevicePages;
use App\Modules\Attendance\Support\PanelScope;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people's linked devices: owner, hr and viewer read them; owner and hr send the link to link one and revoke one. The
 * link goes only to the person's email; RH sees it once, to hand it over, only for a person without one. A person or a
 * device that is not the company's is a 404.
 */
final readonly class DeviceController
{
    public function __construct(private WorkCenters $workCenters) {}

    public function index(Request $request, DevicePages $pages): Response
    {
        Gate::authorize('view-attendance');

        return Inertia::render('attendance/devices', $pages->index(
            PanelScope::center($this->workCenters, $request->string('center')->toString()),
            $request->string('status')->toString(),
            $request->string('q')->trim()->limit(100, '')->toString(),
        ));
    }

    public function enroll(EnrollPersonRequest $request, IssueEnrollment $issue, Roster $roster): RedirectResponse
    {
        $person = $roster->find($request->employeeId());

        try {
            $issued = $issue->handle($request->employeeId(), (string) ($request->user() ?? abort(401))->id);
        } catch (EmployeeNotAvailable) {
            abort(404);
        }

        $name = $person === null ? '' : $person->name;

        if ($issued->manualUrl === null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => "Enviamos el enlace de alta al correo de {$name}."]);
        } else {
            // Shown this once: never kept, never logged.
            Inertia::flash('enrollmentLink', ['name' => $name, 'url' => $issued->manualUrl]);
        }

        return back();
    }

    public function revoke(mixed $tenant, string $device, RevokeDevice $revoke): RedirectResponse
    {
        Gate::authorize('manage-attendance');
        $revoke->handle($device);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dispositivo revocado. Ya no puede marcar.']);

        return back();
    }
}
