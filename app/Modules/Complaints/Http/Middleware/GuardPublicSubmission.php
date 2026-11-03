<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Middleware;

use App\Modules\Complaints\Support\OpenedChannel;
use App\Modules\Complaints\Support\RandomCode;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * What stops a bot or another site before the form is read. A submission that comes from another site is refused. A hidden
 * field a person never fills, if filled, gets a receipt that looks real and is not kept, so the bot learns nothing. (The
 * session's forgery token is not here: the signed ticket of the form replaces it.)
 */
final class GuardPublicSubmission
{
    public const string HONEYPOT = 'website';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->header('Sec-Fetch-Site') === 'cross-site', 403);

        if ($request->filled(self::HONEYPOT)) {
            $opened = $request->attributes->get(OpenChannelForSubmission::ATTRIBUTE);
            Log::info('complaints.spam_blocked', ['channel_id' => $opened instanceof OpenedChannel ? $opened->channel->id : null, 'reason' => 'honeypot']);

            return new JsonResponse(['reference' => RandomCode::make(8), 'key' => RandomCode::make(20)], 201);
        }

        return $next($request);
    }
}
