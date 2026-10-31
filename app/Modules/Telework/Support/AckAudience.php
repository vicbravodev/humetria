<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;

/**
 * Who has to acknowledge a document today (criterio C-20): the policy, every active employee of its center; the risk
 * information, the roster (padrón) of the center and its Comisión; the other kinds nobody.
 */
final readonly class AckAudience
{
    public function __construct(private Roster $roster, private Members $members) {}

    /** @return list<string> */
    public function employeeIds(TeleworkDocument $document): array
    {
        return match ($document->kind) {
            DocumentKind::Policy => array_map(fn (RosterEntry $person): string => $person->id, $this->roster->activeIn($document->work_center_id)),
            DocumentKind::RiskInformation => array_values(array_map(fn (Teleworker $period): string => $period->employee_id, Teleworker::query()
                ->where('work_center_id', $document->work_center_id)->where('status', TeleworkerStatus::Active)->get(['employee_id'])->all())),
            default => [],
        };
    }

    /** @return list<int> */
    public function userIds(TeleworkDocument $document): array
    {
        if ($document->kind !== DocumentKind::RiskInformation) {
            return [];
        }

        $reviewers = array_map(fn (MemberSummary $member): int => $member->userId, $this->members->reviewers());

        return array_values(array_map(fn (CommitteeAssignment $seat): int => $seat->user_id, CommitteeAssignment::query()
            ->where('work_center_id', $document->work_center_id)->whereIn('user_id', $reviewers)->get(['user_id'])->all()));
    }
}
