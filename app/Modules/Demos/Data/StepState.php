<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

/**
 * Whether a step's fact already happened, and the live sentence of what is going on («Llegaron 3 de 5 respuestas»), if any.
 * What the sentence counts also comes apart, for the guide to use without reading the sentence: the count, out of how many
 * (when it says so) and the noun for that count («respuesta», «respuestas»).
 */
final readonly class StepState
{
    public function __construct(
        public bool $done,
        public ?string $live = null,
        public ?int $count = null,
        public ?int $of = null,
        public ?string $noun = null,
    ) {}

    public static function pending(): self
    {
        return new self(false);
    }

    /** Done from the first one, told live: «1 llave creada», «3 llaves creadas». */
    public static function counted(int $count, string $one, string $many): self
    {
        return match ($count) {
            0 => self::pending(),
            1 => new self(true, $one, 1, noun: str_starts_with($one, '1 ') ? substr($one, 2) : $one),
            default => new self(true, "{$count} {$many}", $count, noun: $many),
        };
    }
}
