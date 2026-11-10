<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Enums\CutAttribute;
use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Enums\TenureBand;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Tenancy\Contracts\WorkCenters;

/**
 * How far a survey has come, for RH: only counts of people, never who. The company as a whole, and by work center, area
 * and tenure range, one cut at a time. The groups are the ones the minimum lets show, counted by who was invited (the
 * people who fall in a group too small are left out, and so is the group of those reported with the whole company),
 * so nothing here lets a small group or a single person be worked out.
 *
 * @phpstan-type Group array{label: string, invited: int, answered: int}
 * @phpstan-type Cut array{key: string, title: string, groups: list<Group>}
 * @phpstan-type Totals array{invited: int, answered: int, rate: int|null, pending: int, withoutLink: int, canRemind: bool, minGroup: int, cuts: list<Cut>}
 */
final readonly class Participation
{
    /** The cuts of the panel, in the order they are read. The manager groups are the manager feedback's to show. */
    private const array CUTS = [CutAttribute::WorkCenter, CutAttribute::Area, CutAttribute::Tenure];

    public function __construct(private Thresholds $thresholds, private WorkCenters $workCenters, private Areas $areas) {}

    /**
     * @return Totals
     */
    public function of(Survey $survey, bool $canManage): array
    {
        $counts = Invitation::query()->toBase()->where('survey_id', $survey->id)->selectRaw('count(*) filter (where answered) as answered, count(*) filter (where not answered and not cancelled) as pending, count(*) filter (where invitation_status = ?) as without_link', [InvitationStatus::NoEmail->value])->first();
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
