<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The personal link opens for any company, but each tool's pages exist only for a company that has it: without the module
 * they are a 404 before anything is validated. A link that is not valid passes, each page already answers it its own way.
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

        if (is_string($token) && $this->access->open($token) !== null) {
            abort_if($this->companies->current()->stopped($module), 404);
        }

        return $next($request);
    }
}
