<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\IssueSelfEnrollment;
use App\Modules\Attendance\Exceptions\EmployeeNotAvailable;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mi jornada» in the personal portal: no account, the link's token is the credential and says whose it is, never the
 * request. It only leads to marking and «Mi semana» (which the passkey of the person's phone signs, never this link) and
 * to linking a phone. A link that is not valid, or of a company without the registro de jornada (RequirePortalTool), gets
 * a 404 that says nothing else.
 */
final readonly class PortalController
{
    public function __construct(private PortalAccess $access, private Companies $companies) {}

    public function show(string $token): Response
    {
        $person = $this->access->open($token);
        abort_if($person === null, 404);

        $slug = $this->companies->current()->slug;

        return Inertia::render('attendance/portal', [
            'token' => $token,
            'company' => $person->company,
            'name' => $person->name,
            'devices' => AttendanceDevice::query()->where('employee_id', $person->employeeId)->whereNull('revoked_at')->oldest('enrolled_at')->get()
                ->map(fn (AttendanceDevice $device): array => [
                    'label' => $device->label,
                    'enrolledOn' => $device->enrolled_at->toDateString(),
                    'lastUsedOn' => $device->last_used_at?->toDateString(),
                ])->all(),
            'markUrl' => route('attendance.device.punch.home', $slug),
            'weekUrl' => route('attendance.device.week.show', $slug),
            'enrollUrl' => route('portal.attendance.enroll', $token),
            'homeUrl' => route('portal.show', $token),
        ]);
    }

    /** The person asks for the link of their phone: it is theirs alone, so they are sent to it at once. */
    public function enroll(string $token, IssueSelfEnrollment $issue): RedirectResponse
    {
        $person = $this->access->open($token);
        abort_if($person === null, 404);

        try {
            return redirect()->to($issue->handle($person->employeeId));
        } catch (EmployeeNotAvailable) {
            abort(404);
        }
    }
}
