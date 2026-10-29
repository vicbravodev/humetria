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
 * Keeps a suspended company (trial ended, expired or deactivated) from being used. A page request gets
 * the blocked page, a modal over the frame; anything that would change something gets 423, with no effect.
 * A superadmin impersonating the owner passes: the frame shows them the modal, and they can dismiss it.
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

        if (! $tenant instanceof Tenant || ! $tenant->status()->isBlocked() || $this->impersonation->isActive()) {
            return $next($request);
        }

        if ($request->isMethodSafe()) {
            return Inertia::render('tenancy/blocked')->toResponse($request);
        }

        Log::warning('tenant.blocked_request_rejected', ['tenant_id' => $tenant->id]);

        abort(423);
    }
}
