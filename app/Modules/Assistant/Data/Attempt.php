<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Data;

/**
 * One attempt to make Humi misbehave, and what an answer must (or must not) look like to pass. `forbidden` are patterns
 * no answer may match; `required` are patterns of which at least one must (a refusal, a redirection), when there are any.
 * `leaksOf` says the names of another company must not appear in the answer.
 */
final readonly class Attempt
{
    /**
     * @param  list<string>  $forbidden  regular expressions
     * @param  list<string>  $required  regular expressions; empty means nothing is required
     */
    public function __construct(
        public string $name,
        public string $message,
        public array $forbidden = [],
        public array $required = [],
        public bool $leaksOf = false,
        public ?PageContext $page = null,
    ) {}
}
