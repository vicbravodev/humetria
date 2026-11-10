<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Support\PolicyTerms;
use Carbon\CarbonImmutable;

mutates(PolicyTerms::class);

function corporateTerms(int $fixedDays = 1): PolicyTerms
{
    return new PolicyTerms('policy-1', 'Corporativo', 30, WorkWeek::MonSat, true, 12, 5, [['from' => 1, 'to' => 5, 'days' => 20]], $fixedDays);
}

it('splits the days of a year into what the law gives and what the policy adds', function (int $years, array $expected): void {
    expect(corporateTerms()->daysFor($years, CarbonImmutable::parse('2026-06-01')))->toBe($expected);
})->with([
    'first year: the tier over the law, plus the fixed day' => [1, ['statutory' => 12, 'extra' => 9]],
    'last year of the tier: only the fixed day' => [5, ['statutory' => 20, 'extra' => 1]],
    'a year no tier covers: the law plus the fixed day' => [6, ['statutory' => 22, 'extra' => 1]],
]);

it('never takes days away when a tier gives fewer than the law of that anniversary', function (): void {
    // A tier of 12 days for years 1 to 3 would be under the law of year 3 (16): the extra stays at zero.
    $terms = new PolicyTerms('policy-1', 'Corta', 25, WorkWeek::MonFri, false, null, null, [['from' => 1, 'to' => 3, 'days' => 12]], 0);

    expect($terms->daysFor(3, CarbonImmutable::parse('2026-06-01')))->toBe(['statutory' => 16, 'extra' => 0]);
});

it('picks the tier that covers the year, at both of its ends', function (int $years, int $extra): void {
    $terms = new PolicyTerms('policy-1', 'Escalonada', 25, WorkWeek::MonFri, false, null, null, [['from' => 6, 'to' => 10, 'days' => 30], ['from' => 1, 'to' => 5, 'days' => 20]], 0);

    expect($terms->daysFor($years, CarbonImmutable::parse('2026-06-01'))['extra'])->toBe($extra);
})->with([[5, 0], [6, 8], [10, 8], [11, 0]]);

it('counts the statutory part with the table of the anniversary', function (): void {
    // Before 2023-01-01 the first year gave 6 days (criterio C-02): the tier of 20 adds 14, plus the fixed day.
    expect(corporateTerms()->daysFor(1, CarbonImmutable::parse('2022-12-31')))->toBe(['statutory' => 6, 'extra' => 15]);
});

it('stands for the law when no policy applies', function (): void {
    $law = PolicyTerms::statutory(WorkWeek::MonSat);

    expect($law->policyId)->toBeNull()
        ->and($law->name)->toBe('Ley')
        ->and($law->premiumPercent)->toBe(25)
        ->and($law->workWeek)->toBe(WorkWeek::MonSat)
        ->and($law->allowsAdvance)->toBeFalse()
        ->and($law->extraExpiresMonths)->toBeNull()
        ->and($law->carryoverCap)->toBeNull()
        ->and($law->daysFor(1, CarbonImmutable::parse('2026-06-01')))->toBe(['statutory' => 12, 'extra' => 0])
        ->and($law->daysFor(6, CarbonImmutable::parse('2026-06-01')))->toBe(['statutory' => 22, 'extra' => 0]);
});
