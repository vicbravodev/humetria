<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\TenureBand;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Audience\ManagerGroups;
use App\Modules\Engagement\Support\Audience\Segment;
use App\Modules\Engagement\Support\Audience\SegmentGeneralizer;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Engagement\Support\Thresholds;
use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Contracts\Seniority;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Invites everyone who is active in the company and fixes, once, the coarse group each person is in: work center, area,
 * tenure range and the group of their manager. Whatever happens to them afterwards (a move, a new manager) changes
 * nothing, so a person is always counted where they were invited. The groups are made coarser until none is small
 * enough to point at someone.
 */
final readonly class SnapshotAudience
{
    public function __construct(
        private Roster $roster,
        private WorkCenters $workCenters,
        private Seniority $seniority,
        private ReportingLines $lines,
        private Thresholds $thresholds,
    ) {}

    /** How many people the survey was sent to; running it again changes nothing. */
    public function handle(Survey $survey): int
    {
        $existing = Invitation::query()->where('survey_id', $survey->id)->count();

        if ($existing > 0) {
            return $existing;
        }

        $centers = array_map(fn ($center): string => $center->id, $this->workCenters->all());
        $active = array_values(array_unique(array_merge(...array_values($this->roster->activeIdsInMany($centers)))));
        $people = $this->roster->findMany($active);
        $ids = array_keys($people);
        $activeSet = array_flip($active);
        $starts = $this->seniority->startDatesFor($ids);
        $today = SurveyClock::day();

        // ponytail: one chainOf call per person at launch; ask ReportingLines for a bulk read if a company passes a few thousand.
        $managerOf = [];
        foreach ($ids as $id) {
            $chain = array_values(array_filter($this->lines->chainOf($id), fn (string $manager): bool => isset($activeSet[$manager])));
            $managerOf[$id] = $chain[0] ?? null;
            foreach ($chain as $level => $manager) {
                $managerOf[$manager] = $chain[$level + 1] ?? null;
            }
        }

        $managerGroups = ManagerGroups::partition($managerOf, $ids, config()->integer('results.min_group'));
        $commentGroups = ManagerGroups::partition($managerOf, $ids, config()->integer('engagement.min_comments'));

        $segments = [];
        foreach ($people as $id => $person) {
            $segments[$id] = new Segment(
                $person->workCenterId,
                $person->areaId,
                isset($starts[$id]) ? TenureBand::on($starts[$id], $today)->value : null,
                $managerGroups[$id],
            );
        }

        $segments = SegmentGeneralizer::generalize($segments, $this->thresholds->met(...));

        DB::transaction(function () use ($survey, $ids, $segments, $commentGroups, $activeSet): void {
            foreach ($ids as $id) {
                $direct = $this->lines->managerOf($id);
                Invitation::query()->create([
                    'tenant_id' => $survey->tenant_id,
                    'survey_id' => $survey->id,
                    'employee_id' => $id,
                    'work_center_id' => $segments[$id]->workCenterId,
                    'area_id' => $segments[$id]->areaId,
                    'tenure_band' => $segments[$id]->tenure,
                    'manager_group_id' => $segments[$id]->managerGroupId,
                    'comment_group_id' => $commentGroups[$id],
                    'about_manager_id' => $direct !== null && isset($activeSet[$direct]) ? $direct : null,
                ]);
            }
        });

        Log::info('engagement.audience_snapshotted', ['survey_id' => $survey->id, 'invited' => count($ids)]);

        return count($ids);
    }
}
