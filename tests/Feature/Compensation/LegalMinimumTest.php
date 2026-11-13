<?php

declare(strict_types=1);

use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Models\FiscalParameter;
use App\Modules\Compensation\Support\FiscalParameters;
use App\Modules\Compensation\Support\LegalMinimum;
use App\Modules\Compensation\Support\MinimumWageCheck;
use App\Modules\Compensation\Support\SalaryRules;
use Carbon\CarbonImmutable;

mutates(LegalMinimum::class, SalaryRules::class, MinimumWageCheck::class);

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

it('warns of a daily salary under the general minimum, to the cent', function (): void {
    $warning = 'Está por debajo del salario mínimo general ($315.04 diarios; $440.87 en la zona libre de la frontera norte). Revísalo si la jornada es completa.';

    expect(SalaryRules::salaryWarning(31503, 31504, 44087))->toBe($warning)
        ->and(SalaryRules::salaryWarning(31504, 31504, 44087))->toBeNull()
        ->and(SalaryRules::salaryWarning(52465, 31504, 44087))->toBeNull();
});

it('takes the minimum in force on the date of the salary, not the one of today', function (): void {
    FiscalParameter::factory()->create(['key' => 'minimum_wage', 'valid_from' => '2027-01-01', 'value_int' => 36000]);
    FiscalParameter::factory()->create(['key' => 'minimum_wage_border', 'valid_from' => '2027-01-01', 'value_int' => 50000]);
    $check = resolve(MinimumWageCheck::class);

    expect($check->warning(33000, CarbonImmutable::parse('2026-06-01')))->toBeNull()
        ->and($check->warning(33000, CarbonImmutable::parse('2027-02-01')))->toContain('$360.00 diarios; $500.00 en la zona libre');
});

it('says nothing of a salary dated before the platform has any minimum wage, and does not fail', function (): void {
    expect(resolve(MinimumWageCheck::class)->warning(100, CarbonImmutable::parse('2000-01-01')))->toBeNull();
});

it('holds the salary in force today to today\'s minimum, however old it is, and a later one to its own day', function (): void {
    $check = resolve(MinimumWageCheck::class);
    $today = CarbonImmutable::parse('2026-11-03');

    expect($check->inForce(25000, CarbonImmutable::parse('2000-01-01'), $today))->toContain('$315.04')
        ->and($check->inForce(31504, CarbonImmutable::parse('2000-01-01'), $today))->toBeNull()
        ->and($check->inForce(25000, CarbonImmutable::parse('2026-12-01'), $today))->toContain('$315.04');
});
