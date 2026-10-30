<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Campaigns\Data\ComplianceCycle;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final readonly class CompanyCampaignRecords implements CampaignRecords
{
    private const int RECENT_DAYS = 30;

    private const int CYCLE_YEARS = 2;

    public function __construct(private InstrumentTally $tally) {}

    public function find(string $id): ?CampaignRecord
    {
        $campaign = Str::isUuid($id) ? Campaign::query()->find($id) : null;

        return $campaign === null ? null : $this->records(new Collection([$campaign]))[0];
    }

    public function current(): array
    {
        $since = CampaignClock::today()->subDays(self::RECENT_DAYS);
        $campaigns = Campaign::query()
            ->where(fn (Builder $query) => $query->whereIn('status', [CampaignStatus::Draft, CampaignStatus::Active])->orWhere('closed_at', '>=', $since))
            ->get();
        $order = fn (Campaign $campaign): array => match ($campaign->status) {
            CampaignStatus::Active => [0, $campaign->closes_on?->format('Y-m-d'), $campaign->id],
            CampaignStatus::Draft => [1, -$campaign->created_at->getTimestamp(), $campaign->id],
            CampaignStatus::Closed => [2, -($campaign->closed_at?->getTimestamp() ?? 0), $campaign->id],
        };

        return $this->records($campaigns->sort(fn (Campaign $a, Campaign $b): int => $order($a) <=> $order($b))->values());
    }

    public function closedEvaluations(int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = Campaign::query()
            ->where('kind', CampaignKind::Nom035Evaluation)
            ->where('status', CampaignStatus::Closed)
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->paginate($perPage, pageName: $pageName)
            ->withQueryString();
        $records = $this->records($page->getCollection());

        return $page->through(fn (Campaign $campaign, int $index): CampaignRecord => $records[$index]);
    }

    public function cycles(array $workCenterIds): array
    {
        $evaluations = Campaign::query()
            ->where('kind', CampaignKind::Nom035Evaluation)
            ->whereIn('status', [CampaignStatus::Active, CampaignStatus::Closed])
            ->whereIn('work_center_id', $workCenterIds)
            ->get(['id', 'work_center_id', 'status', 'launched_at', 'closes_on', 'closed_at']);
        $cycles = [];

        foreach ($workCenterIds as $workCenterId) {
            $own = $evaluations->where('work_center_id', $workCenterId);
            $cycles[$workCenterId] = self::cycle(
                $own->firstWhere('status', CampaignStatus::Active),
                $own->where('status', CampaignStatus::Closed)->sortByDesc('closed_at')->first(),
            );
        }

        return $cycles;
    }

    private static function cycle(?Campaign $running, ?Campaign $last): ComplianceCycle
    {
        if ($running?->launched_at !== null && $running->closes_on !== null) {
            return new ComplianceCycle('in_course', CampaignClock::day($running->launched_at), CampaignClock::day($running->closes_on, isDate: true), CampaignClock::elapsed($running->launched_at, CampaignClock::deadline($running->closes_on)->subDay()));
        }

        if ($last?->closed_at === null) {
            return new ComplianceCycle('never', null, null, 0);
        }

        $due = $last->closed_at->addYears(self::CYCLE_YEARS);
        $overdue = $due->isPast();

        return new ComplianceCycle($overdue ? 'overdue' : 'up_to_date', CampaignClock::day($last->closed_at), CampaignClock::day($due), $overdue ? 100 : CampaignClock::elapsed($last->closed_at, $due));
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     * @return list<CampaignRecord> in the same order
     */
    private function records(Collection $campaigns): array
    {
        $tally = $this->tally->of(array_values(array_filter($campaigns->modelKeys(), is_string(...))));

        return array_values($campaigns->map(fn (Campaign $campaign): CampaignRecord => self::record($campaign, $tally[$campaign->id] ?? []))->all());
    }

    /**
     * @param  array<string, array<string, int>>  $tally
     */
    private static function record(Campaign $campaign, array $tally): CampaignRecord
    {
        $invited = [];
        $answered = [];

        foreach ($campaign->instruments as $instrument) {
            $answered[$instrument] = $tally[$instrument][InstrumentStatus::Completed->value] ?? 0;
            $invited[$instrument] = $answered[$instrument] + ($tally[$instrument][InstrumentStatus::Pending->value] ?? 0);
        }

        return new CampaignRecord(
            id: $campaign->id,
            workCenterId: $campaign->work_center_id,
            kind: $campaign->kind->value,
            kindLabel: $campaign->kind->label(),
            closed: $campaign->status === CampaignStatus::Closed,
            instruments: $campaign->instruments,
            workers: $campaign->workers,
            sample: $campaign->population === Population::Sample,
            sampleSize: $campaign->sample_size,
            launchedOn: $campaign->launched_at === null ? null : CampaignClock::day($campaign->launched_at),
            closedOn: $campaign->closed_at === null ? null : CampaignClock::day($campaign->closed_at),
            invited: $invited,
            answered: $answered,
            closesOn: $campaign->closes_on === null ? null : CampaignClock::day($campaign->closes_on, isDate: true),
            daysLeft: $campaign->status === CampaignStatus::Active && $campaign->closes_on !== null ? CampaignClock::daysLeft($campaign->closes_on) : null,
        );
    }
}
