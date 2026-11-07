<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Saves the company's one policy. It decides how strong the evidence of every mark is, so each change goes to the audit
 * trail.
 */
final readonly class SavePolicy
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(OutOfZoneRule $outOfZone, int $maxAccuracyM, bool $requireQrOnsite, bool $trustedMayUseBrowser, int $retentionMonths): AttendancePolicy
    {
        $policy = DB::transaction(function () use ($outOfZone, $maxAccuracyM, $requireQrOnsite, $trustedMayUseBrowser, $retentionMonths): AttendancePolicy {
            $policy = AttendancePolicy::query()->updateOrCreate(['tenant_id' => $this->tenant->id()], [
                'out_of_zone' => $outOfZone,
                'max_accuracy_m' => $maxAccuracyM,
                'require_qr_onsite' => $requireQrOnsite,
                'trusted_may_use_browser' => $trustedMayUseBrowser,
                'retention_months' => $retentionMonths,
            ]);
            $this->audit->handle('attendance.policy_saved', $policy, $policy->only(['out_of_zone', 'max_accuracy_m', 'require_qr_onsite', 'trusted_may_use_browser', 'retention_months']));

            return $policy;
        });

        Log::info('attendance.policy_saved', ['policy_id' => $policy->id]);

        return $policy;
    }
}
