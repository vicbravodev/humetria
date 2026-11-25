<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\AcceptAgreement;
use App\Modules\Attendance\Exceptions\AgreementOutdated;
use App\Modules\Attendance\Http\Middleware\AttachOfflineTickets;
use App\Modules\Attendance\Http\Requests\AcceptAgreementRequest;
use App\Modules\Attendance\Support\CurrentAgreement;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/**
 * A person accepts the agreement on the electronic record from Marcar, before their first mark or when its text changed
 * (criterio 5.1), with no account. The acceptance takes a signature of its own, of a challenge given for the agreement to
 * this browser's session; the signature alone names the person and is the key the acceptance records. A signature that
 * does not verify, of a revoked key, of a person who left or of another company gets the same 404; a version that is no
 * longer the current one gets a 409 with the current text, to read first.
 */
final readonly class AgreementController
{
    public function __construct(private Roster $roster, private CurrentTenant $tenant) {}

    public function options(Request $request, WorkerSignatures $signatures): JsonResponse
    {
        return response()->json($signatures->options(self::context($request)))->header('Cache-Control', 'no-store, private');
    }

    public function accept(AcceptAgreementRequest $request, WorkerSignatures $signatures, AcceptAgreement $accept, CurrentAgreement $agreement): JsonResponse
    {
        $signed = $signatures->verify(self::context($request), $request->credential());

        if ($signed === null) {
            // Ids only: never the credential, the address or the body.
            Log::warning('attendance.agreement_unverified', ['tenant_id' => $this->tenant->id(), 'request_id' => Context::get('request_id')]);
            abort(404);
        }

        $person = $this->roster->find($signed->employeeId);
        abort_if($person === null || ! $person->active, 404);
        AttachOfflineTickets::signedBy($person->id);

        try {
            $accept->handle($person->id, $signed->credentialId, $request->version());
        } catch (AgreementOutdated) {
            $current = $agreement->get();

            return response()->json(['reason' => 'agreement_outdated', 'agreement' => ['version' => $current->version, 'body' => $current->body]], 409)
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->json(['accepted' => true])->header('Cache-Control', 'no-store, private');
    }

    /** The challenges of the agreement are apart from those to mark, to read the week and to correct. */
    private static function context(Request $request): string
    {
        return 'agreement:'.$request->session()->getId();
    }
}
