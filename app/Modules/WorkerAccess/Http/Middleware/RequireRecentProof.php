<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Middleware;

use App\Modules\WorkerAccess\Support\ProofSteps;
use App\Modules\WorkerAccess\Support\RequestWorkerSession;
use App\Modules\WorkerAccess\Support\WorkerDestination;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * What is delicate (alias `worker.proof`, after RequireWorkerSession) takes a recent proof: the person's key signed, or their
 * code was right, less than 5 minutes ago (config/worker_access.php). Without it the server gives no content and takes no
 * form: a page goes to the screen to prove and comes back once proved (WorkerDestination); a form goes back to the page it
 * was sent from (or the person's start) with the flash `proofRequired`, the step that opens the proof and sends it again; a
 * client that asks for JSON gets a 403 (a 401 without a session).
 */
final readonly class RequireRecentProof
{
    public function __construct(private RequestWorkerSession $sessions, private WorkerDestination $destination, private ProofSteps $steps) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->sessions->session();
        abort_if($session === null, 401);

        if ($session->proofAt instanceof CarbonImmutable && $session->proofAt->addMinutes(config()->integer('worker_access.proof.minutes'))->gt(CarbonImmutable::now())) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Confirme que es usted.', 'proofRequired' => true], 403);
        }

        $slug = $request->route('tenant');
        $slug = is_string($slug) ? $slug : '';

        if ($request->isMethodSafe()) {
            $this->destination->remember($request, $slug);

            return redirect()->route('worker.proof.show', $slug);
        }

        Inertia::flash('proofRequired', $this->steps->for($session, $slug));

        return redirect()->to($this->destination->back($slug));
    }
}
