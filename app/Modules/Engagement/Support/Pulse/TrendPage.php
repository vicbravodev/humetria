<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Pulse;

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\SurveyStatus;
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
     * @return list<Wave> the closed surveys, the most recent twelve, oldest first
     */
    public function waves(): array
    {
        $surveys = Survey::query()->where('status', SurveyStatus::Closed)->orderByDesc('closed_at')->orderByDesc('id')->limit(Trend::MAX_WAVES)->get();

        return array_values(array_reverse(array_map(function (Survey $survey): Wave {
            $company = SurveyResults::for($survey)->company();

            return new Wave($survey->id, $survey->kind, self::label($survey->closed_at ?? $survey->updated_at), $survey->items, $company->byKey, $company->recommendation);
        }, $surveys->all())));
    }

    /**
     * @param  list<Wave>  $waves
     * @return array{hasWaves: bool, series: list<Series>}
     */
    public function props(array $waves): array
    {
        if ($waves === []) {
            return ['hasWaves' => false, 'series' => []];
        }

        $series = [
            ['key' => 'index', 'title' => 'Índice de compromiso', 'hint' => 'Promedio de las tres preguntas de orgullo, motivación y deseo de aportar.', 'kind' => 'percent', 'points' => $this->points(Trend::index($waves, $this->thresholds))],
            ['key' => 'recommendation', 'title' => 'Índice de recomendación', 'hint' => 'De −100 a 100; solo con 30 respuestas o más en la ola.', 'kind' => 'index', 'points' => $this->points(Trend::recommendation($waves))],
            $this->item($waves, ItemBank::LOOP, 'Mejoras vistas'),
        ];

        foreach (Driver::cases() as $driver) {
            $series[] = $this->item($waves, ItemBank::anchor($driver)->key, $driver->label());
        }

        return ['hasWaves' => true, 'series' => $series];
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
