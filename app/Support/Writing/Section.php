<?php

declare(strict_types=1);

namespace App\Support\Writing;

/**
 * One section of a written document: the text the answers make (null while its question is not answered), or a base text
 * that needs no question.
 */
final readonly class Section
{
    public function __construct(public string $key, public string $heading, public ?string $text, public bool $base = false) {}
}
