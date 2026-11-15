<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\Proration;
use App\Modules\Billing\Support\Quote;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

mutates(Quote::class, Proration::class, Money::class, Catalog::class);

/** Pesos to cents, so the tables read as the spec writes them. */
function pesos(int $pesos): int
{
    return $pesos * 100;
}

it('prices every fixture of the spec (§5), without VAT', function (Plan $plan, Profile $profile, int $seats, int $centers, int $eligible, int $expected): void {
    $quote = Quote::for($plan, $profile, $seats, $centers, 'v1', $eligible);

    expect($quote->requiresQuotation)->toBeFalse()
        ->and($quote->subtotalCents)->toBe($expected)
        ->and($quote->currency)->toBe('MXN');
})->with([
    'direct Jornada, 20 people, 1 center' => [Plan::Jornada, Profile::Direct, 20, 1, 0, pesos(1990)],
    'direct Cumplimiento, 100, 2 centers' => [Plan::Cumplimiento, Profile::Direct, 100, 2, 0, pesos(4900)],
    'direct Integral, 100, 3 centers' => [Plan::Integral, Profile::Direct, 100, 3, 0, pesos(9900)],
    'direct Integral, 100, 4 centers' => [Plan::Integral, Profile::Direct, 100, 4, 0, pesos(10049)],
    'channel Cumplimiento 50' => [Plan::Cumplimiento, Profile::ChannelCompliance, 50, 2, 0, pesos(2490)],
    'channel Cumplimiento 51' => [Plan::Cumplimiento, Profile::ChannelCompliance, 51, 2, 0, pesos(3990)],
    'channel Cumplimiento 100' => [Plan::Cumplimiento, Profile::ChannelCompliance, 100, 2, 0, pesos(3990)],
    'channel Cumplimiento 101' => [Plan::Cumplimiento, Profile::ChannelCompliance, 101, 2, 0, pesos(7990)],
    'channel Cumplimiento 250' => [Plan::Cumplimiento, Profile::ChannelCompliance, 250, 2, 0, pesos(7990)],
    'channel Integral eligible, one of 3 companies of 100' => [Plan::Integral, Profile::ChannelIntegral, 100, 3, 3, pesos(7920)],
    'channel Integral eligible, one of 5 companies of 100' => [Plan::Integral, Profile::ChannelIntegral, 100, 3, 5, pesos(7920)],
    'channel Integral with 2 companies: no discount' => [Plan::Integral, Profile::ChannelIntegral, 100, 3, 2, pesos(9900)],
    'channel Integral eligible, one of 3 companies of 20 (the minimum, discounted)' => [Plan::Integral, Profile::ChannelIntegral, 20, 3, 3, pesos(3992)],
]);

it('adds up the companies of a consultant as the spec does', function (): void {
    $sum = fn (int $companies, Plan $plan, Profile $profile, int $seats, int $centers, int $eligible): int => $companies
        * (Quote::for($plan, $profile, $seats, $centers, 'v1', $eligible)->subtotalCents ?? 0);

    expect($sum(5, Plan::Cumplimiento, Profile::ChannelCompliance, 100, 2, 0))->toBe(pesos(19950))
        ->and($sum(3, Plan::Integral, Profile::ChannelIntegral, 100, 3, 3))->toBe(pesos(23760))
        ->and($sum(5, Plan::Integral, Profile::ChannelIntegral, 100, 3, 5))->toBe(pesos(39600))
        ->and($sum(2, Plan::Integral, Profile::ChannelIntegral, 100, 3, 2))->toBe(pesos(19800))
        ->and($sum(3, Plan::Integral, Profile::ChannelIntegral, 20, 3, 3))->toBe(pesos(11976));
});

