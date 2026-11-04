<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Complaints\Actions\SubmitComplaint;
use App\Modules\Complaints\Exceptions\ChannelOverCap;
use App\Modules\Complaints\Http\Middleware\OpenChannelForSubmission;
use App\Modules\Complaints\Http\Requests\SubmitComplaintRequest;
use App\Modules\Complaints\Support\OpenedChannel;
use Illuminate\Http\JsonResponse;

/**
 * Receives a complaint from the public form, as JSON: no redirection, no flash and no session, which would keep a trace.
 */
final class PublicComplaintController extends Controller
{
    public function store(SubmitComplaintRequest $request, SubmitComplaint $submit): JsonResponse
    {
        $opened = $request->attributes->get(OpenChannelForSubmission::ATTRIBUTE);
        abort_unless($opened instanceof OpenedChannel, 404);

        try {
            $receipt = $submit->handle($opened->channel, $request->complaint());
        } catch (ChannelOverCap) {
            return new JsonResponse(['message' => 'El canal recibió muchas quejas hoy. Inténtelo de nuevo mañana.'], 429);
        }

        return new JsonResponse(['reference' => $receipt->reference, 'key' => $receipt->key], 201);
    }
}
