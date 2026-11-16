<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Middleware;

use App\Modules\WorkerAccess\Contracts\WorkerSession;
use App\Modules\WorkerAccess\Support\WorkerDestination;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page of the person (`me/{tenant:slug}/...`, after ResolveWorkerTenant) takes their session. A visitor goes to the
 * entrance of that company, which keeps the page they asked for and takes them there once they enter; a client that asks for
 * JSON gets a 401. It never makes the person the user of the request: the panel's user stays who it is, and the logs' context
 * never names the person.
 */
final readonly class RequireWorkerSession
{
    public function __construct(private WorkerSession $session, private WorkerDestination $destination) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->session->current() !== null) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 401);
        $slug = $request->route('tenant');
        $slug = is_string($slug) ? $slug : '';
        $this->destination->remember($request, $slug);

        return redirect()->route('worker.home', $slug);
    }
}
