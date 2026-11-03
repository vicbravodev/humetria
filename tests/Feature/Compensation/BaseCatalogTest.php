<?php

declare(strict_types=1);

use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Support\BaseCatalog;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

mutates(BaseCatalog::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-03 10:00:00');
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/** The base benefits of the company by key. */
function baseBenefits(): Illuminate\Database\Eloquent\Collection
{
    return Benefit::query()->whereNotNull('base_key')->get()->keyBy('base_key');
}

it('seeds the base benefits once per account', function (): void {
    resolve(BaseCatalog::class)->ensure();

    expect(baseBenefits()->keys()->sort()->values()->all())->toBe(collect(BaseBenefit::cases())->map->value->sort()->values()->all())
        ->and(Benefit::query()->count())->toBe(11)
        ->and(ContractTerm::query()->count())->toBe(11);
});

it('seeds nothing more on a second call and leaves the changes of the account alone', function (): void {
    resolve(BaseCatalog::class)->ensure();
    $aguinaldo = baseBenefits()['christmas_bonus'];
    ContractTerm::query()->where('benefit_id', $aguinaldo->id)->update(['value' => 3000]);
    $aguinaldo->update(['in_payroll_tax' => false]);

    resolve(BaseCatalog::class)->ensure();

    expect(Benefit::query()->count())->toBe(11)
        ->and(ContractTerm::query()->count())->toBe(11)
        ->and(ContractTerm::query()->where('benefit_id', $aguinaldo->id)->sole()->value)->toBe(3000)
        ->and(Benefit::query()->findOrFail($aguinaldo->id)->in_payroll_tax)->toBeFalse();
});

it('skips the benefit another request already created, with the terms that request wrote', function (): void {
    $racer = Benefit::factory()->base('vacation')->create(['tenant_id' => $this->tenant->id]);

    resolve(BaseCatalog::class)->ensure();

    expect(Benefit::query()->count())->toBe(11)
        ->and(Benefit::query()->findOrFail($racer->id)->terms()->count())->toBe(0)
        ->and(ContractTerm::query()->count())->toBe(10);
});

it('seeds each account its own and touches no other', function (): void {
    $other = Tenant::factory()->create();
    Benefit::factory()->base('vacation')->create(['tenant_id' => $other->id]);

    resolve(BaseCatalog::class)->ensure();

    expect(Benefit::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(11)
        ->and(Benefit::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->count())->toBe(1);

    workIn($other);
    resolve(BaseCatalog::class)->ensure();

    expect(Benefit::query()->count())->toBe(11)
        ->and(Benefit::query()->withoutGlobalScopes()->count())->toBe(22);
});

it('creates nothing when the legal values are not known for the date', function (): void {
    expect(fn () => resolve(BaseCatalog::class)->ensure(CarbonImmutable::parse('2000-06-01')))->toThrow(OutOfBoundsException::class)
        ->and(Benefit::query()->count())->toBe(0);
});

it('gives each base benefit its attributes', function (string $key, string $name, BenefitKind $kind, Frequency $frequency, SbcIntegration $sbc, bool $payrollTax): void {
    resolve(BaseCatalog::class)->ensure();
    $benefit = baseBenefits()[$key];

    expect($benefit->name)->toBe($name)
        ->and($benefit->kind)->toBe($kind)
        ->and($benefit->frequency)->toBe($frequency)
        ->and($benefit->sbc_integration)->toBe($sbc)
        ->and($benefit->in_payroll_tax)->toBe($payrollTax)
        // Not covered by criterios.md, so none of them is claimed: the PTU and seniority bases and the income tax exemption.
        ->and($benefit->in_ptu_base)->toBeFalse()
        ->and($benefit->in_seniority_base)->toBeFalse()
        ->and($benefit->income_tax_exempt)->toBeFalse()
        ->and($benefit->applies_to_group)->toBeNull()
        ->and($benefit->job_grade_ids)->toBe([])
        ->and($benefit->min_tenure_months)->toBe(0)
        ->and($benefit->sbc_cap_cents)->toBeNull()
        ->and($benefit->cap_cents)->toBeNull()
        ->and($benefit->archived_at)->toBeNull();
})->with([
    'aguinaldo' => ['christmas_bonus', 'Aguinaldo', BenefitKind::SalaryDays, Frequency::Yearly, SbcIntegration::Always, true],
    // The days of vacation are already in the salary; the factor takes their premium.
    'vacaciones' => ['vacation', 'Vacaciones', BenefitKind::SalaryDays, Frequency::Yearly, SbcIntegration::Never, false],
    'prima vacacional' => ['vacation_premium', 'Prima vacacional', BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Always, true],
    // LSS art. 27: a fund with equal deposits does not integrate, and the ISN leaves it out the same way (criterios.md 3 and 6).
    'fondo de ahorro' => ['savings_fund', 'Fondo de ahorro', BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Never, false],
    // Up to 40 % of the UMA: the law sets the cap, so the benefit keeps none.
    'vales de despensa' => ['food_vouchers', 'Vales de despensa', BenefitKind::FixedAmount, Frequency::Monthly, SbcIntegration::UpToCap, true],
    'prima de antigüedad' => ['seniority_premium', 'Prima de antigüedad', BenefitKind::SalaryDays, Frequency::Yearly, SbcIntegration::Always, true],
    'prima dominical' => ['sunday_premium', 'Prima dominical', BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Always, true],
    'bono de productividad' => ['productivity_bonus', 'Bono de productividad', BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Always, true],
    // Up to 10 % of the SBC, also the law's cap.
    'bono de asistencia' => ['attendance_bonus', 'Bono de asistencia y puntualidad', BenefitKind::SalaryPercent, Frequency::Monthly, SbcIntegration::UpToCap, true],
    'gastos médicos mayores' => ['major_medical', 'Gastos médicos mayores', BenefitKind::FixedAmount, Frequency::Yearly, SbcIntegration::Always, true],
    // A pension plan that meets the CONSAR requirements does not integrate (criterios.md 3).
    'fondo de jubilación' => ['retirement_fund', 'Fondo de jubilación', BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Never, true],
]);

it('starts the terms with the legal values of the year, and at zero where the law sets none', function (): void {
    resolve(BaseCatalog::class)->ensure();
    $terms = fn (string $key): ContractTerm => baseBenefits()[$key]->terms()->sole();

    expect($terms('christmas_bonus')->value)->toBe(1500)
        ->and($terms('vacation_premium')->value)->toBe(2500)
        ->and($terms('vacation')->value)->toBe(1200)
        ->and($terms('vacation')->tenure_table)->toEqual([
            ['from_years' => 1, 'value' => 1200], ['from_years' => 2, 'value' => 1400], ['from_years' => 3, 'value' => 1600],
            ['from_years' => 4, 'value' => 1800], ['from_years' => 5, 'value' => 2000], ['from_years' => 6, 'value' => 2200],
            ['from_years' => 11, 'value' => 2400], ['from_years' => 16, 'value' => 2600], ['from_years' => 21, 'value' => 2800],
            ['from_years' => 26, 'value' => 3000], ['from_years' => 31, 'value' => 3200],
        ])
        ->and($terms('christmas_bonus')->tenure_table)->toBeNull();

    foreach (['savings_fund', 'food_vouchers', 'seniority_premium', 'sunday_premium', 'productivity_bonus', 'attendance_bonus', 'major_medical', 'retirement_fund'] as $key) {
        expect($terms($key)->value)->toBe(0)->and($terms($key)->tenure_table)->toBeNull();
    }
});

it('dates the terms at the start of the year of the date it is asked for', function (): void {
    resolve(BaseCatalog::class)->ensure(CarbonImmutable::parse('2026-05-17'));

    expect(ContractTerm::query()->pluck('effective_from')->map->toDateString()->unique()->all())->toBe(['2026-01-01']);
});
