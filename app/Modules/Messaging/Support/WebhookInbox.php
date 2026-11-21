<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Models\WebhookEvent;
use Illuminate\Support\Str;

/**
 * Remembers each provider event by its id, so one a provider sends again is processed once.
 */
final class WebhookInbox
{
    /**
     * True the first time an event of a provider is seen (and records it), false after. `$orphan` keeps what is needed to
     * reprocess a Meta status whose message does not exist yet: never the phone.
     *
     * @param  array<string, mixed>|null  $orphan
     */
    public function firstTime(string $provider, string $eventId, ?array $orphan = null): bool
    {
        return WebhookEvent::query()->insertOrIgnore([
            'id' => Str::uuid()->toString(),
            'provider' => $provider,
            'event_id' => $eventId,
            'received_at' => now(),
            'orphan' => $orphan === null ? null : json_encode($orphan, JSON_THROW_ON_ERROR),
        ]) === 1;
    }
}
