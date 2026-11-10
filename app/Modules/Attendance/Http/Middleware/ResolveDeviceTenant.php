<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Middleware;

use App\Modules\Tenancy\Contracts\Companies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The company of a time-clock device's route (a/{tenant:slug}/...), which carries no account: works inside the company
 * of the slug, as ResolveTenant does for the panel (its data, logs and queued jobs). An unknown company and one that may
 * not be used (suspended, as BlockSuspendedTenant reads it) get the same 404, so the URL reveals nothing; a suspended
 * company is not entered first, so that 404 is not logged under its id.
 */
final readonly class ResolveDeviceTenant
{
    public function __construct(private Companies $companies) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');
        $company = is_string($slug) ? $this->companies->enterBySlug($slug) : null;

        // A company that does not have the registro de jornada gets the same 404: its devices do not work.
        abort_if($company === null || ! $company->allows('attendance'), 404);

        return $next($request);
    }
}
