<?php

declare(strict_types=1);

namespace App\Modules\Results\Jobs;

use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Results\Notifications\ReferralFollowUp;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Reminds whoever recorded a channeling to ask how the person is (board V4-N035-Canalizacion), only while they may still see
 * the Guía I. Only ids travel in the job and the email names nobody: who it is about is read in Humetría. A blocked company,
 * or a record that no longer exists, gets nothing.
 */
final class SendReferralReminder implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly string $tenantId, public readonly string $referralId) {}

    public function handle(Companies $companies, Members $members): void
    {
        $company = $companies->enter($this->tenantId);
        $referral = ScreeningReferral::query()->find($this->referralId, ['id', 'campaign_id', 'employee_id', 'updated_by']);
        $who = $referral === null ? null : array_find($members->screeningViewers(), fn (MemberSummary $member): bool => $member->userId === $referral->updated_by);

        if ($company->stopped('nom035') || $referral === null || $who === null) {
            return;
        }

        Notification::route('mail', $who->email)->notify(new ReferralFollowUp(
            $company,
            route('tenant.campaigns.screenings', [$company->slug, $referral->campaign_id, 'view' => 'channeled', 'person' => $referral->employee_id]),
        ));
    }
}
