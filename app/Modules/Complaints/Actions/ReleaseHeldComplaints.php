<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Frees, on a later day, the complaints held past a channel's daily cap: each is assigned by the same rules as at receipt, so
 * its handlers find it in their inbox (and in their digest) like any other that arrives. Today's held ones wait, so a released
 * complaint never counts against the new day's cap (the cap counts the day each came, not the day it was freed). Its
 * acknowledge and resolve reminders keep running from the day it came (criterio C-49): the reporter's receipt promised them
 * then, and the hold lasts a day. It runs for every company, as the system, and one company's failure keeps no other's.
 */
final readonly class ReleaseHeldComplaints
{
    public function __construct(private Companies $companies, private AssignComplaint $assign) {}

    /**
     * @return int how many complaints were released.
     */
    public function handle(): int
    {
        $released = 0;
        $tenants = ComplaintChannel::query()->withoutGlobalScopes()->orderBy('tenant_id')->get(['tenant_id'])->map(fn (ComplaintChannel $channel): string => $channel->tenant_id);

        foreach ($tenants as $tenantId) {
            try {
                Context::scope(function () use ($tenantId, &$released): void {
                    $this->companies->enter($tenantId);
                    $released += $this->releaseCompany();
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                report($failure);
            }
        }

        if ($released > 0) {
            Log::info('complaint.released', ['count' => $released]);
        }

        return $released;
    }

    private function releaseCompany(): int
    {
        $held = Complaint::query()->where('held', true)->whereDate('received_on', '<', Dates::today()->toDateString())->get();

        foreach ($held as $complaint) {
            DB::transaction(function () use ($complaint): void {
                $complaint->update(['held' => false]);
                $this->assign->handle($complaint);
            });
        }

        return $held->count();
    }
}
