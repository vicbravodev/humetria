<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\DeliveryEvent;
use App\Modules\Messaging\Models\Pause;
use App\Modules\Messaging\Support\WhatsAppSwitch;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;

/**
 * The automatic pause (rule 15). One company's blocks and spam reports lower the quality of the number for all, so when the share
 * of the WhatsApp messages sent in the window that came back blocked or as spam reaches the threshold, the whole function pauses
 * until an operator resumes it (`messaging:resume`).
 */
final class WatchFailures
{
    public function __construct(private readonly WhatsAppSwitch $switch) {}

    /** @return bool whether it opened a pause now */
    public function handle(): bool
    {
        if (Pause::query()->whereNull('ended_at')->exists()) {
            return false;
        }

        $since = now()->subMinutes(config()->integer('whatsapp.auto_pause.window_minutes'));
        $sent = $this->events($since)->where('messaging_delivery_events.status', DeliveryStatus::Sent->value)->count();

        if ($sent < config()->integer('whatsapp.auto_pause.minimum_messages')) {
            return false;
        }

        $blocked = $this->events($since)
            ->where('messaging_delivery_events.status', DeliveryStatus::Failed->value)
            ->whereIn('messaging_delivery_events.failure_cause', [FailureCause::SpamLimited->value, FailureCause::EcosystemBlocked->value])
            ->count();

        // Whole numbers, so 4.9 % does not round into 5.
        if ($blocked * 100 < config()->integer('whatsapp.auto_pause.failure_rate_percent') * $sent) {
            return false;
        }

        Log::warning('whatsapp.failure_rate_exceeded', ['sent' => $sent, 'blocked' => $blocked]);
        $this->switch->pause('failure_rate');

        return true;
    }

    /** The WhatsApp history of the window, across companies (the number is shared). */
    private function events(DateTimeInterface $since): Builder
    {
        return DeliveryEvent::query()->toBase()
            ->join('messaging_deliveries', 'messaging_deliveries.id', '=', 'messaging_delivery_events.delivery_id')
            ->where('messaging_deliveries.channel', 'whatsapp')
            ->where('messaging_delivery_events.occurred_at', '>=', $since);
    }
}
