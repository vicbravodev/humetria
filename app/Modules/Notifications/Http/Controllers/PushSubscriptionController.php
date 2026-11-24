<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Actions\ForgetSubscriptions;
use App\Modules\Notifications\Actions\StoreSubscription;
use App\Modules\Notifications\Http\Requests\ForgetPushSubscriptionRequest;
use App\Modules\Notifications\Http\Requests\StorePushSubscriptionRequest;
use App\Modules\Notifications\Support\VapidConfig;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The person's phone allows the notices or gives them up, with their session as the only credential (RequireWorkerSession): whose the
 * subscription is always comes from the session, never from the body.
 */
final readonly class PushSubscriptionController
{
    public function __construct(private WorkerSession $session) {}

    /** The key the phone subscribes with; null when the push is not set up, so the app does not offer the notices. */
    public function show(): JsonResponse
    {
        return response()->json(['publicKey' => VapidConfig::fromConfig()?->publicKey()])->header('Cache-Control', 'no-store, private');
    }

    public function store(StorePushSubscriptionRequest $request, StoreSubscription $store): Response
    {
        $store->handle($this->person(), $request->string('endpoint')->toString(), $request->string('keys.p256dh')->toString(), $request->string('keys.auth')->toString());

        return response()->noContent();
    }

    /** 204 whether or not it existed: it only touches the rows of the person of the session, so it is no oracle. */
    public function destroy(ForgetPushSubscriptionRequest $request, ForgetSubscriptions $forget): Response
    {
        $forget->handle($this->person(), $request->string('endpoint')->toString(), 'unsubscribed');

        return response()->noContent();
    }

    private function person(): string
    {
        return $this->session->current()->employeeId ?? abort(401);
    }
}
