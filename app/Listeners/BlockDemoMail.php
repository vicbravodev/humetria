<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\DemoMail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * No mail of the demo company leaves: a message whose recipients are all at the demo domain is cancelled (false); one with
 * any real recipient goes. The log carries only how many recipients, never an address, subject or body, and it is debug:
 * a preparation blocks over a thousand messages, and PostHog only receives info and above (LOG_POSTHOG_LEVEL). Null, not
 * true, lets the other MessageSending listeners run.
 */
final class BlockDemoMail
{
    public function handle(MessageSending $event): ?bool
    {
        $recipients = [...$event->message->getTo(), ...$event->message->getCc(), ...$event->message->getBcc()];

        foreach ($recipients as $recipient) {
            if (! str_ends_with(strtolower($recipient->getAddress()), '@'.DemoMail::DOMAIN)) {
                return null;
            }
        }

        if ($recipients === []) {
            return null;
        }

        Log::debug('demo.mail_blocked', ['recipients' => count($recipients)]);

        return false;
    }
}
