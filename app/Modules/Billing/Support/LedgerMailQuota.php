<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Exceptions\MailQuotaExceeded;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Support\SupportContact;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final readonly class LedgerMailQuota implements MailQuota
{
    public function __construct(private CurrentTenant $tenant, private UsageQuota $quota, private CounterHold $hold) {}

    public function tryReserve(int $count): bool
    {
        try {
            $this->reserve($count);

            return true;
        } catch (MailQuotaExceeded) {
            return false;
        }
    }

    public function reserve(int $count): void
    {
        $tenantId = $this->tenant->id();

        if ($count < 1 || ! Subscription::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $limit = $this->quota->for($tenantId, UsageMeter::EMAIL_CAMPAIGN, UsagePeriod::containing(CarbonImmutable::now()));
        $left = $this->hold->take($tenantId, UsageMeter::EMAIL_CAMPAIGN, $count, $limit);

        if ($left !== null) {
            Log::info('usage.mail_refused', ['tenant_id' => $tenantId, 'count' => $count, 'quota' => $limit]);

            throw new MailQuotaExceeded('Tu plan incluye '.number_format($limit).' correos de campaña al mes y '.($left === 0 ? 'ya no te queda ninguno' : "te quedan {$left}").'; esto manda '.number_format($count).' y faltan '.number_format($count - $left).'. No se envió nada. Pide una ampliación a Humetría.'.SupportContact::sentence());
        }
    }
}
