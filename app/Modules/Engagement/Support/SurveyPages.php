<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Actions\LaunchSurvey;
use App\Modules\Engagement\Actions\SnapshotAudience;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\Questionnaire;
use App\Modules\Organization\Data\RosterEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * What the panel of Clima y pulso shows RH: the survey in progress, the history, and each survey in its state. Only
 * counts: nothing here says who answered, and nothing that follows an answer to a person.
 *
 * @phpstan-type Card array{id: string, name: string, status: string, statusLabel: string, closesOn: string|null, daysLeft: int|null, invited: int|null, answered: int|null, rate: int|null}
 * @phpstan-type Draft array{people: int, withoutEmail: int, companyOnly: int, minGroup: int, canLaunch: bool, limits: array{min: string, suggested: string, max: string}}
 * @phpstan-type Unreachable array{id: string, name: string, area: string}
 * @phpstan-type Section array{title: string, items: list<array{text: string}>}
 * @phpstan-type HistoryRow array{id: string, name: string, closedOn: string|null, invited: int|null, answered: int, rate: int|null}
 */
final readonly class SurveyPages
{
    public const int PER_PAGE = 10;

    public function __construct(private SnapshotAudience $snapshot) {}

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
     * The survey in its state. A draft shows what RH reviews before launching; the rest is what each state adds.
     *
     * @return array{survey: Card, canManage: bool, draft: Draft|null, unreachable: LengthAwarePaginator<int, Unreachable>|null, sections: list<Section>, participation: null}
     */
    public function show(Survey $survey, bool $canManage): array
    {
        $draft = $survey->status === SurveyStatus::Draft;
        $people = $draft ? $this->snapshot->invitees() : [];
        $unreached = array_values(array_filter($people, fn (RosterEntry $person): bool => ($person->email ?? '') === ''));

        return [
            'survey' => $this->card($survey),
            'canManage' => $canManage,
            'draft' => $draft ? [
                'people' => count($people),
                'withoutEmail' => count($unreached),
                'companyOnly' => $this->snapshot->companyOnly(),
                'minGroup' => config()->integer('results.min_group'),
                'canLaunch' => $canManage,
                'limits' => self::limits(),
            ] : null,
            'unreachable' => $draft ? self::unreachablePage($unreached) : null,
            'sections' => $draft ? self::sections($survey) : [],
            'participation' => null,
        ];
    }

    /**
     * The first and the last day a survey may close and the one suggested: tomorrow, sixty days ahead, in two weeks.
     *
     * @return array{min: string, suggested: string, max: string}
     */
    private static function limits(): array
    {
        $today = SurveyClock::day();

        return [
            'min' => $today->addDay()->toDateString(),
            'suggested' => $today->addDays(14)->toDateString(),
            'max' => $today->addDays(LaunchSurvey::MAX_DAYS)->toDateString(),
        ];
    }

    /**
     * The people who have no email, by name, a page at a time: they will not receive their link.
     *
     * @param  list<RosterEntry>  $people
     * @return LengthAwarePaginator<int, Unreachable>
     */
    private static function unreachablePage(array $people): LengthAwarePaginator
    {
        $sorted = collect($people)->sortBy(fn (RosterEntry $person): string => mb_strtolower($person->name), SORT_NATURAL)->values();
        $page = Paginator::resolveCurrentPage('unreachable');

        return (new LengthAwarePaginator(
            $sorted->forPage($page, self::PER_PAGE)->map(fn (RosterEntry $person): array => ['id' => $person->id, 'name' => $person->name, 'area' => $person->areaName])->values(),
            $sorted->count(),
            self::PER_PAGE,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'unreachable'],
        ))->withQueryString();
    }

    /**
     * The questions of the survey as the first person with a manager reads them, by section: the commitment, each
     * driver of the company, the manager and the closing.
     *
     * @return list<Section>
     */
    private static function sections(Survey $survey): array
    {
        $sections = [];

        foreach (Questionnaire::presentedTo($survey->items, true) as $item) {
            $title = match ($item->block) {
                ItemBlock::Outcome => 'Compromiso',
                ItemBlock::Company => $item->driver?->label() ?? 'Su empresa',
                ItemBlock::Manager => 'Su jefe directo',
                ItemBlock::Closing => 'Cierre',
            };
            $sections[$title]['title'] = $title;
            $sections[$title]['items'][] = ['text' => $item->text];
        }

        return array_values($sections);
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
