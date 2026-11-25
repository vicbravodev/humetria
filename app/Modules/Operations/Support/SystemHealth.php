<?php

declare(strict_types=1);

namespace App\Modules\Operations\Support;

use App\Modules\Messaging\Contracts\PlatformDeliveries;
use App\Modules\Messaging\Data\ChannelTraffic;
use App\Modules\Operations\Contracts\SchedulerHeartbeat;
use Carbon\CarbonImmutable;

/**
 * «Salud del sistema»: what runs behind the platform, from what the app can read today. Counts and states only: never a person,
 * an answer, a message or a job's payload.
 */
final readonly class SystemHealth
{
    /** The scheduler beats every 5 minutes; the alert of docs/operations/observability.md fires after 15 without a beat. */
    private const int LATE_AFTER_MINUTES = 15;

    public function __construct(
        private QueueWorkload $queues,
        private FailedJobs $failedJobs,
        private SchedulerHeartbeat $heartbeat,
        private PlatformDeliveries $deliveries,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $now = CarbonImmutable::now();
        $startOfToday = $now->setTimezone(config()->string('app.display_timezone'))->startOfDay()->utc();
        $beat = $this->heartbeat->lastBeatAt();
        $connection = $this->deliveries->connection();

        return [
            'queue' => $this->queues->handle(),
            'failedJobs' => $this->failedJobs->handle($startOfToday),
            'heartbeat' => [
                'lastAt' => $beat?->utc()->toIso8601String(),
                'late' => $beat === null || $beat->diffInMinutes($now) > self::LATE_AFTER_MINUTES,
            ],
            'webhooks' => [
                'meta' => $this->deliveries->lastWebhookAt('meta')?->utc()->toIso8601String(),
                'resend' => $this->deliveries->lastWebhookAt('resend')?->utc()->toIso8601String(),
            ],
            'whatsApp' => [
                'enabled' => $connection->enabled,
                'paused' => $connection->pausedByOperator || $connection->pauseReason !== null,
                'pauseReason' => $connection->pauseReason,
            ],
            'today' => array_map(
                fn (ChannelTraffic $traffic): array => ['channel' => $traffic->channel, 'total' => $traffic->total, 'delivered' => $traffic->delivered, 'read' => $traffic->read, 'rejected' => $traffic->rejected],
                $this->deliveries->byChannel($startOfToday, $startOfToday->addDay()),
            ),
        ];
    }
}
