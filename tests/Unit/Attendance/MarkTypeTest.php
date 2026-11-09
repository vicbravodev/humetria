<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;

mutates(MarkType::class);

it('allows what may follow the last mark: the one place the sequence is written', function (?MarkType $last, array $next): void {
    expect(MarkType::allowedAfter($last))->toBe($next);
})->with([
    'nothing yet' => [null, [MarkType::Start]],
    'after a start' => [MarkType::Start, [MarkType::BreakStart, MarkType::End]],
    'during a break (the day may end in it)' => [MarkType::BreakStart, [MarkType::BreakEnd, MarkType::End]],
    'after a break' => [MarkType::BreakEnd, [MarkType::BreakStart, MarkType::End]],
    'after the end' => [MarkType::End, [MarkType::Start]],
]);
