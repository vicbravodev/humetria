<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Data\RegulationChunk;
use Illuminate\Support\Str;

/**
 * The norms' criteria (config humi.regulations), split by criterion, and a search over them by words. Global and read
 * only: it belongs to no company. ponytail: counts words, no embeddings; add similarity search when the corpus outgrows it.
 */
final class RegulationIndex
{
    private const int MIN_WORD = 4;

    private const int TITLE_WEIGHT = 6;

    private const int BODY_CAP = 5;

    /** Words of a question that say nothing about the topic. */
    private const array STOP = ['cual', 'cuales', 'como', 'para', 'esta', 'este', 'sobre', 'entre', 'donde', 'cuando', 'tiene', 'tienen', 'puedo', 'debo'];

    /**
     * @return list<RegulationChunk>
     */
    public function all(): array
    {
        $chunks = [];

        foreach (config()->array('humi.regulations') as $norm => $path) {
            if (! is_string($path)) {
                continue;
            }

            $document = (string) file_get_contents(base_path($path));
            preg_match_all('/^### (C-\d+)\. (.+?)(?: · (.+))?\R((?s:.*?))(?=^#{2,3} |\z)/m', $document, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $chunks[] = new RegulationChunk((string) $norm, $match[1], $match[2], $match[3], trim($match[4]));
            }
        }

        return $chunks;
    }

    /**
     * The criteria that best answer a question, best first.
     *
     * @return list<RegulationChunk>
     */
    public function search(string $question, int $limit): array
    {
        $words = $this->words($question);

        if ($words === []) {
            return [];
        }

        $scored = [];

        foreach ($this->all() as $position => $chunk) {
            $title = Str::lower(Str::ascii($chunk->title));
            $body = Str::lower(Str::ascii($chunk->text));
            $score = 0;

            foreach ($words as $word) {
                $score += self::TITLE_WEIGHT * substr_count($title, $word) + min(self::BODY_CAP, substr_count($body, $word));
            }

            if ($score > 0) {
                $scored[] = ['score' => $score, 'position' => $position, 'chunk' => $chunk];
            }
        }

        usort($scored, fn (array $left, array $right): int => [$right['score'], $left['position']] <=> [$left['score'], $right['position']]);

        return array_map(fn (array $hit): RegulationChunk => $hit['chunk'], array_slice($scored, 0, $limit));
    }

    /**
     * @return list<string>
     */
    private function words(string $question): array
    {
        $tokens = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($question)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $token): bool => ! in_array($token, self::STOP, true) && (strlen($token) >= self::MIN_WORD || ctype_digit($token)),
        )));
    }
}
