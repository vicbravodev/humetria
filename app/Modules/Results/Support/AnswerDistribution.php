<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Responses\Data\AnonymousRecord;
use App\Modules\Results\Contracts\AnswerDistributions;
use App\Modules\Results\Data\AreaCount;
use App\Modules\Results\Data\QuestionDistribution;
use App\Modules\Results\Data\SurveyDistribution;

final readonly class AnswerDistribution implements AnswerDistributions
{
    private const string INSTRUMENT = 'seating.survey';

    public function __construct(private ResponseReader $responses, private Instruments $instruments, private GroupMinimum $minimum) {}

    public function of(string $campaignId, ?string $areaId = null): SurveyDistribution
    {
        $records = $this->responses->anonymous($campaignId, self::INSTRUMENT);
        $sizes = [];

        foreach ($records as $record) {
            $sizes[$record->areaId] = ($sizes[$record->areaId] ?? 0) + 1;
        }

        $suppression = $this->minimum->suppress($sizes);
        $areaId = $areaId !== null && in_array($areaId, $suppression->visible, true) ? $areaId : null;

        if ($areaId !== null) {
            $records = array_values(array_filter($records, fn (AnonymousRecord $record): bool => $record->areaId === $areaId));
        }

        if (! $this->minimum->isShown(count($records))) {
            return new SurveyDistribution(count($records), false, [], [], 0, null);
        }

        return new SurveyDistribution(
            count($records),
            true,
            $this->questions($records),
            array_map(fn (string $id): AreaCount => new AreaCount($id, $sizes[$id]), $suppression->visible),
            $suppression->hidden,
            $areaId,
        );
    }

    /**
     * @param  list<AnonymousRecord>  $records
     * @return list<QuestionDistribution>
     */
    private function questions(array $records): array
    {
        return array_map(function (int $number) use ($records): QuestionDistribution {
            $given = array_values(array_filter(array_map(fn (AnonymousRecord $record): ?string => $record->answers[(string) $number] ?? null, $records)));

            if (! $this->minimum->isShown(count($given))) {
                return new QuestionDistribution($number, false);
            }

            return new QuestionDistribution(
                $number,
                true,
                count(array_keys($given, 'yes', true)),
                count(array_keys($given, 'no', true)),
                count(array_keys($given, 'not_applicable', true)),
            );
        }, $this->instruments->definition(self::INSTRUMENT)->itemNumbers());
    }
}
