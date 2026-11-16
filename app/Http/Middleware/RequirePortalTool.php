<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Tenancy\Contracts\Companies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The person's pages exist for any company, but each tool's pages exist only for a company that has it: without the module
 * they are a 404 before anything is validated, and before the person is asked to enter. It runs after ResolveWorkerTenant
 * (`me/{tenant:slug}/...`), so the company of the request answers. Use as `RequirePortalTool::class.':seating'` (the
 * module's key in the catalog).
 */
final readonly class RequirePortalTool
{
    public function __construct(private Companies $companies) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_if($this->companies->current()->stopped($module), 404);

        return $next($request);
    }
}
