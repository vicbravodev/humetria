<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Support\UsagePeriod;
use App\Modules\Messaging\Contracts\DeliveryOutcomes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * The monthly check of WhatsApp against what Meta bills (rule 11): per company, the messages charged, those that were never
 * delivered and the credit that suggests; and what the platform spent in the month, to compare with Meta's invoice. It writes
 * nothing: the operator applies the credit with the statement adjustment ("Crédito WhatsApp {mes}"). What Meta charges beyond what
 * was settled is Humetría's, and needs no action.
 */
final readonly class ReconcileWhatsApp
{
    public function __construct(private DeliveryOutcomes $outcomes) {}

    /**
     * @return array{companies: list<array{tenantId: string, charged: int, undelivered: int, creditCents: int}>, platformCostCents: int}
     */
    public function handle(CarbonImmutable $month): array
    {
        $events = UsageEvent::query()->toBase()
            ->where('meter', UsageMeter::WHATSAPP)
            ->whereDate('period_start', UsagePeriod::containing($month)->toDateString())
            ->get(['tenant_id', 'idempotency_key', 'unit_cost_micros', 'unit_price_micros']);

        $ids = [];

        foreach ($events as $event) {
            if (is_string($event->idempotency_key)) {
                $ids[] = $event->idempotency_key;
            }
        }

        $undelivered = array_flip($this->outcomes->undelivered($ids));
        $companies = [];
        $cost = 0;

        foreach ($events as $event) {
            $tenantId = is_string($event->tenant_id) ? $event->tenant_id : '';
            $companies[$tenantId] ??= ['tenantId' => $tenantId, 'charged' => 0, 'undelivered' => 0, 'creditMicros' => 0];
            $companies[$tenantId]['charged']++;
            $cost += is_numeric($event->unit_cost_micros) ? (int) $event->unit_cost_micros : 0;

            if (is_string($event->idempotency_key) && isset($undelivered[$event->idempotency_key])) {
                $companies[$tenantId]['undelivered']++;
                $companies[$tenantId]['creditMicros'] += is_numeric($event->unit_price_micros) ? (int) $event->unit_price_micros : 0;
            }
        }

        Log::info('billing.whatsapp_reconciled', ['companies' => count($companies), 'messages' => $events->count()]);

        return [
            'companies' => array_values(array_map(fn (array $row): array => ['tenantId' => $row['tenantId'], 'charged' => $row['charged'], 'undelivered' => $row['undelivered'], 'creditCents' => (int) round($row['creditMicros'] / 10_000)], $companies)),
            'platformCostCents' => (int) round($cost / 10_000),
        ];
    }
}
