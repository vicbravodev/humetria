<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\RequestCorrection;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Exceptions\InvalidCredential;
use App\Modules\Attendance\Http\Requests\AskCorrectionRequest;
use App\Modules\Attendance\Support\PersonWeek;
use App\Modules\Attendance\Support\WebAuthnCeremony;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/**
 * A person asks RH to correct their record from their linked device, with no account (criterio 5.4): from «Mi semana» (a
 * mark that is wrong, a mark missing in a day) or from Marcar when a mark was refused. Each request takes a signature of
 * its own, of a challenge given for corrections to this browser's session; the signature alone names the person. A
 * signature that does not verify, of a revoked device, of a person who left or of another company, and a mark that is not
 * one of the person's, get the same 404. The answer is the person's week with their requests in it.
 */
final readonly class CorrectionRequestController
{
    public function __construct(private Roster $roster, private CurrentTenant $tenant) {}

    public function options(Request $request, WebAuthnCeremony $ceremony): JsonResponse
    {
        return response()->json($ceremony->assertionOptions(self::context($request)))->header('Cache-Control', 'no-store, private');
    }

    public function store(AskCorrectionRequest $request, WebAuthnCeremony $ceremony, RequestCorrection $ask, string $tenant): JsonResponse
    {
        try {
            $signed = $ceremony->verifyAssertion(self::context($request), $request->credential());
        } catch (InvalidCredential) {
            // Ids only: never the credential, the address or the body.
            Log::warning('attendance.correction_unverified', ['tenant_id' => $this->tenant->id(), 'request_id' => Context::get('request_id')]);
            abort(404);
        }

        $person = $this->roster->find($signed->employeeId) ?? abort(404);
        $proposedAt = $request->proposedAt();

        try {
            $ask->handle($person->id, $request->markId(), $request->type(), $proposedAt, $request->reason(), $signed->deviceId);
        } catch (CorrectionRejected $rejected) {
            abort_if($rejected->reason === 'mark_not_found', 404);

            return response()->json(['reason' => $rejected->reason], 422)->header('Cache-Control', 'no-store, private');
        }

        $start = $request->start();
        $week = $start === null ? WeekPeriod::containing($proposedAt) : WeekPeriod::ofDate($start);

        return response()->json(PersonWeek::of($person->id, $person->name, $week, $tenant))->header('Cache-Control', 'no-store, private');
    }

    /** The challenges of corrections are apart from those to mark and to read the week. */
    private static function context(Request $request): string
    {
        return 'correction:'.$request->session()->getId();
    }
}
