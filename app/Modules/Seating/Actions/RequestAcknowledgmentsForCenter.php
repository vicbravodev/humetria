<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Jobs\SendRisksToConfirm;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

/**
 * Asks everyone in a center whose current assessment is not confirmed yet to confirm it. Those without an email are counted,
 * not mailed: RH shows them their page.
 */
final readonly class RequestAcknowledgmentsForCenter
{
    public function __construct(private CurrentTenant $tenant, private Roster $roster, private MailQuota $mail) {}

    /**
     * @return array{queued: int, withoutEmail: int}
     */
    public function handle(string $workCenterId): array
    {
        $current = [];

        foreach (RiskAssessment::query()->where('work_center_id', $workCenterId)->where('status', AssessmentStatus::Signed)->orderBy('signed_at')->get(['id', 'employee_id']) as $assessment) {
            $current[$assessment->employee_id] = $assessment->id;
        }

        $confirmed = RiskAcknowledgment::query()->whereIn('assessment_id', array_values($current))->pluck('assessment_id')->all();
        $pending = array_keys(array_filter($current, fn (string $assessmentId): bool => ! in_array($assessmentId, $confirmed, true)));
        $reachable = [];
        $withoutEmail = 0;

        foreach ($this->roster->findMany($pending) as $person) {
            if (! $person->active) {
                continue;
            }

            if ($person->email === null) {
                $withoutEmail++;

                continue;
            }

            $reachable[] = $person->id;
        }

        // All of them or none: held before the first one is queued.
        $this->mail->reserve(count($reachable));

        foreach ($reachable as $employeeId) {
            SendRisksToConfirm::dispatch($this->tenant->id(), $employeeId);
        }

        $queued = count($reachable);

        Log::info('seating.acknowledgments_requested', ['work_center_id' => $workCenterId, 'queued' => $queued, 'without_email' => $withoutEmail]);

        return ['queued' => $queued, 'withoutEmail' => $withoutEmail];
    }
}
