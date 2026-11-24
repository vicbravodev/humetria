<?php

declare(strict_types=1);

use App\Modules\Billing\Support\MessagingCost;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

mutates(MessagingCost::class);

function costs(array $costs, int $margin = 30): void
{
    config(['billing.whatsapp.costs' => $costs, 'billing.whatsapp.margin_percent' => $margin]);
}

/** A moment in Mexico City, the time zone the dates of the entries are in. */
function mx(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'America/Mexico_City');
}

it('takes the latest entry whose date has come, counted in Mexico City', function (): void {
    costs([
        ['from' => '2026-07-01', 'currency' => 'MXN', 'unit_cost_micros' => 170_000],
        ['from' => '2027-01-01', 'currency' => 'MXN', 'unit_cost_micros' => 200_000],
    ]);

    expect((new MessagingCost)->at(mx('2026-12-31 23:59:59'))->costMicros)->toBe(170_000)
        ->and((new MessagingCost)->at(mx('2027-01-01 00:00:00'))->costMicros)->toBe(200_000)
        ->and((new MessagingCost)->at(mx('2026-07-01 00:00:00'))->costMicros)->toBe(170_000);
});

it('does not depend on the order the entries are listed in', function (): void {
    costs([
        ['from' => '2027-01-01', 'currency' => 'MXN', 'unit_cost_micros' => 200_000],
        ['from' => '2026-07-01', 'currency' => 'MXN', 'unit_cost_micros' => 170_000],
    ]);

    expect((new MessagingCost)->at(CarbonImmutable::parse('2026-08-01'))->costMicros)->toBe(170_000);
});

it('prices the cost plus the margin and carries the currency', function (): void {
    costs([['from' => '2026-07-01', 'currency' => 'MXN', 'unit_cost_micros' => 170_000]]);

    $cost = (new MessagingCost)->at(CarbonImmutable::parse('2026-11-18'));

    expect($cost->priceMicros)->toBe(221_000)->and($cost->currency)->toBe('MXN');
});

it('rounds the price up to the next micro', function (int $cost, int $margin, int $price): void {
    costs([['from' => '2026-07-01', 'currency' => 'MXN', 'unit_cost_micros' => $cost]], $margin);

    expect((new MessagingCost)->at(CarbonImmutable::parse('2026-11-18'))->priceMicros)->toBe($price);
})->with([
    'exact' => [100_000, 30, 130_000],
    'a fraction of a micro goes up' => [100_001, 30, 130_002],
    'no margin' => [123_457, 0, 123_457],
    'one percent of one micro' => [1, 1, 2],
]);

it('refuses when no entry is in force yet', function (): void {
    costs([['from' => '2026-07-01', 'currency' => 'MXN', 'unit_cost_micros' => 170_000]]);

    (new MessagingCost)->at(mx('2026-06-30 23:59:59'));
})->throws(LogicException::class);

it('refuses when there are no entries at all', function (): void {
    costs([]);

    (new MessagingCost)->at(CarbonImmutable::parse('2026-11-18'));
})->throws(LogicException::class);

it('ships with the provisional cost of the design: 0.17 MXN from July 2026 and a 30 % margin', function (): void {
    expect(config('billing.whatsapp.costs'))->toBe([['from' => '2026-07-01', 'currency' => 'MXN', 'unit_cost_micros' => 170_000]])
        ->and(config('billing.whatsapp.margin_percent'))->toBe(30);
});
