<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Campaigns\Contracts\CenterSurveys;
use App\Modules\Campaigns\Data\CenterSurvey;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Results\Contracts\AnswerDistributions;
use App\Modules\Results\Data\AreaCount;
use App\Modules\Results\Data\QuestionDistribution;
use App\Modules\Results\Data\SurveyDistribution;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * What the survey page of a center sends to the browser: where its latest survey stands and, once it is closed, the
 * answers counted per question with the minimum of people per group. Never an answer of someone and never a time.
 */
final readonly class SurveyPages
{
    private const array STATUS_LABELS = ['active' => 'En curso', 'closed' => 'Cerrada'];

    public function __construct(
        private CenterSurveys $surveys,
        private AnswerDistributions $distributions,
        private Instruments $instruments,
        private Areas $areas,
    ) {}

    /**
     * @return array{workCenter: array{id: string, name: string}, survey: array{id: string, status: string, statusLabel: string, closesOn: string|null, participants: int, answered: int}|null, results: array{answered: int, visible: bool, questions: list<array<string, mixed>>, areas: array{visible: list<array{id: string, name: string, answered: int}>, hidden: int}, area: array{value: string, label: string}|null}|null, canLaunch: bool, minGroup: int}
     */
    public function show(WorkCenterSummary $center, ?string $areaId): array
    {
        $survey = $this->surveys->forCenter($center->id)[0] ?? null;
        $closed = $survey !== null && $survey->status === 'closed';

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'survey' => $survey === null ? null : $this->survey($survey),
            'results' => $survey !== null && $closed ? $this->results($this->distributions->of($survey->id, $areaId)) : null,
            'canLaunch' => $survey === null || $closed,
            'minGroup' => config()->integer('results.min_group'),
        ];
    }

    /**
     * @return array{id: string, status: string, statusLabel: string, closesOn: string|null, participants: int, answered: int}
     */
    private function survey(CenterSurvey $survey): array
    {
        return [
            'id' => $survey->id,
            'status' => $survey->status,
            'statusLabel' => self::STATUS_LABELS[$survey->status] ?? $survey->status,
            'closesOn' => $survey->closesOn,
            'participants' => $survey->participants,
            'answered' => $survey->answered,
        ];
    }

    /**
     * @return array{answered: int, visible: bool, questions: list<array<string, mixed>>, areas: array{visible: list<array{id: string, name: string, answered: int}>, hidden: int}, area: array{value: string, label: string}|null}
     */
    private function results(SurveyDistribution $distribution): array
    {
        $names = $this->areas->names(array_map(fn (AreaCount $area): string => $area->areaId, $distribution->areas));
        $texts = $this->texts();

        return [
            'answered' => $distribution->answered,
            'visible' => $distribution->visible,
            'questions' => array_map(fn (QuestionDistribution $question): array => $this->question($question, $texts[$question->number] ?? ''), $distribution->questions),
            'areas' => [
                'visible' => array_map(fn (AreaCount $area): array => ['id' => $area->areaId, 'name' => $names[$area->areaId] ?? '', 'answered' => $area->answered], $distribution->areas),
                'hidden' => $distribution->hiddenAreas,
            ],
            'area' => $distribution->areaId === null ? null : ['value' => $distribution->areaId, 'label' => $names[$distribution->areaId] ?? ''],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function question(QuestionDistribution $question, string $text): array
    {
        $base = ['number' => $question->number, 'code' => sprintf('SS-%02d', $question->number), 'text' => $text, 'visible' => $question->visible];

        return $question->visible ? $base + ['yes' => $question->yes, 'no' => $question->no, 'notApplicable' => $question->notApplicable] : $base;
    }

    /**
     * @return array<int, string> the text of each question, by number
     */
    private function texts(): array
    {
        $texts = [];

        foreach ($this->instruments->definition('seating.survey')->sections as $section) {
            foreach ($section->items as $item) {
                $texts[$item->number] = $item->text;
            }
        }

        return $texts;
    }
}
