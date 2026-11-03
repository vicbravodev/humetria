<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Middleware;

use App\Http\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a person earns is not for the platform's staff: while a superadmin acts as the owner, every Compensación route
 * answers 404, as if it were not there. Runs inside the company group, after ResolveTenant.
 */
final class BlockWhileImpersonating
{
    public function __construct(private readonly Impersonation $impersonation) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->impersonation->isActive(), 404);

        return $next($request);
    }
}
