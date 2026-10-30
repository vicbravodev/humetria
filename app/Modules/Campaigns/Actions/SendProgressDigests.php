<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Notifications\ProgressDigest;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Campaigns\Support\CampaignPages;
use App\Modules\Campaigns\Support\InstrumentTally;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Tells each company's owner, every Monday, how far each of its open evaluations got: answered against the target, the
 * areas that lack the most, and how many people a reminder would reach. Only who answered and who did not; never an
 * answer. Blocked companies and companies with no owner get nothing.
 */
final readonly class SendProgressDigests
{
    /** The areas an email shows: those that lack the most. */
    private const int AREAS = 5;

    public function __construct(
        private Companies $companies,
        private WorkCenters $workCenters,
        private Roster $roster,
        private InstrumentTally $tally,
        private CampaignPages $pages,
    ) {}

    /**
     * @return int how many digests were sent.
     */
    public function handle(): int
    {
        $sent = 0;

        foreach (Campaign::query()->withoutGlobalScopes()->where('status', CampaignStatus::Active)->orderBy('tenant_id')->get(['id', 'tenant_id']) as $campaign) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($campaign, &$sent): void {
                    $sent += $this->send($campaign->tenant_id, $campaign->id);
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' digests from going out.
                report($failure);
            }
        }

        return $sent;
    }

    private function send(string $tenantId, string $campaignId): int
    {
        $company = $this->companies->enter($tenantId);
        $owner = $this->companies->contact();
        $campaign = Campaign::query()->findOrFail($campaignId);
        $progress = $this->pages->progress($campaign, $this->tally->of([$campaign->id])[$campaign->id] ?? []);

        if ($company->blocked || $owner === null || $progress === null || $campaign->closes_on === null) {
            return 0;
        }

        $areas = $this->areas($campaign);

        Notification::route('mail', $owner)->notify(new ProgressDigest(
            workCenter: $this->workCenters->profile($campaign->work_center_id)->name ?? '',
            completed: $progress['completed'],
            target: $progress['target'],
            daysLeft: CampaignClock::daysLeft($campaign->closes_on),
            areas: $this->shown($areas),
            behind: $this->behind($areas),
            toRemind: $this->toRemind($campaign),
            url: route('tenant.campaigns.show', [$company->slug, $campaign->id]),
        ));
        Log::info('campaign.digest_sent', ['campaign_id' => $campaign->id]);

        return 1;
    }

    /**
     * Answered and invited of the guide that measures the campaign, by area.
     *
     * @return array<string, array{completed: int, total: int}>
     */
    private function areas(Campaign $campaign): array
    {
        $rows = ParticipantInstrument::query()
            ->join('campaign_participants', 'campaign_participants.id', '=', 'participant_instruments.participant_id')
            ->where('campaign_participants.campaign_id', $campaign->id)
            ->where('participant_instruments.instrument', array_last($campaign->instruments))
            ->whereIn('participant_instruments.status', [InstrumentStatus::Pending, InstrumentStatus::Completed])
            ->toBase()
            ->get(['campaign_participants.employee_id', 'participant_instruments.status']);
        $guides = [];

        foreach ($rows as $row) {
            if (is_string($row->employee_id) && is_string($row->status)) {
                $guides[] = ['employee' => $row->employee_id, 'completed' => $row->status === InstrumentStatus::Completed->value];
            }
        }

        $people = $this->roster->findMany(array_values(array_unique(array_column($guides, 'employee'))));
        $areas = [];

        foreach ($guides as $guide) {
            $area = $people[$guide['employee']]->areaName ?? 'Sin área';
            $areas[$area] ??= ['completed' => 0, 'total' => 0];
            $areas[$area]['total']++;
            $areas[$area]['completed'] += $guide['completed'] ? 1 : 0;
        }

        return $areas;
    }

    /**
     * The areas that lack the most, by name.
     *
     * @param  array<string, array{completed: int, total: int}>  $areas
     * @return list<array{name: string, completed: int, total: int}>
     */
    private function shown(array $areas): array
    {
        $rows = array_map(fn (string $name, array $counts): array => ['name' => $name, ...$counts], array_keys($areas), $areas);
        usort($rows, fn (array $a, array $b): int => [$b['total'] - $b['completed'], $a['name']] <=> [$a['total'] - $a['completed'], $b['name']]);
        $rows = array_slice($rows, 0, self::AREAS);
        usort($rows, fn (array $a, array $b): int => strcoll($a['name'], $b['name']));

        return $rows;
    }

    /**
     * The area with the most people still to answer, and how many.
     *
     * @param  array<string, array{completed: int, total: int}>  $areas
     * @return array{name: string, pending: int}|null
     */
    private function behind(array $areas): ?array
    {
        $behind = null;

        foreach ($areas as $name => $counts) {
            $pending = $counts['total'] - $counts['completed'];

            if ($pending > 0 && ($behind === null || $pending > $behind['pending'])) {
                $behind = ['name' => $name, 'pending' => $pending];
            }
        }

        return $behind;
    }

    /** Whom a reminder reaches, as ResendInvitations sends it: a guide pending and an email. */
    private function toRemind(Campaign $campaign): int
    {
        $employeeIds = $campaign->participants()->whereHas('instruments', fn ($guides) => $guides->where('status', InstrumentStatus::Pending))
            ->get(['employee_id'])
            ->map(fn (Participant $participant): string => $participant->employee_id)
            ->all();

        return count(array_filter($this->roster->findMany(array_values($employeeIds)), fn (RosterEntry $person): bool => $person->email !== null));
    }
}
