<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Jobs\SendRisksToConfirm;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

/**
 * Asks a person to confirm they were informed of their assessment (Quinta H): one email, with their own link. It happens
 * when the assessment is signed; RH can send it again.
 */
final readonly class RequestAcknowledgment
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(RiskAssessment $assessment): void
    {
        SendRisksToConfirm::dispatch($this->tenant->id(), $assessment->employee_id);
        Log::info('seating.acknowledgment_requested', ['assessment_id' => $assessment->id, 'work_center_id' => $assessment->work_center_id]);
    }
}
