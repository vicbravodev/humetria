<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\DeliveryEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one place that writes a status. The event always goes into the history (nothing is overwritten or dropped); the current
 * status of the delivery changes only if it is not terminal and the new one is terminal, ranks higher, or the current one is
 * `created` (rule 8). A delayed after a delivered, then, stays in the history only.
 */
final class RecordDeliveryEvent
{
    public function handle(Delivery $delivery, DeliveryStatus $status, CarbonImmutable $occurredAt, ?FailureCause $cause = null): void
    {
        $changed = DB::transaction(function () use ($delivery, $status, $occurredAt, $cause): bool {
            // Two webhooks for one message may arrive together: the second reads what the first left.
            $current = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);

            DeliveryEvent::query()->create([
                'tenant_id' => $current->tenant_id,
                'delivery_id' => $current->id,
                'status' => $status,
                'failure_cause' => $cause,
                'occurred_at' => $occurredAt,
            ]);

            if (! $this->moves($current->status, $status)) {
                return false;
            }

            $current->update(['status' => $status]);

            return true;
        });

        $delivery->refresh();

        if ($changed) {
            Log::info('messaging.delivery_status_changed', [
                'delivery_id' => $delivery->id,
                'channel' => $delivery->channel,
                'status' => $status->value,
                'cause' => $cause?->value,
            ]);
        }
    }

    private function moves(DeliveryStatus $from, DeliveryStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return $to->isTerminal() || $to->rank() > $from->rank() || $from === DeliveryStatus::Created;
    }
}
