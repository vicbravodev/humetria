<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * What the panel of Clima y pulso shows RH: the survey in progress, the history, and each survey in its state. Only
 * counts: nothing here says who answered, and nothing that follows an answer to a person.
 *
 * @phpstan-type Card array{id: string, name: string, status: string, statusLabel: string, closesOn: string|null, daysLeft: int|null, invited: int|null, answered: int|null, rate: int|null}
 * @phpstan-type HistoryRow array{id: string, name: string, closedOn: string|null, invited: int|null, answered: int, rate: int|null}
 */
final readonly class SurveyPages
{
    public const int PER_PAGE = 10;

    /**
     * @return array{current: Card|null, surveys: LengthAwarePaginator<int, HistoryRow>, canManage: bool}
     */
    public function index(SurveyKind $kind, bool $canManage): array
    {
        $current = Survey::query()->where('kind', $kind)->whereIn('status', [SurveyStatus::Draft, SurveyStatus::Active])->first();
        $closed = Survey::query()->where('kind', $kind)->where('status', SurveyStatus::Closed)->orderByDesc('closed_at')->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
        $answered = self::answeredBySurvey(array_values($closed->getCollection()->map(fn (Survey $survey): string => $survey->id)->all()));

        return [
            'current' => $current === null ? null : $this->card($current),
            'surveys' => $closed->through(fn (Survey $survey): array => [
                'id' => $survey->id,
                'name' => $survey->name,
                'closedOn' => $survey->closed_at?->setTimezone(config()->string('app.display_timezone'))->toDateString(),
                'invited' => $survey->invited,
                'answered' => self::countOf($answered, $survey->id),
                'rate' => self::rate(self::countOf($answered, $survey->id), $survey->invited),
            ]),
            'canManage' => $canManage,
        ];
    }

    /**
     * @return array{survey: Card, canManage: bool}
     */
    public function show(Survey $survey, bool $canManage): array
    {
        return ['survey' => $this->card($survey), 'canManage' => $canManage];
    }

    /**
     * @return Card
     */
    private function card(Survey $survey): array
    {
        $launched = $survey->status !== SurveyStatus::Draft;
        $answered = $launched ? self::countOf(self::answeredBySurvey([$survey->id]), $survey->id) : null;

        return [
            'id' => $survey->id,
            'name' => $survey->name,
            'status' => $survey->status->value,
            'statusLabel' => $survey->status->label(),
            'closesOn' => $survey->closes_on?->toDateString(),
            'daysLeft' => $survey->status === SurveyStatus::Active && $survey->closes_on !== null ? SurveyClock::daysToAnswer($survey->closes_on) : null,
            'invited' => $launched ? $survey->invited : null,
            'answered' => $answered,
            'rate' => $answered === null ? null : self::rate($answered, $survey->invited),
        ];
    }

    /**
     * How many people answered each survey: a count, never who.
     *
     * @param  list<string>  $surveyIds
     * @return Collection<array-key, mixed> the count by survey id
     */
    private static function answeredBySurvey(array $surveyIds): Collection
    {
        return Invitation::query()->toBase()->whereIn('survey_id', $surveyIds)->where('answered', true)->select('survey_id')->selectRaw('count(*) as total')->groupBy('survey_id')->orderBy('survey_id')->pluck('total', 'survey_id');
    }

    /**
     * @param  Collection<array-key, mixed>  $counts
     */
    private static function countOf(Collection $counts, string $surveyId): int
    {
        return is_int($total = $counts->get($surveyId)) ? $total : 0;
    }

    private static function rate(int $answered, ?int $invited): ?int
    {
        return $invited === null || $invited === 0 ? null : (int) round($answered * 100 / $invited);
    }
}
