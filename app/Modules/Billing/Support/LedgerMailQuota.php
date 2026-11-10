<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Billing\Exceptions\MailQuotaExceeded;
use App\Modules\Billing\Listeners\CountSentMail;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class LedgerMailQuota implements MailQuota
{
    public function __construct(private CurrentTenant $tenant, private UsageQuota $quota) {}

    public function reserve(int $count): void
    {
        $tenantId = $this->tenant->id();

        if ($count < 1 || ! Subscription::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $now = CarbonImmutable::now();
        $period = UsagePeriod::containing($now);
        $limit = $this->quota->for($tenantId, CountSentMail::METER, $period);

        UsageCounter::query()->insertOrIgnore([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'meter' => CountSentMail::METER,
            'period_start' => $period->toDateString(),
            'used' => 0,
            'reserved' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $counter = UsageCounter::query()->where('tenant_id', $tenantId)->where('meter', CountSentMail::METER)->whereDate('period_start', $period->toDateString());
        $taken = (clone $counter)->whereRaw('"used" + "reserved" + ? <= ?', [$count, $limit])->increment('reserved', $count, ['quota' => $limit]);

        if ($taken === 0) {
            $current = (clone $counter)->first();
            $left = max(0, $limit - ($current->used ?? 0) - ($current->reserved ?? 0));
            Log::info('usage.mail_refused', ['tenant_id' => $tenantId, 'count' => $count, 'quota' => $limit]);

            throw new MailQuotaExceeded('Tu plan incluye '.number_format($limit).' correos de campaña al mes y '.($left === 0 ? 'ya no te queda ninguno' : "te quedan {$left}").'; esto manda '.number_format($count).' y faltan '.number_format($count - $left).'. No se envió nada. Pide una ampliación a Humetria.');
        }
    }
}
