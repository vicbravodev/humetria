<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintEntry;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Complaints\Support\RandomCode;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily purge (criterio C-48): the cases closed longer ago than their company's retention lose their content (the
 * texts, who wrote them, the thread, the assignments, the reference and the key) and leave a skeleton of type, category,
 * resolution, the month received and the day closed, with no personal data, enough to count the cases the channel handled. A
 * case under legal hold, one still open and one already purged are left alone; running it again changes nothing. It runs for
 * every company, usable or not: data does not outlive its time because of a bill.
 */
final readonly class PurgeComplaints
{
    public function __construct(private Companies $companies, private RecordAudit $audit) {}

    /**
     * @return int how many cases were purged.
     */
    public function handle(): int
    {
        $purged = 0;
        $tenants = ComplaintChannel::query()->withoutGlobalScopes()->orderBy('tenant_id')->get(['tenant_id'])->map(fn (ComplaintChannel $channel): string => $channel->tenant_id);

        foreach ($tenants as $tenantId) {
            try {
                // Each in its own company, as the system: the audit entry has no actor.
                Context::scope(function () use ($tenantId, &$purged): void {
                    // Deliberately no `stopped('complaints')` here: data does not outlive its retention because a company lost the module or is restricted.
                    $this->companies->enter($tenantId);
                    $purged += $this->purgeCompany();
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' data from being purged.
                report($failure);
            }
        }

        Log::info('complaints.purged', ['count' => $purged]);

        return $purged;
    }

    private function purgeCompany(): int
    {
        $channel = ComplaintChannel::query()->firstOrFail();
        $limit = Dates::today()->subMonthsNoOverflow($channel->retention_months)->toDateString();
        $purged = 0;
        $this->clearEmails($channel);

        $due = Complaint::query()
            ->where('status', ComplaintStatus::Closed->value)
            ->where('on_hold', false)
            ->whereNull('purged_on')
            ->whereDate('closed_on', '<=', $limit)
            ->get();

        foreach ($due as $complaint) {
            DB::transaction(function () use ($complaint): void {
                ComplaintEntry::query()->where('complaint_id', $complaint->id)->delete();
                ComplaintAssignment::query()->where('complaint_id', $complaint->id)->delete();
                $complaint->update($this->skeleton($complaint));
                $this->audit->handle('complaint.purged', $complaint);
            });
            $purged++;
        }

        return $purged;
    }

    /** The write-only address goes thirty days after the case closes, held or not: a hold keeps the case, not the address. */
    private function clearEmails(ComplaintChannel $channel): void
    {
        $limit = Dates::today()->subDays(config()->integer('complaints.retention.notice_email_days'))->toDateString();
        $cleared = Complaint::query()->where('status', ComplaintStatus::Closed->value)->whereNotNull('reporter_email')->whereDate('closed_on', '<=', $limit)->get();

        foreach ($cleared as $complaint) {
            $complaint->update(['reporter_email' => null]);
        }

        if ($cleared->isNotEmpty()) {
            Log::info('complaints.reporter_emails_cleared', ['count' => $cleared->count(), 'channel_id' => $channel->id]);
        }
    }

    /**
     * What a purged case keeps: nothing that says who, where or what.
     *
     * @return array<string, mixed>
     */
    private function skeleton(Complaint $complaint): array
    {
        return [
            'description' => null,
            'respondent_text' => null,
            'involved_text' => null,
            'reporter_name' => null,
            'reporter_contact' => null,
            'reporter_email' => null,
            'identity_mode' => 'anonymous',
            'respondent_kind' => 'unknown',
            'work_center_id' => null,
            'occurred_on' => null,
            'acknowledged_at' => null,
            'received_on' => $complaint->received_on->startOfMonth()->toDateString(),
            // The reference and the key stop opening the case: they are replaced by values nobody was given.
            'reference' => RandomCode::make(8),
            'access_key_hash' => hash('sha256', RandomCode::make(20)),
            'purged_on' => Dates::today()->toDateString(),
        ];
    }
}
