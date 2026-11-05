<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support\Writing;

/** A list written as a sentence: its first letter up and a period at its end; null while there is nothing to say. */
final class Sentence
{
    public static function of(?string $list): ?string
    {
        return $list === null ? null : mb_strtoupper(mb_substr($list, 0, 1)).mb_substr($list, 1).'.';
    }
}
