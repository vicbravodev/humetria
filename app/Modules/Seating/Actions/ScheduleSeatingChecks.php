<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Support\AssessmentTerm;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily Ley Silla check, for every company: marks as «por reevaluar» the current assessment of each person that turns a
 * year old (the term is `seating.reassessment_months`; the Disposiciones set none, criterio C-07). It only sets the mark: the
 * signed assessment stays as it is until another is signed.
 */
final readonly class ScheduleSeatingChecks
{
    public function __construct(private Companies $companies) {}

    /**
     * @return int how many assessments were marked.
     */
    public function handle(): int
    {
        $flagged = 0;

        foreach (RiskAssessment::query()->withoutGlobalScopes()->where('status', AssessmentStatus::Signed)->distinct()->orderBy('tenant_id')->get(['tenant_id'])->map(fn (RiskAssessment $assessment): string => $assessment->tenant_id) as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, &$flagged): void {
                    $flagged += $this->check($tenantId);
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others from being checked.
                report($failure);
            }
        }

        Log::info('seating.checks_scheduled', ['flagged' => $flagged]);

        return $flagged;
    }

    private function check(string $tenantId): int
    {
        if ($this->companies->enter($tenantId)->blocked) {
            return 0;
        }

        // Only the current assessment of each person: a later one makes the earlier part of the history.
        $current = [];

        foreach (RiskAssessment::query()->where('status', AssessmentStatus::Signed)->orderBy('signed_at')->get(['id', 'employee_id', 'signed_at', 'reassessment_due']) as $assessment) {
            $current[$assessment->employee_id] = $assessment;
        }

        $flagged = 0;

        foreach ($current as $assessment) {
            if (! $assessment->reassessment_due && $assessment->signed_at !== null && AssessmentTerm::overdue($assessment->signed_at)) {
                $assessment->update(['reassessment_due' => true]);
                $flagged++;
            }
        }

        return $flagged;
    }
}
