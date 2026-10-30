<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Data\PeopleFilter;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The people of a campaign and where each stands with its guides: whether they answered, never what. Where they stand
 * is read here; their name and area, from the roster, which also narrows and pages them.
 */
final readonly class CampaignPeople
{
    public const int PER_PAGE = 25;

    /**
     * One guide pending makes a person pending, or without email, or failed, by how their link went; else one cancelled,
     * cancelled.
     */
    private const string STANDING = <<<'SQL'
        case
            when bool_or(participant_instruments.status = 'pending')
                then case campaign_participants.invitation_status
                    when 'queued' then 'pending'
                    when 'sent' then 'pending'
                    when 'failed' then 'failed'
                    else 'unreachable'
                end
            when bool_or(participant_instruments.status = 'cancelled') then 'cancelled'
            else 'answered'
        end
        SQL;

    public function __construct(private Roster $roster, private Instruments $instruments) {}

    /**
     * How many people stand where, with the filter's guide or all of them.
     *
     * @return array<string, int> standing → people
     */
    public function standings(Campaign $campaign, ?string $guide): array
    {
        $counts = [];
        $rows = DB::query()->fromSub($this->standingQuery($campaign, $guide), 'people')
            ->groupBy('standing')
            ->selectRaw('standing, count(*)::int as total')
            ->get();

        foreach ($rows as $row) {
            if (is_string($row->standing) && is_int($row->total)) {
                $counts[$row->standing] = $row->total;
            }
        }

        return $counts;
    }

    /**
     * The people the filter matches, by name, a page at a time.
     *
     * @return LengthAwarePaginator<int, array{id: string, name: string, area: string, reachable: bool, inSample: bool, guides: list<array{key: string, title: string, status: string, statusLabel: string}>}>
     */
    public function page(Campaign $campaign, PeopleFilter $filter): LengthAwarePaginator
    {
        $page = $this->roster->page($this->rosterQuery($campaign, $filter), self::PER_PAGE);
        $participants = $campaign->participants()
            ->with('instruments')
            ->whereIn('employee_id', array_map(fn (RosterEntry $person): string => $person->id, $page->items()))
            ->get()
            ->keyBy('employee_id');
        $titles = [];

        foreach ($campaign->instruments as $key) {
            $titles[$key] = $this->instruments->definition($key)->shortTitle;
        }

        return $page->through(function (RosterEntry $person) use ($participants, $campaign, $titles): array {
            // The roster was asked only for this campaign's people.
            $participant = $participants->get($person->id) ?? throw new LogicException("Employee [{$person->id}] is not in the campaign.");
            $guides = $participant->instruments->sortBy(fn (ParticipantInstrument $guide): int|false => array_search($guide->instrument, $campaign->instruments, true));

            return [
                'id' => $participant->id,
                'name' => $person->name,
                'area' => $person->areaName,
                'reachable' => $person->email !== null,
                'inSample' => $participant->in_sample,
                'guides' => array_values($guides->map(fn (ParticipantInstrument $guide): array => [
                    'key' => $guide->instrument,
                    'title' => $titles[$guide->instrument] ?? $guide->instrument,
                    'status' => $guide->status->value,
                    'statusLabel' => $guide->status->label(),
                ])->all()),
            ];
        });
    }

    /**
     * The ids of everyone the filter matches.
     *
     * @return list<string>
     */
    public function employeeIds(Campaign $campaign, PeopleFilter $filter): array
    {
        return $this->roster->matchingIds($this->rosterQuery($campaign, $filter));
    }

    private function rosterQuery(Campaign $campaign, PeopleFilter $filter): RosterQuery
    {
        $standings = DB::query()->fromSub($this->standingQuery($campaign, $filter->guide), 'people')
            ->when($filter->standing !== null, fn ($query) => $query->where('standing', $filter->standing?->value))
            ->pluck('employee_id');

        return new RosterQuery(
            employeeIds: array_values(array_filter($standings->all(), is_string(...))),
            search: $filter->search,
            areaId: $filter->areaId,
        );
    }

    /**
     * Each person of the campaign with a guide (the given one, or any) and where they stand with it.
     *
     * @return Builder<Participant>
     */
    private function standingQuery(Campaign $campaign, ?string $guide): Builder
    {
        return $campaign->participants()->getQuery()
            ->join('participant_instruments', 'participant_instruments.participant_id', '=', 'campaign_participants.id')
            ->when($guide !== null, fn (Builder $query) => $query->where('participant_instruments.instrument', $guide))
            ->groupBy('campaign_participants.id')
            ->select('campaign_participants.employee_id')
            ->selectRaw(self::STANDING.' as standing');
    }
}
