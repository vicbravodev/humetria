<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\WorkerAccess\Actions\ProveWithCode;
use App\Modules\WorkerAccess\Actions\SendProofCode;
use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
use App\Modules\WorkerAccess\Http\Requests\ProofCodeRequest;
use App\Modules\WorkerAccess\Http\Requests\ProveRequest;
use App\Modules\WorkerAccess\Support\OpenSession;
use App\Modules\WorkerAccess\Support\ProofSteps;
use App\Modules\WorkerAccess\Support\RequestWorkerSession;
use App\Modules\WorkerAccess\Support\WorkerDestination;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The person of the session proves it is them before something delicate (spec «Prueba reciente»): with their key, or, without
 * one, with a code by email. A proof marks the session (WorkerSessionStore) and takes them where they were going. Every
 * signature that is not a key of theirs in use gets the same answer. Logs carry ids only: never the code or the credential.
 */
final readonly class ProofController
{
    public function __construct(private RequestWorkerSession $sessions, private WorkerSessionStore $store) {}

    public function show(string $tenant, ProofSteps $steps): Response
    {
        $session = $this->session();

        return Inertia::render('worker-access/proof', [
            'company' => $session->identity->companyName,
            'step' => $steps->for($session, $tenant),
            'homeUrl' => route('worker.home', $tenant),
        ]);
    }

    public function options(Request $request, WorkerSignatures $signatures): JsonResponse
    {
        return response()->json($signatures->options(self::context($request)))->header('Cache-Control', 'no-store, private');
    }

    public function store(ProveRequest $request, string $tenant, WorkerSignatures $signatures, WorkerDestination $destination): JsonResponse
    {
        $person = $this->session()->identity->employeeId;
        $signed = $signatures->verify(self::context($request), $request->credential());

        if ($signed?->employeeId !== $person) {
            Log::warning('worker_access.proof_refused', ['employee_id' => $person, 'via' => 'key']);

            return response()->json(['message' => 'No pudimos comprobar su llave. Intente de nuevo.'], 422)->header('Cache-Control', 'no-store, private');
        }

        $this->store->prove();
        Log::info('worker_access.proof_given', ['employee_id' => $person, 'via' => 'key', 'key_id' => $signed->credentialId]);

        return response()->json(['redirect' => $destination->pull($tenant)])->header('Cache-Control', 'no-store, private');
    }

    public function sendCode(Request $request, SendProofCode $send): JsonResponse
    {
        $outcome = $send->handle($this->session()->identity->employeeId, $request->session()->getId());
        abort_if($outcome === 'refused', 403);
        abort_if($outcome === 'busy', 429);

        return response()->json(['sent' => true]);
    }

    public function checkCode(ProofCodeRequest $request, string $tenant, ProveWithCode $prove, WorkerDestination $destination): JsonResponse
    {
        $outcome = $prove->handle($this->session()->identity->employeeId, $request->session()->getId(), $request->string('code')->toString());
        abort_if($outcome === 'refused', 403);

        if ($outcome !== 'passed') {
            return response()->json(['reason' => $outcome], 422);
        }

        $this->store->prove();

        return response()->json(['redirect' => $destination->pull($tenant)])->header('Cache-Control', 'no-store, private');
    }

    /** The route takes the person's session (RequireWorkerSession). */
    private function session(): OpenSession
    {
        return $this->sessions->session() ?? abort(401);
    }

    /** The challenges to prove are apart from those to enter, to mark, to read the week or to register a key. */
    private static function context(Request $request): string
    {
        return 'proof:'.$request->session()->getId();
    }
}
