<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Billing\Support\Proration;
use App\Modules\Billing\Support\Quote;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

mutates(Quote::class, Catalog::class);

it('has a Sindicato plan, with a flat monthly fee, only Compensación and no per-person rate', function (): void {
    $catalog = Catalog::version('v1');

    expect(Plan::from('sindicato'))->toBe(Plan::Sindicato)
        ->and(Plan::Sindicato->label())->toBe('Sindicato')
        ->and($catalog->flatCents(Plan::Sindicato))->toBe(199_000)
        ->and($catalog->modules(Plan::Sindicato))->toBe(['compensation'])
        ->and($catalog->rateCents(Plan::Sindicato))->toBe(0)
        ->and($catalog->quota(Plan::Sindicato, 'assistant_tokens'))->toBe(0);
});

it('charges a union the same flat fee however many members it has, with VAT on top', function (int $seats): void {
    $quote = Quote::for(Plan::Sindicato, Profile::Union, $seats, 1, 'v1');

    expect($quote->requiresQuotation)->toBeFalse()
        ->and($quote->subtotalCents)->toBe(199_000)
        ->and($quote->vatCents)->toBe(31_840)
        ->and($quote->totalCents)->toBe(230_840)
        ->and($quote->lines)->toHaveCount(1)
        ->and($quote->lines[0]->label)->toBe('Sindicato')
        ->and($quote->lines[0]->explanation)->toBe('Cuota fija mensual: $1,990');
})->with([0, 1, 100, 5_000]);

it('prorates the flat fee in the first, partial month', function (): void {
    $quote = Quote::for(Plan::Sindicato, Profile::Union, 0, 1, 'v1', proration: Proration::startingOn(CarbonImmutable::parse('2026-12-16')));

    expect($quote->subtotalCents)->toBe(Quote::divide(199_000 * 16, 31));
});

it('takes a negotiated price for a union as for any plan', function (): void {
    expect(Quote::for(Plan::Sindicato, Profile::Negotiated, 0, 1, 'v1', negotiatedCents: 250_000)->subtotalCents)->toBe(250_000);
});

it('keeps the flat fee for a union and every other plan for a company', function (Plan $plan, Profile $profile): void {
    Quote::for($plan, $profile, 10, 1, 'v1');
})->with([
    'the union fee on a company plan' => [Plan::Integral, Profile::Union],
    'a company rate on the union plan' => [Plan::Sindicato, Profile::Direct],
    'a channel rate on the union plan' => [Plan::Sindicato, Profile::ChannelCompliance],
])->throws(InvalidArgumentException::class);
