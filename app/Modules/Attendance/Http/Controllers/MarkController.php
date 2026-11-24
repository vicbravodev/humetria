<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\AcceptAgreement;
use App\Modules\Attendance\Actions\RecordMark;
use App\Modules\Attendance\Data\MarkContext;
use App\Modules\Attendance\Data\Position;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Exceptions\AgreementOutdated;
use App\Modules\Attendance\Exceptions\InvalidMarkSequence;
use App\Modules\Attendance\Exceptions\MarkRejected;
use App\Modules\Attendance\Http\Middleware\AttachOfflineTickets;
use App\Modules\Attendance\Http\Requests\MarkRequest;
use App\Modules\Attendance\Models\AttendanceCenterMode;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Support\AgreementStatus;
use App\Modules\Attendance\Support\CompanyPolicy;
use App\Modules\Attendance\Support\CurrentAgreement;
use App\Modules\Attendance\Support\MarkPolicyCheck;
use App\Modules\Attendance\Support\PunchDay;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Attendance\Support\ScheduleTimeline;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use App\Modules\WorkerAccess\Contracts\WorkerCredentials;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
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
 * current work center then decides which code and which zones count; a person who has not accepted the current
 * agreement is refused first, with its text (criterio 5.1); the policy decides how the mark is taken
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

    /** The code this browser scanned when it opened the page, while it counted: its center and when (hallazgo 27). */
    private const string SCAN_KEY = 'attendance.scan';

    public function __construct(private Roster $roster, private Companies $companies, private CurrentTenant $tenant, private WorkerCredentials $credentials, private Timezones $timezones, private WorkCenters $workCenters, private WorkerSession $session) {}

    /**
     * The page that reads the device's position and the camera (the QR of the center's screen): the panel's
     * Permissions-Policy denies both, and SecurityHeaders opens them to their own origin for every page under `/me/{slug}`.
     */
    public function show(Request $request, string $tenant): SymfonyResponse
    {
        $person = $this->remembered($request);
        // Who this page speaks to, for how they mark and their clock: who marked here, or else the person of the portal's
        // session (never shown as them: whoever taps signs).
        $known = $person ?? $this->fromPortal();
        // The day and the clock of that person, in their center's zone; the company's before anyone is known.
        $timezone = $known === null ? $this->timezones->forTenant() : $this->timezones->forCenter($known->workCenterId);
        $q = $request->query('q');
        $qr = is_string($q) && $q !== '' && strlen($q) <= 128 ? $q : null;
        // A code that counts now is kept for this browser a few minutes: accepting the agreement and marking may outlast it.
        $scanned = $qr === null ? null : $this->centerOf($qr, null, CarbonImmutable::now());

        if ($scanned !== null) {
            $request->session()->put(self::SCAN_KEY, ['q' => $qr, 'center' => $scanned->id, 'at' => CarbonImmutable::now()->getTimestamp()]);
        }

        $page = Inertia::render('attendance/punch', [
            'company' => $this->companies->current()->tradeName,
            'qr' => $qr,
            // When the code was read, at the clock of its center: «Escaneó a las 09:00, tiene 3 minutos».
            'scannedAt' => $scanned === null ? null : CarbonImmutable::now($this->timezones->forCenter($scanned->id))->format('H:i'),
            // Who taps next may be someone else: the position is always asked when marking (and dropped by the server when
            // the verified person does not need it).
            'person' => $person === null ? null : ['name' => $person->name, 'next' => PunchDay::of($person->id, $timezone)['next']],
            'optionsUrl' => route('attendance.device.punch.options', $tenant),
            'punchUrl' => route('attendance.device.punch.store', $tenant),
            'forgetUrl' => route('attendance.device.punch.forget', $tenant),
            // The company's address segment and where the phone swaps a ticket for a new batch (it keeps them to mark without a connection).
            'slug' => $tenant,
            'ticketsUrl' => route('attendance.device.tickets', $tenant),
            'deliverUrl' => route('attendance.device.punch.offline', $tenant),
            'weekUrl' => route('attendance.device.week.show', $tenant),
            // Where a person without a key here adds one from a session; the first one comes only from RH's link.
            'enterUrl' => route('worker.home', $tenant),
            'correctionOptionsUrl' => route('attendance.device.correction.options', $tenant),
            'correctionUrl' => route('attendance.device.correction.store', $tenant),
            'agreementOptionsUrl' => route('attendance.device.agreement.options', $tenant),
            'agreementUrl' => route('attendance.device.agreement.accept', $tenant),
            // A mark refused is asked of RH at the time of the center's clock, whatever the phone's zone.
            'timezone' => $timezone,
            // How they mark: a remote person is asked for no code nor zone (their position is dropped); null for nobody known.
            'mode' => $known === null ? null : ($this->schedule($known->id, $timezone)?->work_mode === WorkMode::Remote ? 'remote' : 'onsite'),
            // The server's clock, in milliseconds: the page's clock follows it, not the phone's.
            'now' => CarbonImmutable::now()->getTimestampMs(),
        ]);

        return $page->toResponse($request);
    }

    /** «¿No es usted?»: this browser forgets who marked in it, at once. */
    public function forget(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return back();
    }

    public function options(Request $request, WorkerSignatures $signatures): JsonResponse
    {
        return response()->json($signatures->options($request->session()->getId()))->header('Cache-Control', 'no-store, private');
    }

    public function store(MarkRequest $request, WorkerSignatures $signatures, RecordMark $record, AgreementStatus $agreements, CurrentAgreement $agreement, AcceptAgreement $accept): JsonResponse
    {
        $signed = $signatures->verify($request->session()->getId(), $request->credential());

        if ($signed === null) {
            // Ids only: never the credential, the address or the body.
            Log::warning('attendance.mark_unverified', ['tenant_id' => $this->tenant->id(), 'request_id' => Context::get('request_id')]);
            abort(404);
        }

        $person = $this->roster->find($signed->employeeId);
        abort_if($person === null || ! $person->active, 404);
        // Whoever answers from here on (the mark, a refusal) hands the phone its tickets and context.
        AttachOfflineTickets::signedBy($person->id);
        $timezone = $this->timezones->forCenter($person->workCenterId);
        $request->session()->put(self::SESSION_KEY, ['id' => $person->id, 'at' => CarbonImmutable::now()->getTimestamp()]);

        $qr = $request->qr();
        $now = CarbonImmutable::now();
        $scan = $qr === null ? null : $this->scan($request, $qr, $now);
        $qrValid = $qr === null ? null : ($scan !== null ? $scan->id === $person->workCenterId : QrToken::verify($qr, $this->tenant->id(), $person->workCenterId, $now));
        // A code that is not of the person's center may be of another one of the company: a trip, not a lie (hallazgo 26).
        $away = $qr !== null && ! $qrValid ? $scan ?? $this->centerOf($qr, $person->workCenterId, $now) : null;
        $zones = AttendanceZone::query()->where('work_center_id', $person->workCenterId)->get()->all();
        // How they work today, by the schedule in effect today at their center.
        $schedule = $this->schedule($person->id, $timezone);

        try {
            // The agreement first: a person who has not accepted it is not sent to fix the code or the position for nothing.
            // The version they read may come with the mark, accepted with this same signature before it (hallazgo 27).
            if (! $agreements->accepted($person->id)) {
                $this->accept($accept, $person->id, $signed->credentialId, $request->agreementVersion());
            }

            $context = $away !== null
                ? MarkPolicyCheck::away($schedule, CompanyPolicy::current(), $away->id, $request->position(), $signed->credentialId)
                : $this->decide($person, $schedule, $qrValid, $qr !== null && $qrValid === false && QrToken::expired($qr, $this->tenant->id(), $person->workCenterId, $now), $zones, $request->position(), $signed->credentialId);
            $record->handle($person->id, $request->type(), $context);
        } catch (MarkRejected|InvalidMarkSequence $rejected) {
            Log::info('attendance.mark_rejected', ['employee_id' => $person->id, 'key_id' => $signed->credentialId, 'type' => $request->type()->value, 'reason' => $rejected->reason]);

            // Who signed and what they may do: never the steps of whoever marked before on this browser.
            $current = $rejected->reason === 'agreement_not_accepted' ? $agreement->get() : null;

            return response()->json([
                'reason' => $rejected->reason,
                'name' => $person->name,
                'next' => PunchDay::of($person->id, $timezone)['next'],
                // What to read and accept, for the screen to show it.
                ...($current === null ? [] : ['agreement' => ['version' => $current->version, 'body' => $current->body]]),
                // Whose code it was, for the screen to send the person to the one of their center.
                ...($rejected->reason === 'qr_other_center' && $away !== null ? ['center' => $away->name] : []),
            ], 422);
        }

        $onsite = in_array($context->method, [MarkMethod::QrAndGeofence, MarkMethod::Qr, MarkMethod::Geofence], true);

        return response()->json([
            'name' => $person->name,
            ...PunchDay::of($person->id, $timezone),
            // For the screen now and the panel later: a mark in a field center (the person is not told: it is their mode), a
            // center with no zones, a mark taken with no position, or outside every zone.
            'flag' => match (true) {
                // Taken at another center of the company: flagged «fuera de su centro», never a lateness.
                $context->awayCenterId !== null => 'away_center',
                in_array($context->method, [MarkMethod::Field, MarkMethod::FieldUnlocated], true) => 'field',
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

        if ($person === null || ! $person->active || ($this->credentials->countsByEmployee([$person->id])[$person->id]->active ?? 0) === 0) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $person;
    }

    /**
     * Accepts the version of the agreement read, with the key that signed the mark; refused with the current text when none
     * came or it is not the current one.
     *
     * @throws MarkRejected
     */
    private function accept(AcceptAgreement $accept, string $employeeId, string $credentialId, ?int $version): void
    {
        if ($version === null) {
            throw new MarkRejected('agreement_not_accepted');
        }

        try {
            $accept->handle($employeeId, $credentialId, $version);
        } catch (AgreementOutdated) {
            throw new MarkRejected('agreement_not_accepted');
        }
    }

    /** The person whose portal session is open in this browser (WorkerSession forgets one who left); null for none. */
    private function fromPortal(): ?RosterEntry
    {
        $identity = $this->session->current();

        return $identity === null ? null : $this->roster->find($identity->employeeId);
    }

    /**
     * How the policy takes the mark at the person's own center; a code of it that no longer counts is told apart.
     *
     * @param  array<array-key, AttendanceZone>  $zones
     *
     * @throws MarkRejected
     */
    private function decide(RosterEntry $person, ?AttendanceSchedule $schedule, ?bool $qrValid, bool $expired, array $zones, ?Position $position, string $credentialId): MarkContext
    {
        try {
            return MarkPolicyCheck::decide(
                $schedule,
                CompanyPolicy::current(),
                $person->trusted,
                $qrValid,
                $zones,
                $position,
                $credentialId,
                KioskDevice::inUseIn($person->workCenterId),
                AttendanceCenterMode::isField($person->workCenterId),
            );
        } catch (MarkRejected $rejected) {
            throw $rejected->reason === 'qr_invalid' && $expired ? new MarkRejected('qr_expired') : $rejected;
        }
    }

    /** The center of the company in use, other than `$except`, whose code this is now; null for none. */
    private function centerOf(string $qr, ?string $except, CarbonImmutable $now): ?WorkCenterSummary
    {
        // ponytail: two HMACs per center of the company; fine for hundreds of centers, an index of codes past that.
        return array_find(
            $this->workCenters->all(),
            fn (WorkCenterSummary $center): bool => ! $center->archived && $center->id !== $except && QrToken::verify($qr, $this->tenant->id(), $center->id, $now),
        );
    }

    /** The center of `$qr` when this browser scanned it on opening the page within the minutes of config/attendance.php; null otherwise. */
    private function scan(Request $request, string $qr, CarbonImmutable $now): ?WorkCenterSummary
    {
        $scan = $request->session()->get(self::SCAN_KEY);
        $fresh = is_array($scan) && ($scan['q'] ?? null) === $qr && is_int($scan['at'] ?? null)
            && $now->getTimestamp() - $scan['at'] <= config()->integer('attendance.qr.scan_minutes') * 60;

        return $fresh ? array_find($this->workCenters->all(), fn (WorkCenterSummary $center): bool => $center->id === $scan['center']) : null;
    }

    /** The schedule in effect today, in the center's zone, of the person; null with none yet. */
    private function schedule(string $employeeId, string $timezone): ?AttendanceSchedule
    {
        return ScheduleTimeline::of([$employeeId])->on($employeeId, CarbonImmutable::now($timezone));
    }
}
