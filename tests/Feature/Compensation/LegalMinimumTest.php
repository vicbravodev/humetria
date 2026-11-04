<?php

declare(strict_types=1);

use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Support\FiscalParameters;
use App\Modules\Compensation\Support\LegalMinimum;
use Carbon\CarbonImmutable;

mutates(LegalMinimum::class);

beforeEach(function (): void {
    $this->legal = resolve(FiscalParameters::class)->at(CarbonImmutable::parse('2026-11-03'));
});

it('points out an aguinaldo below 15 days and accepts 15 and more', function (): void {
    expect(LegalMinimum::problem(BaseBenefit::ChristmasBonus, 1499, null, $this->legal))->toBe('Está por debajo del mínimo de la ley: 15 días.')
        ->and(LegalMinimum::problem(BaseBenefit::ChristmasBonus, 1500, null, $this->legal))->toBeNull()
        ->and(LegalMinimum::problem(BaseBenefit::ChristmasBonus, 3000, null, $this->legal))->toBeNull();
});

it('points out a vacation premium below 25 percent and accepts 25 and more', function (): void {
    expect(LegalMinimum::problem(BaseBenefit::VacationPremium, 2499, null, $this->legal))->toBe('Está por debajo del mínimo de la ley: 25 %.')
        ->and(LegalMinimum::problem(BaseBenefit::VacationPremium, 2500, null, $this->legal))->toBeNull();
});

it('points out the first year of service whose vacation days fall below the law, with and without a table', function (): void {
    $legal = [['from_years' => 1, 'value' => 1200], ['from_years' => 2, 'value' => 1400], ['from_years' => 3, 'value' => 1600], ['from_years' => 4, 'value' => 1800], ['from_years' => 5, 'value' => 2000], ['from_years' => 6, 'value' => 2200], ['from_years' => 11, 'value' => 2400], ['from_years' => 16, 'value' => 2600], ['from_years' => 21, 'value' => 2800], ['from_years' => 26, 'value' => 3000], ['from_years' => 31, 'value' => 3200]];

    expect(LegalMinimum::problem(BaseBenefit::Vacation, 1200, $legal, $this->legal))->toBeNull()
        ->and(LegalMinimum::problem(BaseBenefit::Vacation, 1200, [['from_years' => 1, 'value' => 1200], ['from_years' => 2, 'value' => 1300]], $this->legal))
        ->toBe('Está por debajo del mínimo de la ley: en el año 2 de servicio da 13 días y la ley pide 14.')
        ->and(LegalMinimum::problem(BaseBenefit::Vacation, 1200, null, $this->legal))->toBe('Está por debajo del mínimo de la ley: en el año 2 de servicio da 12 días y la ley pide 14.');
});

it('has no minimum to compare for the other benefits and for the ones the company makes up', function (): void {
    expect(LegalMinimum::problem(BaseBenefit::FoodVouchers, 0, null, $this->legal))->toBeNull()
        ->and(LegalMinimum::problem(null, 0, null, $this->legal))->toBeNull();
});
