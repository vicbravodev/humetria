<?php

declare(strict_types=1);

namespace App\Modules\Operations\Support;

use App\Modules\Messaging\Contracts\PlatformDeliveries;
use App\Modules\Messaging\Data\CompanyRejections;
use App\Modules\Messaging\Data\TemplateTraffic;
use App\Modules\Tenancy\Contracts\CompanyRegistry;

/**
 * «Mensajería»: WhatsApp and mail of the whole platform, read through Messaging's contract. Counts and states only: never a
 * message, a phone or a person.
 */
final readonly class MessagingOverview
{
    /** The companies with most rejections the page names, at most; the ranges are whole days of Mexico, today included. */
    private const int COMPANIES = 5;

    public function __construct(private PlatformDeliveries $deliveries, private CompanyRegistry $companies) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $today = LocalCalendar::startOfToday();
        $tomorrow = $today->addDay();
        $month = LocalCalendar::startOfMonth();
        $connection = $this->deliveries->connection();
        $optOuts = $this->deliveries->optOuts();

        return [
            'month' => TrafficRows::of($this->deliveries->byChannel($month, LocalCalendar::startOfNextMonth())),
            'today' => TrafficRows::of($this->deliveries->byChannel($today, $tomorrow)),
            'templates' => array_map(
                fn (TemplateTraffic $template): array => ['key' => $template->key, 'category' => $template->category, 'pendingInCode' => $template->pendingInCode, 'total' => $template->total, 'delivered' => $template->delivered, 'rejected' => $template->rejected],
                $this->deliveries->byTemplate($today->subDays(29), $tomorrow),
            ),
            'companies' => $this->companies($this->deliveries->mostRejected($today->subDays(6), $tomorrow, self::COMPANIES)),
            'connection' => [
                'enabled' => $connection->enabled,
                'paused' => $connection->pausedByOperator || $connection->pauseReason !== null,
                'pauseReason' => $connection->pauseReason,
                'pausedSince' => $connection->pausedSince?->utc()->toIso8601String(),
                'lastEventAt' => $this->deliveries->lastWebhookAt('meta')?->utc()->toIso8601String(),
            ],
            'optOuts' => ['everyCompany' => $optOuts->everyCompany, 'oneCompany' => $optOuts->oneCompany, 'lastThirtyDays' => $optOuts->lastThirtyDays],
        ];
    }

    /**
     * @param  list<CompanyRejections>  $rejections
     * @return list<array{id: string, name: string, rejected: int}>
     */
    private function companies(array $rejections): array
    {
        $briefs = $this->companies->many(array_map(fn (CompanyRejections $row): string => $row->tenantId, $rejections));
        $named = [];

        foreach ($rejections as $row) {
            $brief = $briefs[$row->tenantId] ?? null;

            if ($brief !== null) {
                $named[] = ['id' => $brief->id, 'name' => $brief->tradeName, 'rejected' => $row->rejected];
            }
        }

        return $named;
    }
}
