<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\IdentityMode;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;

/**
 * The page of one case, for a handler assigned to it: the only place where the words of a complaint reach a screen. An
 * anonymous complaint has no field of identity at all, not even an empty one.
 */
final readonly class CasePages
{
    public function __construct(private Members $members, private WorkCenters $workCenters) {}

    /**
     * @return array<string, mixed>
     */
    public function show(Complaint $complaint): array
    {
        $identity = $complaint->identity_mode === IdentityMode::Confidential
            ? ['reporterName' => $complaint->reporter_name, 'reporterContact' => $complaint->reporter_contact]
            : [];

        return [
            'case' => [
                'id' => $complaint->id,
                'reference' => $complaint->reference,
                'type' => $complaint->type->value,
                'typeLabel' => $complaint->type->label(),
                'categoryLabel' => $complaint->category?->label(),
                'center' => $complaint->work_center_id === null ? null : $this->workCenters->profile($complaint->work_center_id)->name ?? null,
                'kindLabel' => $complaint->respondent_kind->shortLabel(),
                'identity' => $complaint->identity_mode->value,
                ...$identity,
                'description' => $complaint->description,
                'respondentText' => $complaint->respondent_text,
                'involvedText' => $complaint->involved_text,
                'receivedOn' => $complaint->received_on->toDateString(),
                'occurredOn' => $complaint->occurred_on?->toDateString(),
                'status' => $complaint->status->value,
                'statusLabel' => $complaint->status->label(),
                'onHold' => $complaint->on_hold,
                'resolution' => $complaint->resolution?->label(),
            ],
            'access' => $this->access($complaint),
        ];
    }

    /**
     * Who has the case, by name: the handlers assigned to it now.
     *
     * @return list<string>
     */
    private function access(Complaint $complaint): array
    {
        $ids = ComplaintAssignment::query()->where('complaint_id', $complaint->id)->whereNull('ended_on')->get()->map(fn (ComplaintAssignment $assignment): int => $assignment->user_id)->all();
        $names = [];

        foreach ($this->members->all() as $member) {
            if (in_array($member->userId, $ids, true)) {
                $names[] = $member->name;
            }
        }

        sort($names);

        return $names;
    }
}
