<?php

declare(strict_types=1);

namespace App\Support\Writing;

/**
 * One choice of a question: a single option (radio rows) or several (checkbox rows), with what the board marks at first.
 */
final readonly class Field
{
    /**
     * @param  array<string, string>  $options  value => label, in the order they are shown.
     * @param  string|list<string>|null  $default  What is marked before anyone answers.
     * @param  bool  $shown  Whether its legend is shown, or only read aloud (a question with a single field).
     */
    public function __construct(
        public string $key,
        public string $legend,
        public array $options,
        public bool $multiple = false,
        public string|array|null $default = null,
        public bool $shown = false,
        public int $columns = 1,
    ) {}
}
