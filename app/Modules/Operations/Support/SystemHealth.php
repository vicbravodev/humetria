<?php

declare(strict_types=1);

namespace App\Modules\Operations\Support;

use App\Modules\Messaging\Contracts\PlatformDeliveries;
use App\Modules\Operations\Contracts\SchedulerHeartbeat;
use Carbon\CarbonImmutable;

/**
 * «Salud del sistema»: what runs behind the platform, from what the app can read today. Counts and states only: never a person,
 * an answer, a message or a job's payload.
 */
final readonly class SystemHealth
{
    /** The scheduler beats every 5 minutes; the alert of docs/operations/observability.md fires after 15 without a beat. */
    private const int LATE_AFTER_MINUTES = 15; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

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
        $startOfToday = LocalCalendar::startOfToday();
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
            'today' => TrafficRows::of($this->deliveries->byChannel($startOfToday, $startOfToday->addDay())),
            'hours' => $this->hours($now),
            // The share of rejections that pauses WhatsApp on its own: the bars of an hour above it are drawn as a warning.
            'rejectionLimit' => config()->integer('whatsapp.auto_pause.failure_rate_percent'),
        ];
    }

    /**
     * The mail and WhatsApp of each of the last 24 hours, the current one last.
     *
     * @return list<array{startsAt: string, mail: array{total: int, rejected: int}, whatsapp: array{total: int, rejected: int}}>
     */
    private function hours(CarbonImmutable $now): array
    {
        $hours = [];

        // ponytail: one query per hour through Messaging's contract (24 per visit); a grouped-by-hour method on
        // PlatformDeliveries if this page is ever visited often.
        for ($back = 23; $back >= 0; $back--) {
            $start = $now->utc()->startOfHour()->subHours($back);
            $hour = ['startsAt' => $start->toIso8601String(), 'mail' => ['total' => 0, 'rejected' => 0], 'whatsapp' => ['total' => 0, 'rejected' => 0]];

            foreach ($this->deliveries->byChannel($start, $start->addHour()) as $traffic) {
                $hour[$traffic->channel === 'mail' ? 'mail' : 'whatsapp'] = ['total' => $traffic->total, 'rejected' => $traffic->rejected];
            }

            $hours[] = $hour;
        }

        return $hours;
    }
}
