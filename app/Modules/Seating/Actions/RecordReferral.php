<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Actions\RecordAudit;
use App\Modules\Seating\Models\MedicalReferral;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records that a person was referred to medical attention (Quinta J), or that they were evaluated and no referral was
 * needed. The note has no clinical detail and is never copied to the log or the audit trail (criterio C-18).
 */
final readonly class RecordReferral
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(RiskAssessment $assessment, bool $referred, string $referredOn, ?string $note, int $userId): MedicalReferral
    {
        return DB::transaction(function () use ($assessment, $referred, $referredOn, $note, $userId): MedicalReferral {
            $referral = MedicalReferral::query()->create([
                'tenant_id' => $this->tenant->id(),
                'employee_id' => $assessment->employee_id,
                'work_center_id' => $assessment->work_center_id,
                'referred' => $referred,
                'referred_on' => $referredOn,
                'note' => $note,
                'created_by' => $userId,
            ]);

            $record = ['referral_id' => $referral->id, 'work_center_id' => $assessment->work_center_id];
            $this->audit->handle('seating.referral_recorded', $referral, $record);
            Log::info('seating.referral_recorded', $record);

            return $referral;
        });
    }
}
