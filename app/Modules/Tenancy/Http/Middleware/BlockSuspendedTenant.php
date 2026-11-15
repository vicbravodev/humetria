<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Http\Impersonation;
use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a restricted company from being changed. A suspended or archived one is blocked: a page request gets the blocked
 * page, a modal over the frame, and anything that would change something gets 423, with no effect. One whose trial ended,
 * whose term lapsed or that a person set read only keeps its pages for reading and exporting, and only changes get 423.
 * A superadmin impersonating the owner sees the real pages (the frame shows them the modal, and they can dismiss it),
 * but changes nothing either: a suspended company launches and sends nothing, whoever acts as its owner.
 * Runs after ResolveTenant, which has already bound the company and checked membership.
 */
final class BlockSuspendedTenant
{
    public function __construct(private readonly Impersonation $impersonation) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        $status = $tenant->status();

        if ($status->isReadOnly()) {
            return $this->readOnly($request, $next, $tenant);
        }

        if (! $status->blocksPanel()) {
            return $next($request);
        }

        if ($request->isMethodSafe()) {
            return $this->impersonation->isActive() ? $next($request) : Inertia::render('tenancy/blocked')->toResponse($request);
        }

        Log::warning('tenant.blocked_request_rejected', ['tenant_id' => $tenant->id]);

        abort(423);
    }

    /**
     * Reading and exporting go on; anything that would change something is rejected.
     *
     * @param  Closure(Request): Response  $next
     */
    private function readOnly(Request $request, Closure $next, Tenant $tenant): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        Log::warning('tenant.read_only_request_rejected', ['tenant_id' => $tenant->id]);

        abort(423);
    }
}
