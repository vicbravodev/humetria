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
 * Finds the channel of a submission's code and works inside its company, before anything of the form is read: the rules of
 * the form need the company. Answers in JSON: a code that serves nobody is a bare 404, a paused channel a 503.
 */
final readonly class OpenChannelForSubmission
{
    public const string ATTRIBUTE = 'opened_channel';

    public function __construct(private OpenChannel $open) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->route('code');

        try {
            $opened = $this->open->handle(is_string($code) ? $code : '');
        } catch (ChannelUnavailable) {
            return new JsonResponse(['message' => 'Este enlace no está disponible.'], 404);
        }

        if (! $opened->channel->isOpen()) {
            return new JsonResponse(['message' => 'El canal no está disponible por ahora.'], 503);
        }

        $request->attributes->set(self::ATTRIBUTE, $opened);

        return $next($request);
    }
}
