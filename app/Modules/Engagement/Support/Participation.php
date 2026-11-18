<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Enums\CutAttribute;
use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Enums\TenureBand;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Messaging\Contracts\DeliverySummary;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Support\SmallCells;

/**
 * How far a survey has come, for RH: only counts of people, never who. The company as a whole, and by work center, area
 * and tenure range, one cut at a time. The groups are the ones the minimum lets show, counted by who was invited (the
 * people who fall in a group too small are left out, and so is the group of those reported with the whole company),
 * so nothing here lets a small group or a single person be worked out.
 *
 * @phpstan-type Group array{label: string, invited: int, answered: int}
 * @phpstan-type Cut array{key: string, title: string, groups: list<Group>}
 * @phpstan-type Reach array{channels: list<array{channel: string, total: int|null, delivered: int|null, undelivered: int|null, undeliveredBelowMinimum: bool}>, answeredByChannel: list<array{channel: string, answered: int, unanswered: int}>|null, minGroup: int}
 * @phpstan-type Totals array{invited: int, answered: int, rate: int|null, pending: int, withoutLink: int, canRemind: bool, minGroup: int, cuts: list<Cut>, reach: Reach}
 */
final readonly class Participation
{
    /** The cuts of the panel, in the order they are read. The manager groups are the manager feedback's to show. */
    private const array CUTS = [CutAttribute::WorkCenter, CutAttribute::Area, CutAttribute::Tenure];

    public function __construct(private Thresholds $thresholds, private WorkCenters $workCenters, private Areas $areas, private DeliverySummary $deliveries) {}

    /**
     * @return Totals
     */
    public function of(Survey $survey, bool $canManage): array
    {
        $counts = Invitation::query()->toBase()->where('survey_id', $survey->id)->selectRaw('count(*) filter (where answered) as answered, count(*) filter (where not answered and not cancelled) as pending, count(*) filter (where invitation_status = ?) as without_link', [InvitationStatus::Unreachable->value])->first();
        $invited = $survey->invited ?? 0;
        $answered = self::int($counts->answered ?? 0);
        $pending = self::int($counts->pending ?? 0);

        return [
            'invited' => $invited,
            'answered' => $answered,
            'rate' => $invited === 0 ? null : (int) round($answered * 100 / $invited),
            'pending' => $pending,
            'withoutLink' => self::int($counts->without_link ?? 0),
            'canRemind' => $canManage && $survey->status === SurveyStatus::Active && $pending > 0,
            'minGroup' => config()->integer('results.min_group'),
            'cuts' => array_map(fn (CutAttribute $cut): array => $this->cut($survey, $cut), self::CUTS),
            'reach' => $this->reach($survey),
        ];
    }

    /**
     * How the messages went and who answered, by channel: aggregates under the minimum of 5, never a person.
     *
     * @return Reach
     */
    private function reach(Survey $survey): array
    {
        $rows = Invitation::query()->toBase()->where('survey_id', $survey->id)->whereNotNull('invited_via')->groupBy('invited_via')
            ->selectRaw('invited_via, count(*) filter (where answered) as answered, count(*) filter (where not answered) as unanswered')->get();
        $cells = [];

        foreach ($rows as $row) {
            if (is_string($row->invited_via)) {
                $cells[$row->invited_via] = ['answered' => self::int($row->answered), 'unanswered' => self::int($row->unanswered)];
            }
        }

        return [
            'channels' => array_map(fn ($tally): array => $tally->toArray(), $this->deliveries->forSource(InvitationReference::ENGAGEMENT_SURVEY, $survey->id)),
            'answeredByChannel' => SmallCells::answeredByChannel($cells, config()->integer('results.min_group')),
            'minGroup' => config()->integer('results.min_group'),
        ];
    }

    /**
     * @return Cut
     */
    private function cut(Survey $survey, CutAttribute $cut): array
    {
        $column = self::column($cut);
        $rows = Invitation::query()->toBase()->where('survey_id', $survey->id)->select($column)->selectRaw('count(*) as invited, count(*) filter (where answered) as answered')->groupBy($column)->orderBy($column)->get();
        $invited = [];
        $answered = [];

        foreach ($rows as $row) {
            $key = is_string($row->{$column}) ? $row->{$column} : '';
            $invited[$key] = self::int($row->invited);
            $answered[$key] = self::int($row->answered);
        }

        // Whoever is in no group (reported with the whole company) is hidden too, and counts as such for the minimum.
        $visible = array_values(array_filter($this->thresholds->visible($invited), fn (string $key): bool => $key !== ''));
        $labels = $this->labels($cut, $visible);

        return [
            'key' => $cut->value,
            'title' => self::title($cut),
            'groups' => array_map(fn (string $key): array => ['label' => $labels[$key] ?? $key, 'invited' => $invited[$key] ?? 0, 'answered' => $answered[$key] ?? 0], $visible),
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function labels(CutAttribute $cut, array $keys): array
    {
        return match ($cut) {
            CutAttribute::WorkCenter => array_column(array_map(fn ($center): array => [$center->id, $center->name], $this->workCenters->all()), 1, 0),
            CutAttribute::Area => $this->areas->names($keys),
            CutAttribute::Tenure => array_combine($keys, array_map(fn (string $key): string => TenureBand::tryFrom($key)?->label() ?? $key, $keys)),
            CutAttribute::Manager => [],
        };
    }

    private static function column(CutAttribute $cut): string
    {
        return match ($cut) {
            CutAttribute::WorkCenter => 'work_center_id',
            CutAttribute::Area => 'area_id',
            CutAttribute::Tenure => 'tenure_band',
            CutAttribute::Manager => 'manager_group_id',
        };
    }

    private static function title(CutAttribute $cut): string
    {
        return match ($cut) {
            CutAttribute::WorkCenter => 'Por centro de trabajo',
            CutAttribute::Area => 'Por área',
            CutAttribute::Tenure => 'Por antigüedad',
            CutAttribute::Manager => 'Por equipo',
        };
    }

    private static function int(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}
