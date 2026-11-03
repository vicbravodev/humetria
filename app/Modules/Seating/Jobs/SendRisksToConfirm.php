<?php

declare(strict_types=1);

namespace App\Modules\Seating\Jobs;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Notifications\RisksToConfirm;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells a person their current signed assessment is waiting for their confirmation, with their personal link derived here.
 * Only ids travel in the job. Nothing is sent when they already confirmed it, have no email, left, or the company may
 * not be used; a person has at most one waiting.
 */
final class SendRisksToConfirm implements ShouldBeUniqueUntilProcessing, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** Seconds a person's waiting request blocks another one. */
    public int $uniqueFor = 600;

    public function __construct(public readonly string $tenantId, public readonly string $employeeId) {}

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->employeeId}";
    }

    public function handle(Companies $companies, Roster $roster, PortalAccess $access): void
    {
        $company = $companies->enter($this->tenantId);
        $person = $roster->find($this->employeeId);

        if ($company->blocked || $person === null || ! $person->active || $person->email === null) {
            return;
        }

        $current = RiskAssessment::query()->where('employee_id', $person->id)->where('status', AssessmentStatus::Signed)->latest('signed_at')->first(['id']);

        if ($current === null || RiskAcknowledgment::query()->where('assessment_id', $current->id)->exists()) {
            return;
        }

        Notification::route('mail', $person->email)->notify(new RisksToConfirm(route('portal.seating.show', $access->token($person->id)), $person->name, $company->tradeName));
    }
}
