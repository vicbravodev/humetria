<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\IssueEnrollment;
use App\Modules\Attendance\Actions\RevokeDevice;
use App\Modules\Attendance\Actions\SendEnrollmentLinks;
use App\Modules\Attendance\Exceptions\EmployeeNotAvailable;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Http\Requests\EnrollCenterRequest;
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

    /** Their link to every person of a center without a device; who has no email is counted, for RH to hand theirs over. */
    public function enrollAll(EnrollCenterRequest $request, SendEnrollmentLinks $send): RedirectResponse
    {
        try {
            $counts = $send->handle($request->workCenterId(), (string) ($request->user() ?? abort(401))->id);
        } catch (WorkCenterNotAvailable) {
            abort(404);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => self::batchMessage($counts['sent'], $counts['without_email'])]);

        return back();
    }

    public function revoke(mixed $tenant, string $device, RevokeDevice $revoke): RedirectResponse
    {
        Gate::authorize('manage-attendance');
        $revoke->handle($device);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dispositivo revocado. Ya no puede marcar.']);

        return back();
    }

    private static function batchMessage(int $sent, int $withoutEmail): string
    {
        if ($sent === 0 && $withoutEmail === 0) {
            return 'No había a quién enviar: todas las personas del centro tienen dispositivo o no tienen correo.';
        }

        $message = match ($sent) {
            0 => 'No enviamos ningún enlace.',
            1 => 'Enviamos 1 enlace de alta.',
            default => "Enviamos {$sent} enlaces de alta.",
        };

        return match ($withoutEmail) {
            0 => $message,
            1 => "{$message} 1 persona no tiene correo: crea su enlace desde su renglón.",
            default => "{$message} {$withoutEmail} personas no tienen correo: crea su enlace desde su renglón.",
        };
    }
}
