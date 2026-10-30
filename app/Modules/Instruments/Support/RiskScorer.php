<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support;

use App\Modules\Instruments\Data\GroupScore;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Instruments\Data\RiskScore;
use App\Modules\Instruments\Support\Nom035\GuideThree;
use App\Modules\Instruments\Support\Nom035\GuideTwo;
use App\Modules\Instruments\Support\Nom035\ScoringTable;
use InvalidArgumentException;

/**
 * Scores one Guía II or III as II.3 and III.3 say: each item 0 to 4 by Tabla 2 or 5, a domain (Cdom) is the sum of its
 * items, a category (Ccat) the sum of its domains, the final score (Cfinal) the sum of every item; each is leveled by
 * its ranges. An item behind a gate answered "no" was not asked and adds nothing (criterio C-12).
 */
final class RiskScorer
{
    private const array SCALE = ['always' => 0, 'almost_always' => 1, 'sometimes' => 2, 'almost_never' => 3, 'never' => 4];

    /**
     * @param  array<array-key, mixed>  $answers  a valid questionnaire of the guide
     */
    public function score(string $key, array $answers): RiskScore
    {
        $table = self::table($key);
        $dimensions = [];
        $domainScores = array_fill_keys(array_keys($table->domains), 0);

        foreach ($table->dimensions as $dimensionKey => $dimension) {
            $score = array_sum(array_map(fn (int $item): int => $this->itemScore($table, $item, $answers[$item] ?? null), $dimension['items']));
            $dimensions[] = ['key' => $dimensionKey, 'label' => $dimension['label'], 'score' => $score];
            $domainScores[$dimension['domain']] += $score;
        }

        $domains = [];
        $categoryScores = array_fill_keys(array_keys($table->categories), 0);

        foreach ($table->domains as $domainKey => $domain) {
            $domains[] = new GroupScore($domainKey, $domain['label'], $domainScores[$domainKey], RiskLevel::fromCutoffs($domainScores[$domainKey], $domain['cutoffs']));
            $categoryScores[$domain['category']] += $domainScores[$domainKey];
        }

        $categories = [];

        foreach ($table->categories as $categoryKey => $category) {
            $categories[] = new GroupScore($categoryKey, $category['label'], $categoryScores[$categoryKey], RiskLevel::fromCutoffs($categoryScores[$categoryKey], $category['cutoffs']));
        }

        $total = array_sum($domainScores);

        return new RiskScore($total, RiskLevel::fromCutoffs($total, $table->final), $categories, $domains, $dimensions);
    }

    /**
     * The level of a score of the guide's total ("final"), one of its categories or one of its domains.
     */
    public static function levelOf(string $key, string $group, int $score): RiskLevel
    {
        $table = self::table($key);
        $cutoffs = $group === 'final'
            ? $table->final
            : ($table->categories[$group]['cutoffs'] ?? $table->domains[$group]['cutoffs'] ?? throw new InvalidArgumentException("Unknown group [{$group}]."));

        return RiskLevel::fromCutoffs($score, $cutoffs);
    }

    private static function table(string $key): ScoringTable
    {
        return match ($key) {
            GuideTwo::KEY => GuideTwo::scoring(),
            GuideThree::KEY => GuideThree::scoring(),
            default => throw new InvalidArgumentException("[{$key}] is not scored by risk level."),
        };
    }

    /** The points of one answer to one item of the guide. */
    public function scoreItem(string $key, int $item, string $answer): int
    {
        return $this->itemScore(self::table($key), $item, $answer);
    }

    private function itemScore(ScoringTable $table, int $item, mixed $answer): int
    {
        if (! is_string($answer) || ! array_key_exists($answer, self::SCALE)) {
            return 0;
        }

        $position = self::SCALE[$answer];

        return in_array($item, $table->zeroOnAlways, true) ? $position : 4 - $position;
    }
}
