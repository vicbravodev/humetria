<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Bank;

use App\Modules\Engagement\Data\BankItem;
use InvalidArgumentException;

/**
 * What a person may send when they answer: every closed question they were shown, with a whole number inside its scale or
 * null when they skipped it, and optional comments on the open ones. A comment is data: it is trimmed and its length
 * checked, never rewritten, escaped or interpreted.
 */
final class AnswerRules
{
    /**
     * @param  list<BankItem>  $presented
     * @param  array<array-key, mixed>  $answers
     * @param  array<array-key, mixed>  $comments
     * @return array{answers: array<string, ?int>, comments: array<string, string>}
     *
     * @throws InvalidArgumentException
     */
    public static function check(array $presented, array $answers, array $comments): array
    {
        $closed = array_filter($presented, fn (BankItem $item): bool => $item->scale->range() !== null);
        $open = array_filter($presented, fn (BankItem $item): bool => $item->scale->range() === null);

        return [
            'answers' => self::answers($closed, $answers),
            'comments' => self::comments(array_map(fn (BankItem $item): string => $item->key, $open), $comments),
        ];
    }

    /**
     * @param  array<int, BankItem>  $closed
     * @param  array<array-key, mixed>  $answers
     * @return array<string, ?int>
     */
    private static function answers(array $closed, array $answers): array
    {
        $checked = [];

        foreach ($closed as $item) {
            if (! array_key_exists($item->key, $answers)) {
                throw new InvalidArgumentException("The answer to [{$item->key}] is missing.");
            }

            $checked[$item->key] = self::answer($item, $answers[$item->key]);
        }

        if (array_diff(array_map(strval(...), array_keys($answers)), array_keys($checked)) !== []) {
            throw new InvalidArgumentException('An answer is for a question that was not presented.');
        }

        return $checked;
    }

    private static function answer(BankItem $item, mixed $value): ?int
    {
        $range = $item->scale->range();

        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $range === null || $value < $range[0] || $value > $range[1]) {
            throw new InvalidArgumentException("The answer to [{$item->key}] is outside its scale.");
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $openKeys
     * @param  array<array-key, mixed>  $comments
     * @return array<string, string>
     */
    private static function comments(array $openKeys, array $comments): array
    {
        $checked = [];

        foreach ($comments as $key => $comment) {
            if (! in_array($key, $openKeys, true)) {
                throw new InvalidArgumentException('A comment is for a question that is not open or was not presented.');
            }

            $text = self::trimmed($comment);

            if ($text !== '') {
                $checked[(string) $key] = $text;
            }
        }

        return $checked;
    }

    private static function trimmed(mixed $comment): string
    {
        $text = is_string($comment) ? preg_replace('/^\s+|\s+$/u', '', $comment) : null;

        if ($text === null || mb_strlen($text) > config()->integer('engagement.comment_max_length')) {
            throw new InvalidArgumentException('A comment is not text or is too long.');
        }

        return $text;
    }
}
