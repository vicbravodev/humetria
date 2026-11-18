<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Contracts\WhatsAppQuota;
use App\Modules\Billing\Data\MessagePrice;
use App\Modules\Billing\Exceptions\WhatsAppQuotaExceeded;
use App\Modules\Tenancy\Contracts\CompanyChannels;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Support\SupportContact;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final readonly class LedgerWhatsAppQuota implements WhatsAppQuota
{
    public function __construct(
        private CurrentTenant $tenant,
        private UsageQuota $quota,
        private CounterHold $hold,
        private CompanyChannels $channels,
        private MessagingCost $costs,
    ) {}

    public function price(): MessagePrice
    {
        $cost = $this->costs->at(CarbonImmutable::now());

        return new MessagePrice($cost->priceMicros, $cost->currency);
    }

    public function reserve(int $count): void
    {
        if ($count < 1) {
            return;
        }

        $tenantId = $this->tenant->id();

        // Unlike mail, a company with no plan has no WhatsApp: its quota is zero.
        $limit = min(
            $this->quota->for($tenantId, UsageMeter::WHATSAPP, UsagePeriod::containing(CarbonImmutable::now())),
            $this->channels->monthlyCap($tenantId, 'whatsapp') ?? PHP_INT_MAX,
        );
        $left = $this->hold->take($tenantId, UsageMeter::WHATSAPP, $count, $limit);

        if ($left !== null) {
            Log::info('usage.whatsapp_refused', ['tenant_id' => $tenantId, 'count' => $count, 'quota' => $limit]);

            throw new WhatsAppQuotaExceeded('Tu cupo de WhatsApp del mes es de '.number_format($limit)." mensajes y te quedan {$left}; esto manda ".number_format($count).'. No se envió nada.'.SupportContact::sentence());
        }
    }
}
