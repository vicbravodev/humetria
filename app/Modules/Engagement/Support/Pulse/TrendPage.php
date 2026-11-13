<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Pulse;

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Results\SurveyResults;
use App\Modules\Engagement\Support\Results\Trend;
use App\Modules\Engagement\Support\Results\TrendPoint;
use App\Modules\Engagement\Support\Results\Wave;
use App\Modules\Engagement\Support\Thresholds;
use Carbon\CarbonImmutable;

/**
 * The props of the trend screen: the company across its closed surveys (clima and pulso together), one series for the
 * engagement index, the recommendation index, the improvements seen and the anchor of each of the ten drivers. Company
 * level only, and only what each wave's own minimums let it show: no cut, no count of a group, no day.
 *
 * @phpstan-type Point array{surveyId: string, label: string, value: int, direction: string|null}
 * @phpstan-type Series array{key: string, title: string, hint: string|null, kind: string, points: list<Point>}
 */
final readonly class TrendPage
{
    private const array MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public function __construct(private Thresholds $thresholds) {}

    /**
     * @return list<Wave> the closed surveys, the most recent `$limit`, oldest first
     */
    public function waves(int $limit = Trend::MAX_WAVES): array
    {
        $surveys = Survey::query()->where('status', SurveyStatus::Closed)->orderByDesc('closed_at')->orderByDesc('id')->limit($limit)->get();

        $labels = self::labels(array_values(array_reverse($surveys->all())));

        return array_values(array_reverse(array_map(function (Survey $survey) use ($labels): Wave {
            $company = SurveyResults::for($survey)->company();

            return new Wave($survey->id, $survey->kind, $labels[$survey->id], $survey->items, $company->byKey, $company->recommendation);
        }, $surveys->all())));
    }

    /**
     * The label of each wave by its id: its month, and its order in the month («nov 2026 · Pulso 2») when another closed in
     * the same one, so the columns of the trend never read the same.
     *
     * @param  list<Survey>  $oldestFirst
     * @return array<string, string>
     */
    private static function labels(array $oldestFirst): array
    {
        $months = array_map(fn (Survey $survey): string => self::label($survey->closed_at ?? $survey->updated_at), $oldestFirst);
        $repeats = array_count_values($months);
        $seen = [];
        $labels = [];

        foreach ($oldestFirst as $position => $survey) {
            $month = $months[$position];
            $seen[$month] = ($seen[$month] ?? 0) + 1;
            $labels[$survey->id] = $repeats[$month] > 1 ? "{$month} · {$survey->kind->label()} {$seen[$month]}" : $month;
        }

        return $labels;
    }

    /**
     * @param  list<Wave>  $waves
     * @return array{hasWaves: bool, series: list<Series>, waves: list<array{surveyId: string, label: string}>, latestPulseId: string|null, nextPulseOn: string|null}
     */
    public function props(array $waves): array
    {
        // With fewer than two waves there is nothing to compare yet: the page says when the next pulse goes out.
        $next = count($waves) < 2 ? PulseSchedule::query()->first()?->next_run_on->toDateString() : null;
        $pulses = array_values(array_filter($waves, fn (Wave $wave): bool => $wave->kind === SurveyKind::Pulse));
        $around = ['waves' => array_map(fn (Wave $wave): array => ['surveyId' => $wave->surveyId, 'label' => $wave->label], $waves), 'latestPulseId' => $pulses === [] ? null : $pulses[array_key_last($pulses)]->surveyId, 'nextPulseOn' => $next];

        if ($waves === []) {
            return ['hasWaves' => false, 'series' => [], ...$around];
        }

        $series = [
            ['key' => 'index', 'title' => 'Índice de compromiso', 'hint' => 'Promedio de las tres preguntas de orgullo, motivación y deseo de aportar.', 'kind' => 'percent', 'points' => $this->index($waves)],
            ['key' => 'recommendation', 'title' => 'Índice de recomendación', 'hint' => 'De −100 a 100; solo con 30 respuestas o más en la ola.', 'kind' => 'index', 'points' => $this->points(Trend::recommendation($waves))],
            $this->item($waves, ItemBank::LOOP, 'Mejoras vistas'),
        ];

        foreach (Driver::cases() as $driver) {
            $series[] = $this->item($waves, ItemBank::anchor($driver)->key, $driver->label());
        }

        return ['hasWaves' => true, 'series' => $series, ...$around];
    }

    /**
     * The engagement index across `$waves`, under the same minimums as the trend: the line of the home of the pulse.
     *
     * @param  list<Wave>  $waves
     * @return list<Point>
     */
    public function index(array $waves): array
    {
        return $this->points(Trend::index($waves, $this->thresholds));
    }

    /**
     * @param  list<Wave>  $waves
     * @return Series
     */
    private function item(array $waves, string $key, string $title): array
    {
        return ['key' => $key, 'title' => $title, 'hint' => ItemBank::find($key)->text, 'kind' => 'percent', 'points' => $this->points(Trend::item($waves, $key, $this->thresholds))];
    }

    /**
     * @param  list<TrendPoint>  $points
     * @return list<Point>
     */
    private function points(array $points): array
    {
        return array_map(fn (TrendPoint $point): array => ['surveyId' => $point->surveyId, 'label' => $point->label, 'value' => $point->value, 'direction' => $point->direction?->value], $points);
    }

    /** "dic 2026": the month and the year, never the day. */
    private static function label(CarbonImmutable $closedAt): string
    {
        $local = $closedAt->setTimezone(config()->string('app.display_timezone'));

        return self::MONTHS[$local->month - 1].' '.$local->year;
    }
}
