<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Support\Allocation;
use App\Modules\Leave\Support\AllocationLine;
use App\Modules\Leave\Support\Allocator;
use App\Modules\Leave\Support\PeriodBalance;
use App\Modules\Leave\Support\PeriodWindow;
use Carbon\CarbonImmutable;

mutates(Allocator::class, Allocation::class, PeriodBalance::class);

function period(string $id, string $anniversary, int $statutory, int $extra): PeriodBalance
{
    $day = CarbonImmutable::parse($anniversary);
    $window = PeriodWindow::of($day);

    return new PeriodBalance($id, $day, $window->enjoyBy, $window->prescribesOn, $statutory, $extra);
}

/**
 * @return list<array{string, string, int}>
 */
function lines(Allocation $allocation): array
{
    return array_map(fn (AllocationLine $line): array => [$line->entitlementId, $line->tranche->value, $line->days], $allocation->lines);
}

// Criterio C-16: the oldest period first and, within it, the statutory days before the extra ones.
it('takes the days from the oldest period first, statutory before extra', function (): void {
    $allocation = Allocator::allocate([period('B', '2026-03-15', 14, 0), period('A', '2025-03-15', 3, 2)], 7);

    expect(lines($allocation))->toBe([['A', 'statutory', 3], ['A', 'extra', 2], ['B', 'statutory', 2]])
        ->and($allocation->shortBy)->toBe(0)
        ->and($allocation->covered())->toBeTrue();
});

it('stops at the period that covers the request', function (int $days, array $expected): void {
    $allocation = Allocator::allocate([period('B', '2026-03-15', 14, 0), period('A', '2025-03-15', 3, 2)], $days);

    expect(lines($allocation))->toBe($expected)
        ->and($allocation->covered())->toBeTrue();
})->with([
    'part of the statutory days' => [2, [['A', 'statutory', 2]]],
    'exactly the statutory days' => [3, [['A', 'statutory', 3]]],
    'into the extra days' => [4, [['A', 'statutory', 3], ['A', 'extra', 1]]],
    'exactly the oldest period' => [5, [['A', 'statutory', 3], ['A', 'extra', 2]]],
    'everything there is' => [19, [['A', 'statutory', 3], ['A', 'extra', 2], ['B', 'statutory', 14]]],
]);

it('says how many days are missing and still hands out all there is', function (): void {
    $allocation = Allocator::allocate([period('B', '2026-03-15', 14, 0), period('A', '2025-03-15', 3, 2)], 25);

    expect(lines($allocation))->toBe([['A', 'statutory', 3], ['A', 'extra', 2], ['B', 'statutory', 14]])
        ->and($allocation->shortBy)->toBe(6)
        ->and($allocation->covered())->toBeFalse();
});

// Criterios C-11 and C-16: what can prescribe is still the person's, and it goes first.
it('does not skip a period that can already prescribe', function (): void {
    $old = period('OLD', '2023-03-15', 4, 0);

    expect($old->prescribesOn->toDateString())->toBe('2024-09-15')
        ->and(lines(Allocator::allocate([period('NEW', '2026-03-15', 14, 0), $old], 5)))->toBe([['OLD', 'statutory', 4], ['NEW', 'statutory', 1]]);
});

it('draws nothing from a balance at zero or below it', function (): void {
    $allocation = Allocator::allocate([period('A', '2024-03-15', 0, -1), period('B', '2025-03-15', -2, 3), period('C', '2026-03-15', 0, 0)], 5);

    expect(lines($allocation))->toBe([['B', 'extra', 3]])
        ->and($allocation->shortBy)->toBe(2);
});

it('is short by the whole request with no periods', function (): void {
    $allocation = Allocator::allocate([], 4);

    expect($allocation->lines)->toBe([])
        ->and($allocation->shortBy)->toBe(4)
        ->and($allocation->covered())->toBeFalse();
});

it('refuses a request of less than one day', function (int $days): void {
    expect(fn () => Allocator::allocate([period('A', '2025-03-15', 3, 2)], $days))->toThrow(InvalidArgumentException::class);
})->with([0, -1]);

it('adds up what is left of a period', function (): void {
    expect(period('A', '2025-03-15', 3, 2)->left())->toBe(5)
        ->and(period('A', '2025-03-15', 0, 0)->left())->toBe(0);
});

it('names the two tranches of a period', function (): void {
    expect(Tranche::Statutory->value)->toBe('statutory')
        ->and(Tranche::Extra->value)->toBe('extra');
});