it('prices the boundaries of every profile', function (Plan $plan, Profile $profile, int $seats, ?int $expected): void {
    $quote = Quote::for($plan, $profile, $seats, 2, 'v1', 0);

    expect($quote->subtotalCents)->toBe($expected)
        ->and($quote->requiresQuotation)->toBe($expected === null);
})->with([
    'direct 0: the minimum' => [Plan::Cumplimiento, Profile::Direct, 0, pesos(2990)],
    'direct 1: the minimum' => [Plan::Cumplimiento, Profile::Direct, 1, pesos(2990)],
    'direct 50: still the minimum' => [Plan::Cumplimiento, Profile::Direct, 50, pesos(2990)],
    'direct 51: still the minimum' => [Plan::Cumplimiento, Profile::Direct, 51, pesos(2990)],
    'direct 61: the last one under the minimum' => [Plan::Cumplimiento, Profile::Direct, 61, pesos(2990)],
    'direct 62: by rate' => [Plan::Cumplimiento, Profile::Direct, 62, pesos(3038)],
    'direct 100: by rate' => [Plan::Cumplimiento, Profile::Direct, 100, pesos(4900)],
    'direct Jornada 51: the minimum plus its extra center' => [Plan::Jornada, Profile::Direct, 51, pesos(1990 + 149)],
    'direct Jornada 52: by rate plus its extra center' => [Plan::Jornada, Profile::Direct, 52, pesos(2028 + 149)],
    'direct 101: quotation' => [Plan::Cumplimiento, Profile::Direct, 101, null],
    'direct 250: quotation' => [Plan::Cumplimiento, Profile::Direct, 250, null],
    'direct Integral 50: still the minimum' => [Plan::Integral, Profile::Direct, 50, pesos(4990)],
    'direct Integral 51: by rate' => [Plan::Integral, Profile::Direct, 51, pesos(5049)],
    'direct Jornada 51: still the minimum, plus the center beyond its one' => [Plan::Jornada, Profile::Direct, 51, pesos(1990 + 149)],
    'direct Jornada 52: by rate, plus that center' => [Plan::Jornada, Profile::Direct, 52, pesos(2028 + 149)],
    'channel 0: the first band' => [Plan::Cumplimiento, Profile::ChannelCompliance, 0, pesos(2490)],
    'channel 1: the first band' => [Plan::Cumplimiento, Profile::ChannelCompliance, 1, pesos(2490)],
    'channel 250: the last band' => [Plan::Cumplimiento, Profile::ChannelCompliance, 250, pesos(7990)],
    'channel 251: quotation' => [Plan::Cumplimiento, Profile::ChannelCompliance, 251, null],
    'channel Integral 250 by rate' => [Plan::Integral, Profile::ChannelIntegral, 250, pesos(24750)],
    'channel Integral 251: quotation' => [Plan::Integral, Profile::ChannelIntegral, 251, null],
]);

it('explains the amount in lines: the plan, the discount of the channel and the centers beyond the ones included', function (): void {
    $quote = Quote::for(Plan::Integral, Profile::ChannelIntegral, 100, 5, 'v1', 3);

    expect(array_map(fn ($line): array => [$line->code, $line->amountCents], $quote->lines))->toBe([
        ['plan', pesos(9900)],
        ['channel_discount', -pesos(1980)],
        ['extra_centers', pesos(298)],
    ])
        ->and($quote->subtotalCents)->toBe(pesos(8218))
        ->and($quote->lines[0]->explanation)->toBe('100 personas × $99 = $9,900')
        ->and($quote->lines[1]->explanation)->toBe('20% de descuento de canal: 3 empresas con Integral pagado')
        ->and($quote->lines[2]->explanation)->toBe('2 centros más de los 3 incluidos × $149 = $298');
});

it('says why the minimum applies when the rate gives less', function (): void {
    expect(Quote::for(Plan::Jornada, Profile::Direct, 20, 1, 'v1')->lines[0]->explanation)
        ->toBe('Mínimo del plan: $1,990 (20 personas × $39 = $780)');
});

it('does not discount the extra centers: the discount is only on the plan line', function (): void {
    $discounted = Quote::for(Plan::Integral, Profile::ChannelIntegral, 100, 4, 'v1', 3);

    expect($discounted->subtotalCents)->toBe(pesos(7920 + 149));
});

it('adds VAT as its own amount over the subtotal, rounded once', function (): void {
    $quote = Quote::for(Plan::Cumplimiento, Profile::Direct, 100, 2, 'v1');

    expect($quote->subtotalCents)->toBe(pesos(4900))
        ->and($quote->vatCents)->toBe(pesos(784))
        ->and($quote->totalCents)->toBe(pesos(5684));

    $odd = Quote::for(Plan::Cumplimiento, Profile::Direct, 100, 2, 'v1', negotiatedCents: 10_003);
    expect($odd->vatCents)->toBe(1_600)->and($odd->totalCents)->toBe(11_603);
});

it('asks for a quotation, with no amounts at all, above the limit of the profile', function (): void {
    $quote = Quote::for(Plan::Cumplimiento, Profile::Direct, 101, 5, 'v1');

    expect($quote->requiresQuotation)->toBeTrue()
        ->and($quote->lines)->toBe([])
        ->and($quote->subtotalCents)->toBeNull()
        ->and($quote->vatCents)->toBeNull()
        ->and($quote->totalCents)->toBeNull()
        ->and($quote->explanation)->toBe('Más de 100 personas: requiere cotización');
});

it('uses a negotiated price instead of the table, even above the limit, and still adds the extra centers', function (): void {
    $quote = Quote::for(Plan::Cumplimiento, Profile::Direct, 180, 3, 'v1', negotiatedCents: pesos(8000));

    expect($quote->requiresQuotation)->toBeFalse()
        ->and($quote->lines[0]->code)->toBe('plan')
        ->and($quote->lines[0]->explanation)->toBe('Precio negociado: $8,000')
        ->and($quote->subtotalCents)->toBe(pesos(8149));
});

it('never discounts a negotiated price, whatever the profile says', function (): void {
    $quote = Quote::for(Plan::Integral, Profile::ChannelIntegral, 100, 3, 'v1', 5, negotiatedCents: pesos(9000));

    expect($quote->subtotalCents)->toBe(pesos(9000));
});

it('needs a price for the negotiated profile', function (): void {
    Quote::for(Plan::Integral, Profile::Negotiated, 100, 3, 'v1');
})->throws(InvalidArgumentException::class, 'negociado');

