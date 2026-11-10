<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Actions\SaveCommitment;
use App\Modules\Engagement\Enums\CommitmentGroup;
use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\Priority;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\SuggestedActions;
use App\Modules\Engagement\Support\Results\ResultsPresenter;
use App\Modules\Engagement\Support\Results\SurveyResults;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The props of the commitments page: what the company wrote down after a closed survey, the groups it may still write
 * for (only those the results show) and, for the drivers to act on first, what could be done. No response, comment or
 * figure of a group reaches it.
 */
final readonly class CommitmentPages
{
    public const int PER_PAGE = 10;

    public function __construct(private ResultsPresenter $presenter) {}

    /**
     * @return array<string, mixed>
     */
    public function props(Survey $survey, ?CommitmentStatus $status, bool $canManage): array
    {
        $available = SurveyResults::for($survey)->available();
        $base = [
            'survey' => ['id' => $survey->id, 'name' => $survey->name, 'status' => $survey->status->value],
            'available' => $available,
            'canManage' => $canManage,
            'published' => $survey->results_published_at !== null,
            'status' => $status?->value,
            'statuses' => array_map(fn (CommitmentStatus $case): array => ['value' => $case->value, 'label' => $case->label()], CommitmentStatus::cases()),
            'drivers' => array_map(fn (Driver $driver): array => ['value' => $driver->value, 'label' => $driver->label()], Driver::cases()),
        ];

        if (! $available) {
            $empty = new LengthAwarePaginator([], 0, self::PER_PAGE);

            return [...$base, 'commitments' => $empty, 'counts' => $this->counts($survey), 'groups' => [], 'suggestions' => [], 'loopAction' => null];
        }

        return [
            ...$base,
            'commitments' => $this->page($survey, $status),
            'counts' => $this->counts($survey),
            'groups' => $this->groups($survey),
            'suggestions' => $this->suggestions($survey),
            'loopAction' => $this->presenter->loopAction(SurveyResults::for($survey)),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function page(Survey $survey, ?CommitmentStatus $status): LengthAwarePaginator
    {
        $page = Commitment::query()
            ->where('survey_id', $survey->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderBy('due_on')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $names = $this->names($page->getCollection()->all());
        $today = SurveyClock::day();

        return $page->through(fn (Commitment $commitment): array => $this->row($commitment, $names, $today));
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, mixed>
     */
    private function row(Commitment $commitment, array $names, CarbonImmutable $today): array
    {
        return [
            'id' => $commitment->id,
            'group_type' => $commitment->group_type->value,
            'group_id' => $commitment->group_id,
            'group_name' => $commitment->group_id === null ? 'Toda la empresa' : ($names[$commitment->group_id] ?? 'Sin nombre'),
            'driver' => $commitment->driver,
            'driver_label' => $commitment->driver === null ? null : Driver::from($commitment->driver)->label(),
            'heard' => $commitment->heard,
            'will_do' => $commitment->will_do,
            'owner_name' => $commitment->owner_name,
            'due_on' => $commitment->due_on->toDateString(),
            'status' => $commitment->status->value,
            'status_label' => $commitment->status->label(),
            'overdue' => $commitment->due_on->lessThan($today) && in_array($commitment->status, [CommitmentStatus::Open, CommitmentStatus::InProgress], true),
        ];
    }

    /**
     * @param  array<int, Commitment>  $commitments
     * @return array<string, string> group name by group id
     */
    private function names(array $commitments): array
    {
        $names = [];

        foreach (CommitmentGroup::cases() as $group) {
            $cut = $group->cut();
            $ids = array_values(array_unique(array_filter(array_map(fn (Commitment $commitment): ?string => $commitment->group_type === $group ? $commitment->group_id : null, $commitments))));

            if ($cut !== null && $ids !== []) {
                $names = [...$names, ...$this->presenter->names($cut, $ids)];
            }
        }

        return $names;
    }

    /**
     * How many commitments each status holds, and all together.
     *
     * @return array<string, int>
     */
    private function counts(Survey $survey): array
    {
        // At most three per group, so the rows are few; counting them here keeps the shape of the enum in one place.
        $byStatus = Commitment::query()->where('survey_id', $survey->id)->get(['id', 'status'])->countBy(fn (Commitment $commitment): string => $commitment->status->value);
        $counts = [];

        foreach (CommitmentStatus::cases() as $case) {
            $counts[$case->value] = $byStatus->get($case->value, 0);
        }

        return ['all' => array_sum($counts), ...$counts];
    }

    /**
     * The company and every group the results show, each with whether it already has its three commitments.
     *
     * @return list<array{type: string, id: string|null, name: string, full: bool}>
     */
    private function groups(Survey $survey): array
    {
        $results = SurveyResults::for($survey);
        $used = Commitment::query()->where('survey_id', $survey->id)->where('status', '!=', CommitmentStatus::Dropped)
            ->get(['id', 'group_type', 'group_id'])->countBy(fn (Commitment $commitment): string => $commitment->group_type->value.'|'.$commitment->group_id);
        $full = fn (CommitmentGroup $group, ?string $id): bool => $used->get($group->value.'|'.$id, 0) >= SaveCommitment::PER_GROUP;

        $groups = [['type' => CommitmentGroup::Company->value, 'id' => null, 'name' => 'Toda la empresa', 'full' => $full(CommitmentGroup::Company, null)]];

        foreach ([CommitmentGroup::WorkCenter, CommitmentGroup::Area, CommitmentGroup::Manager] as $group) {
            $cut = $group->cut();
            $shown = $cut === null ? [] : array_map(strval(...), array_keys($results->cut($cut)['groups']));
            $names = $cut === null || $shown === [] ? [] : $this->presenter->names($cut, $shown);

            foreach ($shown as $id) {
                $groups[] = ['type' => $group->value, 'id' => $id, 'name' => $names[$id] ?? 'Sin nombre', 'full' => $full($group, $id)];
            }
        }

        return $groups;
    }

    /**
     * What could be done for each driver to act on first.
     *
     * @return list<array{driver: string, label: string, levelLabel: string, actions: list<string>}>
     */
    private function suggestions(Survey $survey): array
    {
        return array_map(function (array $priority): array {
            $driver = Driver::from($priority['driver']);

            return ['driver' => $driver->value, 'label' => $driver->label(), 'levelLabel' => Priority::from($priority['level'])->label(), 'actions' => SuggestedActions::for($driver)];
        }, $survey->priorities ?? []);
    }
}
