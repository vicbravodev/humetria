<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Jobs\SendAccidentAlert;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reminds RH, once, of each accident notice (6.9) nobody answered three days after it arrived (rediseño v2, docs/02, «Aviso
 * de accidente»). Run daily by the scheduler. The reminder is marked in the same transaction that queues it, so two runs
 * never remind twice. Blocked companies get nothing (`SendAccidentAlert`).
 */
final readonly class RemindUnansweredAccidents
{
    public const int DAYS = 3;

    public function __construct(private Companies $companies) {}

    /**
     * @return int how many reminders were queued.
     */
    public function handle(): int
    {
        $sent = 0;

        foreach ($this->due()->withoutGlobalScopes()->distinct()->orderBy('tenant_id')->get(['tenant_id'])->map(fn (WorkerNotice $notice): string => $notice->tenant_id) as $tenantId) {
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

        Log::info('telework.accident_reminders_sent', ['sent' => $sent]);

        return $sent;
    }

    private function remind(string $tenantId): int
    {
        $sent = 0;

        foreach ($this->due()->orderBy('submitted_at')->orderBy('id')->get(['id'])->map(fn (WorkerNotice $notice): string => $notice->id) as $noticeId) {
            $sent += DB::transaction(function () use ($tenantId, $noticeId): int {
                $marked = WorkerNotice::query()->whereKey($noticeId)->whereNull('reminded_at')->update(['reminded_at' => now()]);

                if ($marked === 1) {
                    SendAccidentAlert::dispatch($tenantId, $noticeId, true);
                }

                return $marked;
            });
        }

        return $sent;
    }

    /** @return Builder<WorkerNotice> */
    private function due(): Builder
    {
        return WorkerNotice::query()->where('kind', NoticeKind::WorkRisk)->whereNull('reviewed_at')->whereNull('reminded_at')
            ->where('submitted_at', '<=', now()->subDays(self::DAYS));
    }
}
