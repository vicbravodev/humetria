<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Modules\Messaging\Actions\HandleMetaEvents;
use App\Modules\Messaging\Support\MetaSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * The webhook of Meta. It has no session: the credential is the verify token (GET) or the signature of the raw body (POST).
 */
final class MetaWebhookController
{
    public function verify(Request $request): Response
    {
        $token = config('whatsapp.verify_token');
        $given = $request->query('hub_verify_token');

        if ($request->query('hub_mode') !== 'subscribe' || ! is_string($token) || $token === '' || ! is_string($given) || ! hash_equals($token, $given)) {
            return response('', 403);
        }

        $challenge = $request->query('hub_challenge');

        return response(is_string($challenge) ? $challenge : '', 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request, MetaSignature $signature, HandleMetaEvents $events): Response
    {
        $raw = $request->getContent();

        // Nothing is read, let alone processed, before the signature is checked.
        if (! $signature->valid($raw, $request->header('X-Hub-Signature-256'))) {
            return response('', 403);
        }

        try {
            $payload = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = null;
        }

        if (! is_array($payload)) {
            Log::warning('whatsapp.webhook_malformed', ['bytes' => strlen($raw)]);

            return response('', 400);
        }

        $events->handle($payload);

        return response('', 200);
    }
}
