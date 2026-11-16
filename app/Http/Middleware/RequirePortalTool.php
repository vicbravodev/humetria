<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The person's pages exist for any company, but each tool's pages exist only for a company that has it: without the module
 * they are a 404 before anything is validated. On a person's page (`me/{tenant:slug}/...`, after ResolveWorkerTenant) the
 * company of the request answers. On a page still opened by the portal link (`portal/{token}/...`, until every tool moves to
 * the person's session) the link's company answers, and a link that is not valid passes: each page answers it its own way.
 * Use as `RequirePortalTool::class.':seating'` (the module's key in the catalog).
 */
final readonly class RequirePortalTool
{
    public function __construct(private PortalAccess $access, private Companies $companies) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $token = $request->route('token');

        abort_if(is_string($token) ? $this->access->stopped($token, $module) : $this->companies->current()->stopped($module), 404);

        return $next($request);
    }
}
