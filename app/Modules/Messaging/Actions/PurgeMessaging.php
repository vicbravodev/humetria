<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The retention (rule 14): the messages and their history older than `messaging.retention_months`, and the provider events older
 * than `messaging.webhook_event_days` (the window against repeated events). The history is insert-only for everyone: only a
 * transaction that says `messaging.purge = on` may delete it. Opt-outs are never deleted: they are what stops us writing again.
 */
final class PurgeMessaging
{
    /** @return array{deliveries: int, webhook_events: int} */
    public function handle(): array
    {
        $counts = DB::transaction(function (): array {
            DB::statement("SET LOCAL messaging.purge = 'on'");

            $deliveries = Delivery::query()->toBase()->where('created_at', '<', now()->subMonths(config()->integer('messaging.retention_months')))->delete();
            $events = WebhookEvent::query()->toBase()->where('received_at', '<', now()->subDays(config()->integer('messaging.webhook_event_days')))->delete();

            // Back to the rule at once, so nothing else this transaction does can delete history.
            DB::statement("SET LOCAL messaging.purge = 'off'");

            return ['deliveries' => $deliveries, 'webhook_events' => $events];
        });

        Log::info('messaging.purged', $counts);

        return $counts;
    }
}
