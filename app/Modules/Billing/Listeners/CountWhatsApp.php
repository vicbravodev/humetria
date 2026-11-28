<?php

declare(strict_types=1);

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Actions\RecordUsage;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Support\MessagingCost;
use App\Modules\Billing\Support\UsagePeriod;
use App\Modules\Messaging\Events\WhatsAppMessageAccepted;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Carbon\CarbonImmutable;

/**
 * Counts WhatsApp as the design says (D4): a message is charged when Meta accepts it, from the hold its launch made; a message that
 * fails gives its hold back. The line keeps Meta's unit cost and the price charged.
 */
final readonly class CountWhatsApp
{
    public function __construct(private RecordUsage $record, private MessagingCost $costs, private DemoCompanies $demos) {}

    public function accepted(WhatsAppMessageAccepted $event): void
    {
        // The demo is never billed: its messages are not counted (nothing was held for them either).
        if ($this->demos->isDemo($event->tenantId)) {
            return;
        }

        $this->record->handle($event->tenantId, UsageMeter::WHATSAPP, 1, $event->deliveryId, fromHold: true, cost: $this->costs->at(CarbonImmutable::now()));
    }

    public function failed(WhatsAppMessageFailed $event): void
    {
        // Already settled (a failure that arrives by webhook after the acceptance): the reconciliation of the month decides.
        if (UsageEvent::query()->where('tenant_id', $event->tenantId)->where('idempotency_key', $event->deliveryId)->exists()) {
            return;
        }

        UsageCounter::query()
            ->where('tenant_id', $event->tenantId)
            ->where('meter', UsageMeter::WHATSAPP)
            ->whereDate('period_start', UsagePeriod::containing($event->createdAt)->toDateString())
            ->where('reserved', '>=', 1)
            ->decrement('reserved');
    }
}
