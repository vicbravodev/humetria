<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Responses\Data\ScreeningRecord;
use App\Modules\Results\Enums\Destination;
use App\Modules\Results\Support\ResultsClock;
use App\Modules\Results\Support\ScreeningReferrals;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who requires a clinical assessment after their Guía I (num. 5.5), to channel them to care, and their names for the
 * record of num. 5.8 c (board V4-N035-Canalizacion): who waits first, who was channeled a click away, and the person chosen
 * open beside them. Only for whoever may see the Guía I; for anyone else it does not exist (404). Every consultation goes to
 * the audit trail.
 */
final class ScreeningController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(Request $request, mixed $tenant, string $campaign, CampaignRecords $campaigns, ResponseReader $responses, Roster $roster, WorkCenters $workCenters, RecordAudit $audit, ScreeningReferrals $referrals): Response
    {
        abort_unless($request->user()?->can('view-trauma-screenings') ?? false, 404);
        $found = $campaigns->find($campaign) ?? abort(404);
        $audit->handle('results.screenings_viewed', metadata: ['campaign_id' => $found->id]);

        $view = $request->query('view') === 'channeled' ? 'channeled' : 'pending';
        $channeled = $referrals->channeled($found->id);
        $page = $view === 'channeled'
            ? $responses->requiringAssessment($found->id, self::PER_PAGE, among: $channeled)
            : $responses->requiringAssessment($found->id, self::PER_PAGE, except: $channeled);
        $chosen = $this->chosen($request, $found->id, $responses) ?? $page->items()[0] ?? null;
        $ids = array_values(array_unique([...array_map(fn (ScreeningRecord $record): string => $record->employeeId, $page->items()), ...($chosen === null ? [] : [$chosen->employeeId])]));
        $people = $roster->findMany($ids);
        $records = $referrals->of($found->id, $ids);
        $counts = $responses->screeningCounts($found->id);
        $row = fn (ScreeningRecord $record): array => [
            'id' => $record->employeeId,
            'name' => $people[$record->employeeId]->name ?? '',
            'area' => $people[$record->employeeId]->areaName ?? '',
            'jobTitle' => $people[$record->employeeId]->jobTitle ?? '',
            'answeredOn' => ResultsClock::day($record->answeredAt),
            'referral' => $records[$record->employeeId] ?? null,
        ];

        return Inertia::render('results/screenings', [
            'campaign' => ['id' => $found->id, 'workCenter' => $workCenters->profile($found->workCenterId)->name ?? '', 'kindLabel' => $found->kindLabel],
            'counts' => $counts,
            'referralCounts' => $referrals->counts($found->id, $counts['requiring']),
            'view' => $view,
            'people' => $page->through($row),
            'selected' => $chosen === null ? null : [...$row($chosen), 'reachable' => self::reachable($people[$chosen->employeeId] ?? null)],
            'destinations' => array_map(fn (Destination $destination): array => ['value' => $destination->value, 'label' => $destination->label(), 'hint' => $destination->hint(), 'script' => $destination->script(), 'notice' => $destination->notice()], Destination::cases()),
            'today' => CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString(),
        ]);
    }

    /** The person asked for, when their Guía I in this campaign requires an assessment; anyone else is not opened. */
    private function chosen(Request $request, string $campaignId, ResponseReader $responses): ?ScreeningRecord
    {
        $person = $request->query('person');

        return is_string($person) && $person !== '' ? $responses->requiringAssessment($campaignId, 1, among: [$person])->items()[0] ?? null : null;
    }

    /** Whether a notice in their portal can reach the person: it is announced by email. */
    private static function reachable(?RosterEntry $person): bool
    {
        return $person !== null && $person->active && $person->email !== null;
    }
}
