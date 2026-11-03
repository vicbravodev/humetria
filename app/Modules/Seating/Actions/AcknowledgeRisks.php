<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

/**
 * The person confirms, from their portal, that they were informed of the risks and measures of their current signed
 * assessment (Quinta H), with the date and time. Once per assessment: confirming again changes nothing. A change of measures
 * does not ask again; a new signed assessment does.
 */
final readonly class AcknowledgeRisks
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(PortalIdentity $person): RiskAcknowledgment
    {
        $assessment = RiskAssessment::query()->where('employee_id', $person->employeeId)->where('status', AssessmentStatus::Signed)->latest('signed_at')->firstOrFail();

        $acknowledgment = RiskAcknowledgment::query()->createOrFirst(['assessment_id' => $assessment->id], [
            'tenant_id' => $this->tenant->id(),
            'employee_id' => $person->employeeId,
            'acknowledged_at' => now(),
        ]);

        if ($acknowledgment->wasRecentlyCreated) {
            Log::info('seating.acknowledged', ['assessment_id' => $assessment->id, 'work_center_id' => $assessment->work_center_id]);
        }

        return $acknowledgment;
    }
}
