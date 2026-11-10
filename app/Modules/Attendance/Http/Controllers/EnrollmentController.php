<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\EnrollDevice;
use App\Modules\Attendance\Exceptions\AgreementOutdated;
use App\Modules\Attendance\Exceptions\InvalidCredential;
use App\Modules\Attendance\Http\Requests\EnrollRequest;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Support\CurrentAgreement;
use App\Modules\Attendance\Support\WebAuthnCeremony;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A person links their device with the link RH sent them (no account): they read and accept the agreement and their
 * device creates a key (WebAuthn). A link that does not work (unknown, used, replaced, expired, of another company) or
 * whose person left gets the same 404 on every route.
 */
final readonly class EnrollmentController
{
    public function __construct(private Roster $roster, private Companies $companies) {}

    public function show(string $tenant, string $token, CurrentAgreement $agreement): Response
    {
        $person = $this->person($token);
        $current = $agreement->get();

        return Inertia::render('attendance/enroll', [
            'company' => $this->companies->current()->tradeName,
            'name' => $person->name,
            'jobTitle' => $person->jobTitle,
            'linkHours' => config()->integer('attendance.enrollment.link_hours'),
            'punchUrl' => route('attendance.device.punch.home', $tenant),
            'agreement' => ['version' => $current->version, 'body' => $current->body],
            'optionsUrl' => route('attendance.device.enroll.options', [$tenant, $token]),
            'registerUrl' => route('attendance.device.enroll.register', [$tenant, $token]),
        ]);
    }

    public function options(string $tenant, string $token, WebAuthnCeremony $ceremony): JsonResponse
    {
        $person = $this->person($token);

        return response()->json($ceremony->registrationOptions($person->id, $person->name))->header('Cache-Control', 'no-store, private');
    }

    public function register(EnrollRequest $request, string $tenant, string $token, EnrollDevice $enroll): JsonResponse
    {
        try {
            $device = $enroll->handle($token, $request->integer('agreement_version'), $request->credential(), $request->string('device_label')->toString());
        } catch (InvalidCredential) {
            return response()->json(['message' => 'No se pudo crear la llave. Intente de nuevo.'], 422);
        } catch (AgreementOutdated) {
            return response()->json(['message' => 'El acuerdo cambió. Recargue la página para leer la versión nueva.'], 409);
        }

        abort_if($device === null, 404);

        return response()->json(['enrolled' => true]);
    }

    /** The active person of a link that still works; 404, the same for every cause, otherwise. */
    private function person(string $token): RosterEntry
    {
        $link = EnrollmentLink::findPending($token);
        $person = $link === null ? null : $this->roster->find($link->employee_id);

        abort_if($person === null || ! $person->active, 404);

        return $person;
    }
}
