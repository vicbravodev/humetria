<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\ConfirmWeek;
use App\Modules\Attendance\Actions\DisputeWeek;
use App\Modules\Attendance\Exceptions\WeekRejected;
use App\Modules\Attendance\Http\Middleware\AttachOfflineTickets;
use App\Modules\Attendance\Http\Requests\ConfirmRequest;
use App\Modules\Attendance\Http\Requests\DisputeRequest;
use App\Modules\Attendance\Http\Requests\WeekRequest;
use App\Modules\Attendance\Support\PersonWeek;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
use App\Modules\WorkerAccess\Data\SignedBy;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mi semana» (criterios 5.2 and 8.1): a person sees one of their weeks and confirms or disputes it from their linked
 * device, with no account. The page shows nothing until a key signs; each read, each confirmation and each dispute takes a
 * signature of its own (a fresh challenge of this browser's session, good once, for the week only: a challenge given to
 * mark does not serve here). The signature alone names the person; nothing of them is kept in the browser. A signature
 * that does not verify, of a revoked device, of a person who left or of another company gets the same 404, as in Marcar.
 * An answer carries the digest of the week read; when the week changed since, it is refused with the week as it is now.
 */
final readonly class WeekController
{
    public function __construct(private Roster $roster, private Companies $companies, private CurrentTenant $tenant, private Timezones $timezones) {}

    public function show(string $tenant, ?string $start = null): Response
    {
        if ($start !== null) {
            self::period($start);
        }

        return Inertia::render('attendance/week', [
            'company' => $this->companies->current()->tradeName,
            'start' => $start,
            'optionsUrl' => route('attendance.device.week.options', $tenant),
            'weekUrl' => route('attendance.device.week.read', $tenant),
            'punchUrl' => route('attendance.device.punch.show', $tenant),
            'correctionOptionsUrl' => route('attendance.device.correction.options', $tenant),
            'correctionUrl' => route('attendance.device.correction.store', $tenant),
        ]);
    }

    public function options(Request $request, WorkerSignatures $signatures): JsonResponse
    {
        return response()->json($signatures->options(self::context($request)))->header('Cache-Control', 'no-store, private');
    }

    public function read(WeekRequest $request, WorkerSignatures $signatures, string $tenant): JsonResponse
    {
        $signed = $this->verified($request, $signatures, $request->credential());

        // None asked for: the last week that is over, the one to answer.
        return $this->week($signed, $request->start(), $tenant);
    }

    public function confirm(ConfirmRequest $request, WorkerSignatures $signatures, ConfirmWeek $confirm, string $tenant, string $start): JsonResponse
    {
        $week = self::period($start);
        $signed = $this->verified($request, $signatures, $request->credential());

        try {
            $confirm->handle($signed->employeeId, $week, $signed->credentialId, $request->digest());
        } catch (WeekRejected $rejected) {
            return $this->rejected($rejected, $signed, $week, $tenant);
        }

        return $this->week($signed, $week, $tenant);
    }

    public function dispute(DisputeRequest $request, WorkerSignatures $signatures, DisputeWeek $dispute, string $tenant, string $start): JsonResponse
    {
        $week = self::period($start);
        $signed = $this->verified($request, $signatures, $request->credential());

        try {
            $dispute->handle($signed->employeeId, $week, $request->reason(), $signed->credentialId, $request->digest());
        } catch (WeekRejected $rejected) {
            return $this->rejected($rejected, $signed, $week, $tenant);
        }

        return $this->week($signed, $week, $tenant);
    }

    /**
     * @param  array<mixed>  $credential
     */
    private function verified(Request $request, WorkerSignatures $signatures, array $credential): SignedBy
    {
        $signed = $signatures->verify(self::context($request), $credential);

        if ($signed === null) {
            // Ids only: never the credential, the address or the body.
            Log::warning('attendance.week_unverified', ['tenant_id' => $this->tenant->id(), 'request_id' => Context::get('request_id')]);
            abort(404);
        }

        // Whoever signed gets new tickets with the answer (a 404 after this still carries none).
        AttachOfflineTickets::signedBy($signed->employeeId);

        return $signed;
    }

    /** @param  CarbonImmutable|null  $week  a date of the week, in the person's zone; null for the last one that is over */
    private function week(SignedBy $signed, ?CarbonImmutable $week, string $tenant): JsonResponse
    {
        return response()->json($this->view($signed, $week, $tenant))->header('Cache-Control', 'no-store, private');
    }

    /** Why the answer was refused; a week that changed since it was read, or that has days without cover, goes back as it is now. */
    private function rejected(WeekRejected $rejected, SignedBy $signed, ?CarbonImmutable $week, string $tenant): JsonResponse
    {
        $body = ['reason' => $rejected->reason, ...(in_array($rejected->reason, ['week_changed', 'uncovered_days'], true) ? ['week' => $this->view($signed, $week, $tenant)] : [])];

        return response()->json($body, 422)->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array<string, mixed>
     */
    private function view(SignedBy $signed, ?CarbonImmutable $week, string $tenant): array
    {
        $person = $this->roster->find($signed->employeeId) ?? abort(404);
        // The person's week is read in the zone of their center.
        $timezone = $this->timezones->forCenter($person->workCenterId);
        $period = $week === null ? WeekPeriod::containing(CarbonImmutable::now()->subWeek(), $timezone) : WeekPeriod::ofDate($week, $timezone);

        return PersonWeek::of($person->id, $person->name, $period, $tenant);
    }

    /** The week's challenges are apart from those to mark. */
    private static function context(Request $request): string
    {
        return 'week:'.$request->session()->getId();
    }

    /** The date in the URL (Y-m-d, by the route), a day of the week asked for; a date that does not exist is a 404. */
    private static function period(string $date): CarbonImmutable
    {
        [$year, $month, $day] = array_map(intval(...), explode('-', $date));
        abort_unless(checkdate($month, $day, $year), 404);

        return CarbonImmutable::create($year, $month, $day) ?? abort(404);
    }
}
