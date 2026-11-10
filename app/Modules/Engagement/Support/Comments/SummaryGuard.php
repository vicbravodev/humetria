<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Comments;

use App\Modules\Engagement\Exceptions\UnsafeSummary;
use Illuminate\Support\Str;

/**
 * What the model says about the comments is checked in code before anyone reads it: a theme that repeats a stretch of a
 * comment, carries a figure or a quote, is too long or claims more support than there are comments is not a summary but
 * a leak, and the whole answer is refused. Themes that few people mention are dropped, because a theme backed by one or
 * two comments points at them.
 */
final class SummaryGuard
{
    /** Words in a row a theme may share with a comment before it counts as quoting it. */
    private const int QUOTE_WORDS = 5;

    private const int MAX_LENGTH = 160;

    /**
     * @param  array<array-key, mixed>  $themes  `{valued: [{theme, count}], change: [{theme, count}]}`
     * @param  list<string>  $comments  the comments the themes were drawn from
     * @return array{valued: list<array{theme: string, count: int}>, change: list<array{theme: string, count: int}>}
     *
     * @throws UnsafeSummary
     */
    public static function clean(array $themes, array $comments, int $minSupport): array
    {
        $windows = self::windows($comments);

        return [
            'valued' => self::list($themes['valued'] ?? null, $windows, count($comments), $minSupport),
            'change' => self::list($themes['change'] ?? null, $windows, count($comments), $minSupport),
        ];
    }

    /**
     * @param  array<string, true>  $windows
     * @return list<array{theme: string, count: int}>
     */
    private static function list(mixed $items, array $windows, int $comments, int $minSupport): array
    {
        if (! is_array($items)) {
            throw new UnsafeSummary('The themes are not a list.');
        }

        $kept = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['theme'] ?? null) || ! is_int($item['count'] ?? null)) {
                throw new UnsafeSummary('A theme is not a text with a count.');
            }

            self::check($item['theme'], $item['count'], $windows, $comments);

            if ($item['count'] >= $minSupport) {
                $kept[] = ['theme' => $item['theme'], 'count' => $item['count']];
            }
        }

        return $kept;
    }

    /**
     * @param  array<string, true>  $windows
     */
    private static function check(string $theme, int $count, array $windows, int $comments): void
    {
        if (mb_strlen($theme) > self::MAX_LENGTH || $count > $comments || preg_match('/[\d"\'«»“”‘’]/u', $theme) === 1) {
            throw new UnsafeSummary('A theme is too long, quotes, carries a figure or claims too much support.');
        }

        foreach (array_keys(self::windows([$theme])) as $window) {
            if (isset($windows[$window])) {
                throw new UnsafeSummary('A theme repeats a stretch of a comment.');
            }
        }
    }

    /**
     * Every run of QUOTE_WORDS words of the texts, with no case, accents or punctuation.
     *
     * @param  list<string>  $texts
     * @return array<string, true>
     */
    private static function windows(array $texts): array
    {
        $windows = [];
        foreach ($texts as $text) {
            $words = preg_split('/[^a-z0-9]+/', mb_strtolower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY);
            $words = $words === false ? [] : $words;

            for ($i = 0; $i + self::QUOTE_WORDS <= count($words); $i++) {
                $windows[implode(' ', array_slice($words, $i, self::QUOTE_WORDS))] = true;
            }
        }

        return $windows;
    }
}
