<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerCredentials;
use App\Modules\WorkerAccess\Data\CredentialSummary;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mi jornada» in the personal portal: no account, the link's token is the credential and says whose it is, never the
 * request. It only leads to marking and «Mi semana» (which the person's key signs, never this link) and shows the keys in
 * use. It never creates a key: the first comes from RH's link and another is added from the person's entrance, with a
 * session (`worker.home`). A link that is not valid, or of a company without the registro de jornada (RequirePortalTool),
 * gets a 404 that says nothing else.
 */
final readonly class PortalController
{
    public function __construct(private PortalAccess $access, private Companies $companies, private WorkerCredentials $credentials) {}

    public function show(string $token): Response
    {
        $person = $this->access->open($token);
        abort_if($person === null, 404);

        $slug = $this->companies->current()->slug;

        return Inertia::render('attendance/portal', [
            'token' => $token,
            'company' => $person->company,
            'name' => $person->name,
            'devices' => array_values(array_map(fn (CredentialSummary $device): array => [
                'label' => $device->label,
                'enrolledOn' => $device->registeredAt->toDateString(),
                'lastUsedOn' => $device->lastUsedAt?->toDateString(),
            ], array_reverse(array_filter($this->credentials->ofEmployees([$person->employeeId])[$person->employeeId] ?? [], fn (CredentialSummary $device): bool => $device->revokedAt === null)))),
            'markUrl' => route('attendance.device.punch.home', $slug),
            'weekUrl' => route('attendance.device.week.show', $slug),
            'keyUrl' => route('worker.home', $slug),
            'homeUrl' => route('portal.show', $token),
        ]);
    }
}
