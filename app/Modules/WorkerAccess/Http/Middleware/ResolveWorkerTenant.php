<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Middleware;

use App\Modules\Tenancy\Contracts\Companies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The company of a person's page (me/{tenant:slug}/...), which carries no account: works inside the company of the slug, as
 * ResolveTenant does for the panel. An unknown company and a blocked one get the same 404, so the address tells nothing.
 * No tool is required: every company that is not blocked has its people's pages.
 */
final readonly class ResolveWorkerTenant
{
    public function __construct(private Companies $companies) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');

        abort_if(! is_string($slug) || $this->companies->enterBySlug($slug) === null, 404);

        return $next($request);
    }
}
