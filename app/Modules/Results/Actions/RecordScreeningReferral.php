<?php

declare(strict_types=1);

namespace App\Modules\Results\Actions;

use App\Actions\RecordAudit;
use App\Modules\Results\Enums\ReferralStatus;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records, replacing what was there, that a person whose Guía I requires a clinical assessment was referred or attended
 * (num. 5.5, 5.8 c). The audit trail says who changed which status; the log only that a record was saved: neither
 * carries the person, the day or the institution, which are health data.
 */
final readonly class RecordScreeningReferral
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(string $campaignId, string $employeeId, ReferralStatus $status, string $referredOn, string $institution, int $userId): ScreeningReferral
    {
        return DB::transaction(function () use ($campaignId, $employeeId, $status, $referredOn, $institution, $userId): ScreeningReferral {
            $referral = ScreeningReferral::query()->updateOrCreate(
                ['campaign_id' => $campaignId, 'employee_id' => $employeeId],
                ['tenant_id' => $this->tenant->id(), 'status' => $status, 'referred_on' => $referredOn, 'institution' => $institution, 'updated_by' => $userId],
            );

            $this->audit->handle('results.referral_recorded', $referral, ['campaign_id' => $campaignId, 'status' => $status->value]);
            Log::info('screening_referral.saved', ['campaign_id' => $campaignId, 'referral_id' => $referral->id]);

            return $referral;
        });
    }
}