it('prices a negotiated profile with its price', function (): void {
    expect(Quote::for(Plan::Integral, Profile::Negotiated, 400, 3, 'v1', negotiatedCents: pesos(30000))->subtotalCents)->toBe(pesos(30000));
});

it('only combines a channel profile with its plan', function (Plan $plan, Profile $profile): void {
    Quote::for($plan, $profile, 10, 1, 'v1');
})->throws(InvalidArgumentException::class)->with([
    'channel compliance with Integral' => [Plan::Integral, Profile::ChannelCompliance],
    'channel compliance with Jornada' => [Plan::Jornada, Profile::ChannelCompliance],
    'channel Integral with Cumplimiento' => [Plan::Cumplimiento, Profile::ChannelIntegral],
]);

it('rejects a negative count and a catalog that does not exist', function (): void {
    expect(fn () => Quote::for(Plan::Jornada, Profile::Direct, -1, 1, 'v1'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Quote::for(Plan::Jornada, Profile::Direct, 1, -1, 'v1'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Quote::for(Plan::Jornada, Profile::Direct, 1, 1, 'v99'))->toThrow(InvalidArgumentException::class, 'v99');
});

it('prorates each line by days with integer math, rounding half a cent up, once per line', function (): void {
    $half = Quote::for(Plan::Cumplimiento, Profile::Direct, 100, 2, 'v1', negotiatedCents: 1_001, proration: new Proration(1, 2));

    expect($half->lines[0]->amountCents)->toBe(501);

    $month = Quote::for(Plan::Cumplimiento, Profile::Direct, 100, 4, 'v1', proration: new Proration(10, 30));

    // 4,900.00 × 10/30 = 1,633.33 and 2 × 149.00 × 10/30 = 99.33, each rounded on its own.
    expect($month->lines[0]->amountCents)->toBe(163_333)
        ->and($month->lines[1]->amountCents)->toBe(9_933)
        ->and($month->subtotalCents)->toBe(173_266);
});

it('discounts the prorated plan line, not the full one', function (): void {
    $quote = Quote::for(Plan::Integral, Profile::ChannelIntegral, 51, 3, 'v1', 3, proration: new Proration(15, 30));

    expect($quote->lines[0]->amountCents)->toBe(252_450)
        ->and($quote->lines[1]->amountCents)->toBe(-50_490)
        ->and($quote->subtotalCents)->toBe(201_960);
});

it('does not prorate a whole period, and rejects days that make no sense', function (): void {
    $whole = Quote::for(Plan::Cumplimiento, Profile::Direct, 100, 2, 'v1', proration: new Proration(30, 30));

    expect($whole->subtotalCents)->toBe(pesos(4900))
        ->and(fn () => new Proration(0, 30))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Proration(31, 30))->toThrow(InvalidArgumentException::class);
});

it('prorates from the day a subscription starts to the end of its month', function (string $start, ?array $expected): void {
    $proration = Proration::startingOn(CarbonImmutable::parse($start));

    expect($proration === null ? null : [$proration->daysRemaining, $proration->periodDays])->toBe($expected);
})->with([
    'the first day is a whole month' => ['2026-12-01', null],
    'the second day' => ['2026-12-02', [30, 31]],
    'the last day of a 31-day month' => ['2026-12-31', [1, 31]],
    'a 30-day month' => ['2026-11-15', [16, 30]],
    'February of a leap year' => ['2028-02-10', [20, 29]],
    'February' => ['2027-02-28', [1, 28]],
]);

it('writes money in pesos without decimals when there are none', function (int $cents, string $text): void {
    expect(Money::format($cents))->toBe($text);
})->with([
    [0, '$0'],
    [199_000, '$1,990'],
    [1_000_000_00, '$1,000,000'],
    [14_950, '$149.50'],
    [5, '$0.05'],
    [-198_000, '-$1,980'],
]);

it('reads pesos typed in a form as cents, never through a float', function (string $typed, ?int $cents): void {
    expect(Money::parse($typed))->toBe($cents);
})->with([
    'whole pesos' => ['8000', 800_000],
    'one decimal' => ['149.5', 14_950],
    'two decimals' => ['149.05', 14_905],
    'zero' => ['0', 0],
    'with spaces around' => [' 8000 ', 800_000],
    'a comma for the decimals' => ['149,50', null],
    'a thousands separator' => ['8,000', null],
    'three decimals' => ['1.005', null],
    'a sign' => ['-5', null],
    'text' => ['mucho', null],
    'empty' => ['', null],
    'a dot with nothing after it' => ['5.', null],
    'a huge amount' => ['99999999999999999999', null],
]);

it('writes cents back as pesos for a form field: without decimals when there are none', function (int $cents, string $typed): void {
    expect(Money::input($cents))->toBe($typed)->and(Money::parse(Money::input($cents)))->toBe($cents);
})->with([
    [0, '0'],
    [800_000, '8000'],
    [814_950, '8149.50'],
    [14_905, '149.05'],
    [5, '0.05'],
]);
