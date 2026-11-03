<?php

declare(strict_types=1);

namespace App\Modules\Seating\Listeners;

use App\Modules\Seating\Actions\RequestAcknowledgment;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Events\RiskAssessmentSigned;
use App\Modules\Seating\Models\RiskAssessment;

/**
 * An assessment was signed: the person is asked to confirm they were informed of it (Quinta H).
 */
final readonly class RequestOnSignature
{
    public function __construct(private RequestAcknowledgment $request) {}

    public function handle(RiskAssessmentSigned $event): void
    {
        $assessment = RiskAssessment::query()->where('employee_id', $event->employeeId)->where('status', AssessmentStatus::Signed)->latest('signed_at')->first();

        if ($assessment !== null) {
            $this->request->handle($assessment);
        }
    }
}
