<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Organization\Contracts\Roster;

/**
 * How the disclosure stands in each work center (criteria C-52 and C-53): which incisos of 5.7 a physical means covered, and
 * how many of the people active today confirmed the version of the policy in force. Each is read once for all the centers (a
 * fixed number of queries, whatever their number).
 */
final readonly class DeliveryStanding
{
    public function __construct(private Roster $roster) {}

    /**
     * @param  list<string>  $workCenterIds
     * @return array<string, list<string>> the incisos covered, by work center id; a center with no means is left out.
     */
    public function recordedItems(array $workCenterIds): array
    {
        $covered = [];

        foreach (DisseminationRecord::query()->whereIn('work_center_id', $workCenterIds)->get(['work_center_id', 'covered_items']) as $record) {
            $covered[$record->work_center_id] = array_values(array_unique([...$covered[$record->work_center_id] ?? [], ...$record->covered_items]));
        }

        return $covered;
    }

    /**
     * @param  array<string, PolicyFact>  $inForce  The policy in force by work center id.
     * @return array<string, array{active: int, confirmed: int}> by work center id of those with a policy.
     */
    public function acknowledgments(array $inForce): array
    {
        $active = $this->roster->activeIdsInMany(array_keys($inForce));
        $documents = array_values(array_unique(array_map(fn (PolicyFact $fact): string => $fact->documentId, $inForce)));
        $confirmed = [];

        foreach (Acknowledgment::query()->whereIn('document_id', $documents)->whereNotNull('acknowledged_at')->get(['document_id', 'employee_id']) as $ack) {
            $confirmed[$ack->document_id][$ack->employee_id] = true;
        }

        return $this->count($inForce, $active, $confirmed);
    }

    /**
     * @param  array<string, PolicyFact>  $inForce
     * @param  array<string, list<string>>  $active
     * @param  array<string, array<string, true>>  $confirmed
     * @return array<string, array{active: int, confirmed: int}>
     */
    private function count(array $inForce, array $active, array $confirmed): array
    {
        $counts = [];

        foreach ($inForce as $center => $fact) {
            $people = $active[$center] ?? [];
            $counts[$center] = ['active' => count($people), 'confirmed' => count(array_filter($people, fn (string $id): bool => isset($confirmed[$fact->documentId][$id])))];
        }

        return $counts;
    }
}
