<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Organization\Contracts\Roster;

/**
 * Masks the name of a person of the company in what someone typed, before it leaves for the provider and before it is
 * kept: the full name, or the first name with the first surname, in any case and with or without accents. A first name or
 * a surname alone stays (too many words are also names), and so does a one-word name. PersonalDataRedactor masks what has
 * a shape (CURP, email); a name has none, so this one knows the company's people.
 *
 * It reads the text word by word against a set of the names, not with one pattern of every name: a company of a few
 * hundred people already makes a pattern too large for PCRE to compile.
 */
final readonly class PersonNamesRedactor
{
    private const array PLAIN = ['á' => 'a', 'à' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'ü' => 'u'];

    public function __construct(private Roster $roster) {}

    public function redact(string $text): string
    {
        [$known, $longest] = $this->forms();

        if ($known === [] || preg_match_all('/[\p{L}\p{N}]+/u', $text, $found, PREG_OFFSET_CAPTURE) === 0) {
            return $text;
        }

        /** @var list<array{string, int}> $words */
        $words = $found[0];
        $spans = [];

        for ($start = 0, $count = count($words); $start < $count; $start++) {
            $end = $this->longestNameFrom($text, $words, $start, $known, $longest);

            if ($end !== null) {
                $spans[] = [$words[$start][1], $words[$end][1] + strlen($words[$end][0])];
                $start = $end;
            }
        }

        foreach (array_reverse($spans) as [$from, $to]) {
            $text = substr_replace($text, '[persona]', $from, $to - $from);
        }

        return $text;
    }

    /**
     * The last word of the longest name that starts at a word, or null when none does. Its words are only separated by
     * spaces: a name split by a comma or a full stop is two things.
     *
     * @param  list<array{string, int}>  $words
     * @param  array<string, true>  $known
     */
    private function longestNameFrom(string $text, array $words, int $start, array $known, int $longest): ?int
    {
        $last = min($start + $longest - 1, count($words) - 1);
        $reach = $start;

        while ($reach < $last && $this->onlySpaces($text, $words[$reach], $words[$reach + 1])) {
            $reach++;
        }

        for ($end = $reach; $end > $start; $end--) {
            $name = implode(' ', array_map(fn (array $word): string => $this->plain($word[0]), array_slice($words, $start, $end - $start + 1)));

            if (isset($known[$name])) {
                return $end;
            }
        }

        return null;
    }

    /**
     * Whether only whitespace lies between two words of the text.
     *
     * @param  array{string, int}  $before
     * @param  array{string, int}  $after
     */
    private function onlySpaces(string $text, array $before, array $after): bool
    {
        $from = $before[1] + strlen($before[0]);

        return preg_match('/^\s+$/u', substr($text, $from, $after[1] - $from)) === 1;
    }

    /**
     * Every name the company's people can be called by, as the words of a plain, lowercase form joined by a space, and the
     * most words any of them has.
     *
     * @return array{array<string, true>, int}
     */
    private function forms(): array
    {
        $known = [];
        $longest = 0;

        foreach ($this->roster->names() as $name) {
            $words = array_map($this->plain(...), preg_split('/[^\p{L}\p{N}]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: []);

            if (count($words) < 2) {
                continue;
            }

            $known[implode(' ', $words)] = true;
            $known[implode(' ', array_slice($words, 0, 2))] = true;
            $longest = max($longest, count($words));
        }

        return [$known, $longest];
    }

    /** A word in lowercase and without accents (the ñ stays: it is another letter). */
    private function plain(string $word): string
    {
        return strtr(mb_strtolower($word), self::PLAIN);
    }
}
