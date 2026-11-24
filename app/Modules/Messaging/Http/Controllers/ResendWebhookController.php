<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Modules\Messaging\Actions\HandleResendEvents;
use App\Modules\Messaging\Support\ResendSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Mail events of Resend. The signature over the raw body is the credential and is checked before anything is read: without a
 * valid one, 403. A body that is not a JSON object is 400, logged without it.
 */
final class ResendWebhookController
{
    public function receive(Request $request, ResendSignature $signature, HandleResendEvents $events): Response
    {
        $body = $request->getContent();
        $id = $request->header('svix-id');
        $timestamp = $request->header('svix-timestamp');
        $header = $request->header('svix-signature');

        abort_unless(is_string($id) && is_string($timestamp) && is_string($header)
            && $signature->valid($id, $timestamp, $header, $body, now()->getTimestamp()), 403);

        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = null;
        }

        if (! is_array($payload)) {
            Log::warning('mail.webhook_malformed');

            return response('', 400);
        }

        $events->handle($id, $payload);

        return response('', 200);
    }
}
