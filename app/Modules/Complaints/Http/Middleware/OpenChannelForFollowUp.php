<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Middleware;

use App\Modules\Complaints\Actions\OpenChannel;
use App\Modules\Complaints\Exceptions\ChannelUnavailable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Like OpenChannelForSubmission, but a paused channel still lets people follow up the complaints they already made: pausing
 * stops new ones, not the conversation with those who wrote before.
 */
final readonly class OpenChannelForFollowUp
{
    public function __construct(private OpenChannel $open) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->route('code');

        try {
            $request->attributes->set(OpenChannelForSubmission::ATTRIBUTE, $this->open->handle(is_string($code) ? $code : ''));
        } catch (ChannelUnavailable) {
            return new JsonResponse(['message' => 'Este enlace no está disponible.'], 404);
        }

        return $next($request);
    }
}
