<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\ItemScale;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Audience\Segment;
use App\Modules\Engagement\Support\Bank\Questionnaire;
use App\Modules\Engagement\Support\Thresholds;

/**
 * What the pilot needs to judge each question of the bank, from what is already stored: how many were shown it, how many
 * answered and how many skipped, and the two warnings of a question that does not tell the groups apart (almost everybody
 * agrees, or most stay in the middle). A question with fewer answers than the minimum gives only how many were shown it and
 * skipped it. The abandonment per question is not measured: it would need partial answers stored.
 *
 * @phpstan-type Statistics array{key: string, presented: int, answered: int, skipped: int, favorable: int|null, midpoint: int|null, flags: list<string>}
 */
final readonly class ItemStatistics
{
    /** The share of favorable answers above which a question probably tells nobody apart. */
    private const int FAVORABLE_LIMIT = 90;

    /** The share in the middle of the scale above which a question probably tells nobody apart. */
    private const int MIDPOINT_LIMIT = 40;

    public function __construct(private Thresholds $thresholds) {}

    /**
     * @return list<Statistics>
     */
    public function of(Survey $survey): array
    {
        $rows = array_values(Response::query()->where('survey_id', $survey->id)->get(['answers'])->map(fn (Response $response): ResponseRow => new ResponseRow(new Segment(null, null, null, null), $response->answers))->all());
        $statistics = [];

        foreach (Questionnaire::presentedTo($survey->items, true) as $item) {
            if ($item->scale === ItemScale::Open) {
                continue;
            }

            $presented = 0;
            $skipped = 0;
            foreach ($rows as $row) {
                if (array_key_exists($item->key, $row->answers)) {
                    $presented++;
                    $skipped += $row->answers[$item->key] === null ? 1 : 0;
                }
            }

            // Only the agreement questions have a favorable share and a midpoint; the recommendation one is read as bands.
            $tally = $item->scale === ItemScale::Agreement ? ItemTally::of($item, $rows, $this->thresholds) : null;
            $favorable = $tally?->favorable;
            $midpoint = $tally?->neutral;

            $statistics[] = [
                'key' => $item->key,
                'presented' => $presented,
                'answered' => $presented - $skipped,
                'skipped' => $skipped,
                'favorable' => $favorable,
                'midpoint' => $midpoint,
                'flags' => [
                    ...($favorable !== null && $favorable > self::FAVORABLE_LIMIT ? ['over_90_favorable'] : []),
                    ...($midpoint !== null && $midpoint > self::MIDPOINT_LIMIT ? ['midpoint_over_40'] : []),
                ],
            ];
        }

        return $statistics;
    }

    /**
     * The median and the 90th percentile (nearest rank) of the minutes the people took, once enough of them said.
     *
     * @return array{median: int, p90: int}|null
     */
    public function minutes(Survey $survey): ?array
    {
        $minutes = array_values(array_filter(Response::query()->where('survey_id', $survey->id)->orderBy('minutes')->pluck('minutes')->all(), is_int(...)));

        if (! $this->thresholds->met(count($minutes))) {
            return null;
        }

        return ['median' => $minutes[self::rank($minutes, 50)], 'p90' => $minutes[self::rank($minutes, 90)]];
    }

    /**
     * The index of the value that the percentile `$percent` of a sorted list points at, by nearest rank.
     *
     * @param  list<int>  $sorted
     */
    private static function rank(array $sorted, int $percent): int
    {
        return intdiv($percent * count($sorted) + 99, 100) - 1;
    }
}
