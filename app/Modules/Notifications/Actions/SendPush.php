<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\PushKind;
use App\Modules\Notifications\Exceptions\PushRateLimited;
use App\Modules\Notifications\Exceptions\PushUnavailable;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Notifications\Support\VapidConfig;
use App\Modules\Notifications\Support\WebPushFactory;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Throwable;

/**
 * One notice to one phone of the current company: a POST to its push service signed with the VAPID keys, with a body encrypted to
 * the keys of that phone (`{"kind": …}`, only the kind; the push service and anyone on the way see ciphertext). A phone
 * that is gone (404, 410) is forgotten; a service that says to wait (429) or fails (5xx, no answer) makes the job try again; any other
 * refusal (a 403 is usually a wrong VAPID key) is logged and left alone, because deleting there would empty everyone for one
 * misconfiguration; a phone whose own keys cannot encrypt is forgotten (nothing to retry). The logs say the subscription's id and the status, never the address nor a key.
 */
final readonly class SendPush
{
    /** Seconds the push service keeps the notice for a phone that is off: past an hour the app shows the state when opened. */
    public const int TTL = 3600;

    public function __construct(private WebPushFactory $factory) {}

    /**
     * @throws PushRateLimited when the push service asks to wait
     * @throws PushUnavailable when it fails or does not answer
     */
    public function handle(string $subscriptionId, PushKind $kind): void
    {
        $subscription = PushSubscription::query()->find($subscriptionId);
        $vapid = VapidConfig::fromConfig();

        if ($subscription === null || $vapid === null) {
            return;
        }

        try {
            $push = $this->factory->make($vapid);
        } catch (Throwable) {
            // Keys that are not keys: nothing to retry until someone fixes the setting.
            Log::warning('push.failed', ['subscription_id' => $subscription->id, 'reason' => 'invalid_keys']);

            return;
        }

        try {
            $report = $push->sendOneNotification(
                Subscription::create(['endpoint' => $subscription->endpoint, 'publicKey' => $subscription->p256dh, 'authToken' => $subscription->auth, 'contentEncoding' => 'aes128gcm']),
                json_encode(['kind' => $kind->value], JSON_THROW_ON_ERROR),
            );
        } catch (Throwable) {
            // Keys of the phone that are not a P-256 point and a 16-byte secret (the form checks only their size): the notice cannot be
            // encrypted now or ever, so the phone is forgotten and the person's app subscribes again when it is opened.
            $subscription->delete();
            Log::warning('push.failed', ['subscription_id' => $subscription->id, 'reason' => 'subscription_keys']);

            return;
        }

        $this->settle($subscription, $report);
    }

    private function settle(PushSubscription $subscription, MessageSentReport $report): void
    {
        $response = $report->getResponse();
        $status = $response?->getStatusCode();

        if ($report->isSuccess()) {
            $subscription->update(['last_success_at' => now()]);
            Log::info('push.sent', ['subscription_id' => $subscription->id]);
        } elseif ($report->isSubscriptionExpired()) {
            $subscription->delete();
            Log::info('push.expired', ['subscription_id' => $subscription->id, 'status' => $status]);
        } elseif ($response !== null && $status === 429) {
            throw new PushRateLimited(self::retryAfter($response->getHeaderLine('Retry-After')));
        } elseif ($status === null || $status >= 500) {
            throw new PushUnavailable;
        } else {
            Log::warning('push.failed', ['subscription_id' => $subscription->id, 'status' => $status]);
        }
    }

    /** The `Retry-After` of the service, in seconds or as an HTTP date: 60 when it does not say, and always between 1 and 3600. */
    private static function retryAfter(string $header): int
    {
        $header = trim($header);

        if (preg_match('/^-?\d+$/', $header) === 1) {
            $seconds = (int) $header;
        } else {
            $at = $header === '' ? false : strtotime($header);
            $seconds = $at === false ? 60 : $at - now()->getTimestamp();
        }

        return max(1, min(3600, $seconds));
    }
}
