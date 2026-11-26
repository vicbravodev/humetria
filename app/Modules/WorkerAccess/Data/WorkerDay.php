<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Data;

/**
 * The person's day as their start shows it, from the tool that keeps it: where they work, the marks they made (type, the
 * time of where it was taken and the instant, in milliseconds) and what may come next. Their own, never anyone else's.
 */
final readonly class WorkerDay
{
    /**
     * @param  list<array{type: string, time: string, at: int}>  $marks
     * @param  list<string>  $next
     */
    public function __construct(
        public string $center,
        public array $marks,
        public array $next,
    ) {}
}
