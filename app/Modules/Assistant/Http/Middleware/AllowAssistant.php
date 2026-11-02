<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Middleware;

use App\Http\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who may talk to Humi in a company: the people with the `use-assistant` permission, and never a superadmin acting as
 * the owner (the conversations are the owner's, not theirs: it answers 404, as if Humi were not there). Runs after
 * ResolveTenant, which has set the company as the team of the roles.
 */
final readonly class AllowAssistant
{
    public function __construct(private Impersonation $impersonation) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->impersonation->isActive(), 404);
        abort_unless($request->user()?->can('use-assistant') === true, 403);

        return $next($request);
    }
}
