<?php

declare(strict_types=1);

use App\Modules\Organization\Enums\SpanFlag;
use App\Modules\Organization\Support\SpanOfControl;

mutates(SpanOfControl::class);

it('marks only the extremes of direct reports for review', function (int $directReports, ?SpanFlag $flag): void {
    expect(SpanOfControl::flag($directReports))->toBe($flag);
})->with([
    'nobody' => [0, null],
    'one' => [1, SpanFlag::Few],
    'two' => [2, SpanFlag::Few],
    'three' => [3, null],
    'fifteen' => [15, null],
    'sixteen' => [16, SpanFlag::Many],
]);

it('names each mark as the panel reads it', function (): void {
    expect(SpanFlag::Few->label())->toBe('Revisar: pocos reportes')
        ->and(SpanFlag::Many->label())->toBe('Revisar: muchos reportes')
        ->and(SpanOfControl::FEW_MAX)->toBe(2)
        ->and(SpanOfControl::MANY_MIN)->toBe(16);
});

it('tells the median of the direct reports', function (array $counts, ?float $median): void {
    expect(SpanOfControl::median($counts))->toBe($median);
})->with([
    'nobody' => [[], null],
    'one' => [[4], 4.0],
    'even' => [[2, 4], 3.0],
    'odd' => [[1, 2, 9], 2.0],
    'unsorted' => [[9, 1, 2, 30], 5.5],
]);
