<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

/** Whether a step's fact already happened, and the live sentence of what is going on («Llegaron 3 de 5 respuestas»), if any. */
final readonly class StepState
{
    public function __construct(
        public bool $done,
        public ?string $live = null,
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
            1 => new self(true, $one),
            default => new self(true, "{$count} {$many}"),
        };
    }
}
