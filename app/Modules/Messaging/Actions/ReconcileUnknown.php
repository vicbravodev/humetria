<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Events\WhatsAppMessageAccepted;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\RecordDeliveryEvent;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;

/**
 * Meta cannot be asked for the state of a message: an `unknown` is settled when a status webhook brings its id (commit 9a). After
 * `messaging.unknown_hours` with none, it ends as failed with cause `unresolved`, its hold is settled as used (the cost is
 * assumed) and the mail does not take over (rule 8).
 */
final class ReconcileUnknown
{
    public function __construct(private readonly Companies $companies, private readonly RecordDeliveryEvent $record) {}

    /** @return int how many it settled */
    public function handle(): int
    {
        $stale = Delivery::query()
            ->where('channel', 'whatsapp')
            ->where('status', DeliveryStatus::Unknown)
            ->where('updated_at', '<=', now()->subHours(config()->integer('messaging.unknown_hours')))
            ->get();

        foreach ($stale as $delivery) {
            $this->companies->enter($delivery->tenant_id);
            $this->record->handle($delivery, DeliveryStatus::Failed, CarbonImmutable::now(), FailureCause::Unresolved);
            // Settled as used, so it counts as accepted for the daily and platform caps, which read `accepted_at`.
            $delivery->update(['accepted_at' => CarbonImmutable::now()]);
            event(new WhatsAppMessageAccepted($delivery->id, $delivery->tenant_id));
        }

        return $stale->count();
    }
}
