<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Contracts;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Campaigns\Data\ComplianceCycle;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The campaigns of the current company as other modules read them: what was applied, to how many, when, and how many
 * people answered each guide. Never what they answered.
 */
interface CampaignRecords
{
    /** A campaign by id; null when it does not exist or belongs to another company. */
    public function find(string $id): ?CampaignRecord;

    /**
     * What is on the go: the campaigns in course, the soonest to close first; then the drafts and the campaigns closed
     * in the last 30 days, the newest first.
     *
     * @return list<CampaignRecord>
     */
    public function current(): array;

    /**
     * The closed NOM-035 evaluations (Guía I by event left out), the latest first, a page at a time.
     *
     * @return LengthAwarePaginator<int, CampaignRecord>
     */
    public function closedEvaluations(int $perPage, string $pageName = 'page'): LengthAwarePaginator;

    /**
     * Where each work center stands in its two-year cycle of NOM-035 evaluations (num. 7.9).
     *
     * @param  list<string>  $workCenterIds
     * @return array<string, ComplianceCycle> by work center id, in the order given
     */
    public function cycles(array $workCenterIds): array;
}
