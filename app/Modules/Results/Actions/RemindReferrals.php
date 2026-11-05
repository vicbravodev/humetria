<?php

declare(strict_types=1);

namespace App\Modules\Results\Actions;

use App\Modules\Results\Jobs\SendReferralReminder;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reminds whoever recorded a channeling, once, to ask how the person is on the day they asked for (30 days after it; board
 * V4-N035-Canalizacion). Run daily by the scheduler. The reminder is marked in the same transaction that queues it, so two
 * runs never remind twice. Blocked companies get nothing (`SendReferralReminder`). The log carries only how many.
 */
final readonly class RemindReferrals
{
    public function __construct(private Companies $companies) {}

    /**
     * @return int how many reminders were queued.
     */
    public function handle(): int
    {
        $sent = 0;

        foreach ($this->due()->withoutGlobalScopes()->distinct()->orderBy('tenant_id')->get(['tenant_id'])->map(fn (ScreeningReferral $referral): string => $referral->tenant_id) as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, &$sent): void {
                    $this->companies->enter($tenantId);
                    $sent += $this->remind($tenantId);
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' reminders from going out.
                report($failure);
            }
        }

        Log::info('results.referral_reminders_sent', ['sent' => $sent]);

        return $sent;
    }

    private function remind(string $tenantId): int
    {
        $sent = 0;

        foreach ($this->due()->orderBy('remind_on')->orderBy('id')->get(['id'])->map(fn (ScreeningReferral $referral): string => $referral->id) as $referralId) {
            $sent += DB::transaction(function () use ($tenantId, $referralId): int {
                $marked = ScreeningReferral::query()->whereKey($referralId)->whereNull('reminded_at')->update(['reminded_at' => now()]);

                if ($marked === 1) {
                    SendReferralReminder::dispatch($tenantId, $referralId);
                }

                return $marked;
            });
        }

        return $sent;
    }

    /** @return Builder<ScreeningReferral> */
    private function due(): Builder
    {
        return ScreeningReferral::query()->whereNull('reminded_at')->whereNotNull('remind_on')
            ->where('remind_on', '<=', now(config()->string('app.display_timezone'))->toDateString());
    }
}
