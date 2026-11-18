<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Models\WebhookEvent;
use App\Modules\Messaging\Support\MetaStatus;
use Illuminate\Support\Facades\Log;

/**
 * A status can reach us before the job has kept the id of its message. It waits as an orphan and is applied when the message
 * exists; after `messaging.orphan_minutes` the ones still alone are dropped (rule 8).
 */
final class ReplayOrphanEvents
{
    public function __construct(private readonly HandleMetaEvents $meta) {}

    /** @return int how many it applied */
    public function handle(): int
    {
        $applied = 0;
        $discarded = 0;
        $limit = now()->subMinutes(config()->integer('messaging.orphan_minutes'));

        $orphans = WebhookEvent::query()->where('provider', 'meta')->whereNotNull('orphan')->whereNull('processed_at')->orderBy('received_at')->get();

        foreach ($orphans as $orphan) {
            $status = MetaStatus::fromOrphan($orphan->orphan ?? []);
            $delivery = $status === null ? null : $this->meta->find($status);

            if ($status !== null && $delivery !== null) {
                $this->meta->apply($delivery, $status);
                $orphan->update(['processed_at' => now()]);
                $applied++;
            } elseif ($orphan->received_at->lessThan($limit)) {
                $orphan->update(['processed_at' => now()]);
                $discarded++;
            }
        }

        if ($discarded > 0) {
            Log::info('whatsapp.orphans_discarded', ['discarded' => $discarded]);
        }

        return $applied;
    }
}
