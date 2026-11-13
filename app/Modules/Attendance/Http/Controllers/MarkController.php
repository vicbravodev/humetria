<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\RecordMark;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Exceptions\InvalidCredential;
use App\Modules\Attendance\Exceptions\InvalidMarkSequence;
use App\Modules\Attendance\Exceptions\MarkRejected;
use App\Modules\Attendance\Http\Requests\MarkRequest;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Support\CompanyPolicy;
use App\Modules\Attendance\Support\MarkPolicyCheck;
use App\Modules\Attendance\Support\PunchDay;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Attendance\Support\WebAuthnCeremony;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A person marks from their linked device, with no account (criterios 3.3, 4.2 to 4.4). The order of the checks:
 * the company only from the slug (ResolveDeviceTenant); the body validated; the signature verified against a challenge
 * given to this browser's session, which alone names the person (their key's user handle), never the body; the person's
 * current work center then decides which code and which zones count; the policy decides how the mark is taken
 * (MarkPolicyCheck); RecordMark stamps the server's time and keeps the sequence. A signature that does not verify, of a
 * revoked device, of a person who left or of another company gets the same 404. Logs carry ids and reasons only: never a
 * position, the code or the key.
 *
 * The browser's session remembers who last marked in it for 10 minutes (config/attendance.php), only to offer their next step (never their
 * marks); «¿No es usted?» forgets them at once, and so does a person with no device in use or who left. Every answer to a
 * mark names the person the signature verified and their own next steps, so a second person on the same browser is
 * never shown the first one's.
 */
final readonly class MarkController
{
    private const string SESSION_KEY = 'attendance.person';

    public function __construct(private Roster $roster, private Companies $companies, private CurrentTenant $tenant) {}

    /**
     * The one page of the app that reads the device's position: the panel's Permissions-Policy denies geolocation, this
     * page allows it to its own origin only (SecurityHeaders keeps a header the response already carries).
     */
    public function show(Request $request, string $tenant): SymfonyResponse
    {
        $person = $this->remembered($request);
        $q = $request->query('q');
        $qr = is_string($q) && $q !== '' && strlen($q) <= 128 ? $q : null;

        $page = Inertia::render('attendance/punch', [
            'company' => $this->companies->current()->tradeName,
            'qr' => $qr,
            // Who taps next may be someone else: the position is always asked when marking (and dropped by the server when
            // the verified person does not need it).
            'person' => $person === null ? null : ['name' => $person->name, 'next' => PunchDay::of($person->id)['next']],
            'optionsUrl' => route('attendance.device.punch.options', $tenant),
            'punchUrl' => route('attendance.device.punch.store', $tenant),
            'forgetUrl' => route('attendance.device.punch.forget', $tenant),
            'weekUrl' => route('attendance.device.week.show', $tenant),
            'correctionOptionsUrl' => route('attendance.device.correction.options', $tenant),
            'correctionUrl' => route('attendance.device.correction.store', $tenant),
            // A mark refused is asked of RH at the time of the company's clock, whatever the phone's zone.
            'timezone' => config()->string('app.display_timezone'),
        ]);

        $response = $page->toResponse($request);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=()');

        return $response;
    }

    /** «¿No es usted?»: this browser forgets who marked in it, at once. */
    public function forget(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return back();
    }

    public function options(Request $request, WebAuthnCeremony $ceremony): JsonResponse
    {
        return response()->json($ceremony->assertionOptions($request->session()->getId()))->header('Cache-Control', 'no-store, private');
    }

    public function store(MarkRequest $request, WebAuthnCeremony $ceremony, RecordMark $record): JsonResponse
    {
        try {
            $signed = $ceremony->verifyAssertion($request->session()->getId(), $request->credential());
        } catch (InvalidCredential) {
            // Ids only: never the credential, the address or the body.
            Log::warning('attendance.mark_unverified', ['tenant_id' => $this->tenant->id(), 'request_id' => Context::get('request_id')]);
            abort(404);
        }

        $person = $this->roster->find($signed->employeeId);
        abort_if($person === null || ! $person->active, 404);
        $request->session()->put(self::SESSION_KEY, ['id' => $person->id, 'at' => CarbonImmutable::now()->getTimestamp()]);

        $qr = $request->qr();
        $zones = AttendanceZone::query()->where('work_center_id', $person->workCenterId)->get()->all();

        try {
            $context = MarkPolicyCheck::decide(
                $this->schedule($person->id),
                CompanyPolicy::current(),
                $person->trusted,
                $qr === null ? null : QrToken::verify($qr, $this->tenant->id(), $person->workCenterId, CarbonImmutable::now()),
                $zones,
                $request->position(),
                $signed->deviceId,
                KioskDevice::query()->where('work_center_id', $person->workCenterId)->whereNotNull('last_seen_at')->whereNull('revoked_at')->count(),
            );
            $record->handle($person->id, $request->type(), $context);
        } catch (MarkRejected|InvalidMarkSequence $rejected) {
            Log::info('attendance.mark_rejected', ['employee_id' => $person->id, 'device_id' => $signed->deviceId, 'type' => $request->type()->value, 'reason' => $rejected->reason]);

            // Who signed and what they may do: never the steps of whoever marked before on this browser.
            return response()->json(['reason' => $rejected->reason, 'name' => $person->name, 'next' => PunchDay::of($person->id)['next']], 422);
        }

        $onsite = in_array($context->method, [MarkMethod::QrAndGeofence, MarkMethod::Qr, MarkMethod::Geofence], true);

        return response()->json([
            'name' => $person->name,
            ...PunchDay::of($person->id),
            // For the screen now and the panel later: a center with no zones, a mark taken with no position, or outside every zone.
            'flag' => match (true) {
                ! $onsite => null,
                $zones === [] => 'center_without_zones',
                $context->latitude === null => 'no_location',
                $context->zoneId === null => 'outside_zone',
                default => null,
            },
            // How much the mark proves, for the person to read in words (never where it was taken).
            'evidence' => $context->method->evidence()->value,
        ]);
    }

    /**
     * The person who marked in this browser in the last 10 minutes, while they are active and have a device in use;
     * forgotten otherwise.
     */
    private function remembered(Request $request): ?RosterEntry
    {
        $memory = $request->session()->get(self::SESSION_KEY);
        $id = is_array($memory) && is_int($memory['at'] ?? null) && CarbonImmutable::now()->getTimestamp() - $memory['at'] < config()->integer('attendance.punch.remember_minutes') * 60
            ? $memory['id'] ?? null
            : null;
        $person = is_string($id) ? $this->roster->find($id) : null;

        if ($person === null || ! $person->active || ! AttendanceDevice::query()->where('employee_id', $person->id)->whereNull('revoked_at')->exists()) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $person;
    }

    private function schedule(string $employeeId): ?AttendanceSchedule
    {
        return AttendanceSchedule::query()->where('employee_id', $employeeId)->first();
    }
}
