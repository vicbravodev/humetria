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
     * @param  list<string>  $comments  the comments the themes were drawn from, the valued ones first
     * @param  int|null  $valuedComments  how many of them are valued ones, so each list's counts are capped by its own comments
     * @return array{valued: list<array{theme: string, count: int}>, change: list<array{theme: string, count: int}>}
     *
     * @throws UnsafeSummary
     */
    public static function clean(array $themes, array $comments, int $minSupport, ?int $valuedComments = null): array
    {
        $changeComments = $valuedComments === null ? count($comments) : count($comments) - $valuedComments;
        $valuedComments ??= count($comments);
        $guard = ['windows' => self::windows($comments), 'phrases' => self::phrases($comments), 'names' => self::names($comments)];

        return [
            'valued' => self::list($themes['valued'] ?? null, $guard, $valuedComments, $minSupport),
            'change' => self::list($themes['change'] ?? null, $guard, $changeComments, $minSupport),
        ];
    }

    /**
     * @param  array{windows: array<string, true>, phrases: list<string>, names: array<string, true>}  $guard
     * @return list<array{theme: string, count: int}>
     */
    private static function list(mixed $items, array $guard, int $comments, int $minSupport): array
    {
        if (! is_array($items)) {
            throw new UnsafeSummary('The themes are not a list.');
        }

        $kept = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['theme'] ?? null) || ! is_int($item['count'] ?? null)) {
                throw new UnsafeSummary('A theme is not a text with a count.');
            }

            self::check($item['theme'], $item['count'], $guard, $comments);

            if ($item['count'] >= $minSupport && ! self::namesSomeone($item['theme'], $guard['names'])) {
                $kept[] = ['theme' => $item['theme'], 'count' => $item['count']];
            }
        }

        return $kept;
    }

    /**
     * @param  array{windows: array<string, true>, phrases: list<string>, names: array<string, true>}  $guard
     */
    private static function check(string $theme, int $count, array $guard, int $comments): void
    {
        if (mb_strlen($theme) > self::MAX_LENGTH || $count > $comments || preg_match('/[\d"\'«»“”‘’]/u', $theme) === 1) {
            throw new UnsafeSummary('A theme is too long, quotes, carries a figure or claims too much support.');
        }

        foreach (array_keys(self::windows([$theme])) as $window) {
            if (isset($guard['windows'][$window])) {
                throw new UnsafeSummary('A theme repeats a stretch of a comment.');
            }
        }

        $normalized = ' '.implode(' ', self::words($theme)).' ';
        foreach ($guard['phrases'] as $phrase) {
            if (str_contains($normalized, $phrase)) {
                throw new UnsafeSummary('A theme is or contains a whole comment.');
            }
        }
    }

    /**
     * Each comment as its normalized words between spaces, to find it whole inside a theme.
     *
     * @param  list<string>  $comments
     * @return list<string>
     */
    private static function phrases(array $comments): array
    {
        $phrases = [];
        foreach ($comments as $comment) {
            $words = self::words($comment);
            if ($words !== []) {
                $phrases[] = ' '.implode(' ', $words).' ';
            }
        }

        return $phrases;
    }

    /**
     * Words written with a capital that do not start a sentence: the cheap sign of a name.
     *
     * @param  list<string>  $comments
     * @return array<string, true>
     */
    private static function names(array $comments): array
    {
        $names = [];
        foreach ($comments as $comment) {
            preg_match_all('/(?<![.!?¡¿]\s)(?<=\s)\p{Lu}\p{L}+/u', $comment, $found);
            foreach ($found[0] as $name) {
                $names[implode('', self::words($name))] = true;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, true>  $names
     */
    private static function namesSomeone(string $theme, array $names): bool
    {
        return array_any(self::words($theme), fn (string $word): bool => isset($names[$word]));
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
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
            $words = self::words($text);

            for ($i = 0; $i + self::QUOTE_WORDS <= count($words); $i++) {
                $windows[implode(' ', array_slice($words, $i, self::QUOTE_WORDS))] = true;
            }
        }

        return $windows;
    }
}
