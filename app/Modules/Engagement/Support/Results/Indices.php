<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Thresholds;

/** The two headline numbers of a survey: the engagement index and the recommendation index. */
final class Indices
{
    /**
     * The average favorable share of the engagement questions; none if any of them is hidden or missing.
     *
     * @param  array<string, ItemResult>  $byKey
     */
    public static function engagement(array $byKey): ?int
    {
        $favorable = [];
        foreach (ItemBank::INDEX as $key) {
            $value = ($byKey[$key] ?? null)?->favorable;
            if ($value === null) {
                return null;
            }
            $favorable[] = $value;
        }

        return (int) round(array_sum($favorable) / count($favorable));
    }

    /** @param  list<ResponseRow>  $rows */
    public static function recommendation(array $rows, Thresholds $thresholds): RecommendationResult
    {
        $detractors = $passives = $promoters = 0;
        foreach ($rows as $row) {
            $score = $row->answers[ItemBank::RECOMMEND] ?? null;
            match (true) {
                $score === null => null,
                $score <= 6 => $detractors++,
                $score <= 8 => $passives++,
                default => $promoters++,
            };
        }

        $answered = $detractors + $passives + $promoters;
        if (! $thresholds->met($answered)) {
            return new RecommendationResult(false, null, null);
        }

        return new RecommendationResult(
            true,
            $thresholds->trend($answered) ? (int) round(100 * ($promoters - $detractors) / $answered) : null,
            [
                'detractors' => (int) round(100 * $detractors / $answered),
                'passives' => (int) round(100 * $passives / $answered),
                'promoters' => (int) round(100 * $promoters / $answered),
            ],
        );
    }
}
