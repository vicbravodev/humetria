<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

/**
 * The Svix signature Resend puts on its webhooks: HMAC-SHA256 of `{id}.{timestamp}.{raw body}` with the base64 key that follows
 * `whsec_`, as `v1,<base64>` (several, space-separated, while Resend rotates). No package: `hash_hmac` and `hash_equals`.
 * The timestamp bounds replays; the repeat of one event is also stopped by its id (WebhookInbox).
 */
final class ResendSignature
{
    public function valid(string $id, string $timestamp, string $signatureHeader, string $rawBody, int $now): bool
    {
        if (preg_match('/^\d+$/', $timestamp) !== 1
            || abs($now - (int) $timestamp) > config()->integer('services.resend.webhook_tolerance_seconds')) {
            return false;
        }

        $signatures = [];

        foreach (explode(' ', $signatureHeader) as $entry) {
            if (str_starts_with($entry, 'v1,')) {
                $signatures[] = substr($entry, 3);
            }
        }

        foreach (config()->array('services.resend.webhook_secrets') as $secret) {
            $key = is_string($secret) ? base64_decode(substr($secret, 6), true) : false;

            if ($key === false) {
                continue;
            }

            $expected = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$rawBody}", $key, true));

            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) {
                    return true;
                }
            }
        }

        return false;
    }
}
