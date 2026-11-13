<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Contracts\CommitteeWork;
use App\Modules\Seating\Data\CommitteeTask;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Enums\RowState;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Telework\Contracts\Committee;

final readonly class CompanyCommitteeWork implements CommitteeWork
{
    public function __construct(private Roster $roster, private ComplianceRows $rows, private Committee $committee) {}

    public function waiting(array $workCenterIds): array
    {
        $drafts = RiskAssessment::query()->whereIn('work_center_id', $workCenterIds)->where('status', AssessmentStatus::Draft)->orderBy('created_at')->orderBy('id')->get(['work_center_id', 'employee_id']);
        $unrecorded = RiskAssessment::query()->whereIn('work_center_id', $workCenterIds)->where('status', AssessmentStatus::Signed)->whereNull('recorded_in_document_id')->pluck('work_center_id')->countBy();
        $names = $this->roster->findMany(array_values(array_unique($drafts->map(fn (RiskAssessment $assessment): string => $assessment->employee_id)->all())));
        $facts = ComplianceFacts::ofMany($workCenterIds, $this->committee);
        $tasks = [];

        foreach ($workCenterIds as $id) {
            $here = $drafts->where('work_center_id', $id);

            if ($here->isNotEmpty()) {
                $tasks[] = new CommitteeTask($id, 'signatures', $here->count(), array_values(array_filter($here->map(fn (RiskAssessment $assessment): ?string => isset($names[$assessment->employee_id]) ? $names[$assessment->employee_id]->name : null)->all(), fn (?string $name): bool => $name !== null)));
            }

            if ($unrecorded->get($id, 0) > 0) {
                $tasks[] = new CommitteeTask($id, 'minutes', $unrecorded->get($id, 0), []);
            }

            $unassessed = collect($this->rows->fromFacts($facts[$id]))->firstWhere('key', 'r2');

            if ($unassessed !== null && $unassessed->state === RowState::Missing) {
                $tasks[] = new CommitteeTask($id, 'unassessed', $unassessed->count, []);
            }
        }

        return $tasks;
    }
}
