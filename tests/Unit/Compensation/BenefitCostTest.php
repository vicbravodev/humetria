<?php

declare(strict_types=1);

use App\Modules\Compensation\Data\BenefitSpec;
use App\Modules\Compensation\Data\CostSubject;
use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Support\Calculation\BenefitCost;
use App\Modules\Compensation\Support\Calculation\Rate;

mutates(BenefitCost::class);

/**
 * 2026 values of docs/normas/compensation/criterios.md: UMA $117.31, aguinaldo 15 days, prima vacacional 25 %, vales exentos
 * hasta 40 % de la UMA, and the vacation table of the LFT art. 76 up to the 6 to 10 step.
 */
function benefitFiscal(): FiscalSnapshot
{
    return new FiscalSnapshot([
        'uma' => Rate::scaled(11_731, 0),
        'minimum_wage' => Rate::scaled(31_504, 0),
        'christmas_bonus_days' => Rate::scaled(15, 0),
        'vacation_premium' => Rate::scaled(25, 2),
        'voucher_exempt_uma' => Rate::scaled(40, 2),
        'vacation_days.1' => Rate::scaled(12, 0),
        'vacation_days.2' => Rate::scaled(14, 0),
        'vacation_days.3' => Rate::scaled(16, 0),
        'vacation_days.4' => Rate::scaled(18, 0),
        'vacation_days.5' => Rate::scaled(20, 0),
        'vacation_days.6' => Rate::scaled(22, 0),
    ], []);
}

/**
 * @param  array{key?: string, kind?: BenefitKind, amount?: Rate, frequency?: Frequency, group?: ?PayGroup, jobGradeIds?: list<string>, minTenureMonths?: int, tenureTable?: list<array{fromYears: int, value: Rate}>, sbcIntegration?: SbcIntegration, sbcCapCents?: ?int, inPayrollTax?: bool, capCents?: ?int}  $with
 */
function benefitSpec(array $with = []): BenefitSpec
{
    return new BenefitSpec(
        key: $with['key'] ?? 'meal_bonus',
        kind: $with['kind'] ?? BenefitKind::SalaryDays,
        amount: $with['amount'] ?? Rate::scaled(10, 0),
        frequency: $with['frequency'] ?? Frequency::Yearly,
        group: $with['group'] ?? null,
        jobGradeIds: $with['jobGradeIds'] ?? [],
        minTenureMonths: $with['minTenureMonths'] ?? 0,
        tenureTable: $with['tenureTable'] ?? [],
        sbcIntegration: $with['sbcIntegration'] ?? SbcIntegration::Never,
        sbcCapCents: $with['sbcCapCents'] ?? null,
        inPayrollTax: $with['inPayrollTax'] ?? true,
        incomeTaxExempt: false,
        inSeniorityBase: false,
        capCents: $with['capCents'] ?? null,
    );
}

/** @param  array{dailySalaryCents?: int, group?: PayGroup, jobGradeId?: string, tenureYears?: int, tenureMonths?: int}  $with */
function benefitSubject(array $with = []): CostSubject
{
    return new CostSubject(
        dailySalaryCents: $with['dailySalaryCents'] ?? 50_000,
        group: $with['group'] ?? PayGroup::Unionized,
        jobGradeId: $with['jobGradeId'] ?? 'g1',
        tenureYears: $with['tenureYears'] ?? 3,
        tenureMonths: $with['tenureMonths'] ?? 40,
    );
}

/**
 * @param  array{key?: string, kind?: BenefitKind, amount?: Rate, frequency?: Frequency, group?: ?PayGroup, jobGradeIds?: list<string>, minTenureMonths?: int, tenureTable?: list<array{fromYears: int, value: Rate}>, sbcIntegration?: SbcIntegration, sbcCapCents?: ?int, inPayrollTax?: bool, capCents?: ?int}  $spec
 * @param  array{dailySalaryCents?: int, group?: PayGroup, jobGradeId?: string, tenureYears?: int, tenureMonths?: int}  $subject
 */
function annualOf(array $spec, array $subject = []): int
{
    return BenefitCost::annual(benefitSpec($spec), benefitSubject($subject), benefitFiscal());
}

function dailyIntegrating(BenefitSpec $spec, ?CostSubject $subject = null): Rate
{
    return BenefitCost::dailyIntegrating($spec, $subject ?? benefitSubject(), benefitFiscal());
}

it('costs days of salary per period', function (): void {
    // 10 days × $500 a year; 2 days a month is 24 days; 1 day for each of the 365 days.
    expect(annualOf([]))->toBe(500_000)
        ->and(annualOf(['amount' => Rate::scaled(2, 0), 'frequency' => Frequency::Monthly]))->toBe(1_200_000)
        ->and(annualOf(['amount' => Rate::scaled(1, 0), 'frequency' => Frequency::Daily]))->toBe(18_250_000)
        ->and(annualOf(['amount' => Rate::scaled(225, 1)]))->toBe(1_125_000);
});

it('costs a percent of the salary the same whatever the frequency', function (): void {
    // 8 % of $500 for each of the 365 days.
    foreach (Frequency::cases() as $frequency) {
        expect(annualOf(['kind' => BenefitKind::SalaryPercent, 'amount' => Rate::scaled(8, 2), 'frequency' => $frequency]))->toBe(1_460_000);
    }
});

it('costs a fixed amount per period', function (): void {
    expect(annualOf(['kind' => BenefitKind::FixedAmount, 'amount' => Rate::scaled(50_000, 0), 'frequency' => Frequency::Monthly]))->toBe(600_000)
        ->and(annualOf(['kind' => BenefitKind::FixedAmount, 'amount' => Rate::scaled(50_000, 0), 'frequency' => Frequency::Yearly]))->toBe(50_000)
        ->and(annualOf(['kind' => BenefitKind::FixedAmount, 'amount' => Rate::scaled(4_792, 0), 'frequency' => Frequency::Daily]))->toBe(1_749_080);
});

it('costs a multiple of the UMA per period and settles the cent with a half going up', function (): void {
    // 1.5 × 11,731 = 17,596.5: a month is 211,158 and a single payment rounds up to 17,597.
    expect(annualOf(['kind' => BenefitKind::UmaMultiple, 'amount' => Rate::scaled(15, 1), 'frequency' => Frequency::Monthly]))->toBe(211_158)
        ->and(annualOf(['kind' => BenefitKind::UmaMultiple, 'amount' => Rate::scaled(15, 1), 'frequency' => Frequency::Yearly]))->toBe(17_597);
});

it('costs nothing for who the benefit is not for', function (): void {
    expect(annualOf(['group' => PayGroup::Trusted]))->toBe(0)
        ->and(annualOf(['group' => PayGroup::Unionized]))->toBe(500_000)
        ->and(annualOf(['jobGradeIds' => ['g2', 'g3']]))->toBe(0)
        ->and(annualOf(['jobGradeIds' => ['g2', 'g1']]))->toBe(500_000)
        ->and(annualOf(['minTenureMonths' => 41]))->toBe(0)
        ->and(annualOf(['minTenureMonths' => 40]))->toBe(500_000);
});

it('takes the row of the tenure table with the greatest year not after the person', function (): void {
    $table = [
        ['fromYears' => 2, 'value' => Rate::scaled(15, 0)],
        ['fromYears' => 5, 'value' => Rate::scaled(20, 0)],
        ['fromYears' => 10, 'value' => Rate::scaled(30, 0)],
    ];
    $days = fn (int $years): int => intdiv(annualOf(['tenureTable' => $table], ['tenureYears' => $years]), 50_000);

    // Under the first row the base amount (10 days) applies.
    expect($days(0))->toBe(10)
        ->and($days(1))->toBe(10)
        ->and($days(2))->toBe(15)
        ->and($days(4))->toBe(15)
        ->and($days(5))->toBe(20)
        ->and($days(9))->toBe(20)
        ->and($days(10))->toBe(30)
        ->and($days(40))->toBe(30);
});

it('does not depend on the order of the rows of the tenure table', function (): void {
    $table = [
        ['fromYears' => 10, 'value' => Rate::scaled(30, 0)],
        ['fromYears' => 2, 'value' => Rate::scaled(15, 0)],
    ];

    expect(annualOf(['tenureTable' => $table], ['tenureYears' => 11]))->toBe(1_500_000)
        ->and(annualOf(['tenureTable' => $table], ['tenureYears' => 3]))->toBe(750_000);
});

it('limits the annual amount of a person to the cap, and a cap of zero costs nothing', function (): void {
    expect(annualOf(['capCents' => 400_000]))->toBe(400_000)
        ->and(annualOf(['capCents' => 500_000]))->toBe(500_000)
        ->and(annualOf(['capCents' => 600_000]))->toBe(500_000)
        ->and(annualOf(['capCents' => 0]))->toBe(0);
});

it('integrates to the SBC by its setting', function (): void {
    $yearly = ['kind' => BenefitKind::FixedAmount, 'amount' => Rate::scaled(1_200_000, 0)];

    // 1,200,000 a year is 1,200,000 / 365 a day.
    expect(dailyIntegrating(benefitSpec($yearly))->compareTo(Rate::zero()))->toBe(0)
        ->and(dailyIntegrating(benefitSpec([...$yearly, 'sbcIntegration' => SbcIntegration::Always]))->compareTo(Rate::fraction(1_200_000, 365)))->toBe(0)
        ->and(dailyIntegrating(benefitSpec([...$yearly, 'sbcIntegration' => SbcIntegration::UpToCap, 'sbcCapCents' => 3_000]))->compareTo(Rate::scaled(3_000, 0)))->toBe(0)
        ->and(dailyIntegrating(benefitSpec([...$yearly, 'sbcIntegration' => SbcIntegration::UpToCap, 'sbcCapCents' => 4_000]))->compareTo(Rate::scaled(3_287, 0)))->toBe(1)
        ->and(dailyIntegrating(benefitSpec([...$yearly, 'sbcIntegration' => SbcIntegration::UpToCap, 'sbcCapCents' => 4_000]))->compareTo(Rate::fraction(1_200_000, 365)))->toBe(0);
});

it('integrates nothing of what the person does not receive', function (): void {
    $spec = benefitSpec(['sbcIntegration' => SbcIntegration::Always, 'group' => PayGroup::Trusted]);

    expect(dailyIntegrating($spec)->compareTo(Rate::zero()))->toBe(0);
});

it('integrates a voucher only for what is over 40 percent of the daily UMA, and only the excess', function (): void {
    // 40 % × 117.31 = 46.924, truncated to the cent: $46.92 (criterios.md 5d).
    $vouchers = fn (int $dailyCents): Rate => dailyIntegrating(benefitSpec([
        'key' => 'food_vouchers', 'kind' => BenefitKind::FixedAmount, 'amount' => Rate::scaled($dailyCents, 0),
        'frequency' => Frequency::Daily, 'sbcIntegration' => SbcIntegration::UpToCap,
    ]));

    expect($vouchers(4_692)->compareTo(Rate::zero()))->toBe(0)
        ->and($vouchers(1_000)->compareTo(Rate::zero()))->toBe(0)
        ->and($vouchers(4_693)->compareTo(Rate::scaled(1, 0)))->toBe(0)
        ->and($vouchers(4_792)->compareTo(Rate::scaled(100, 0)))->toBe(0);
});

it('compares a monthly voucher by day', function (): void {
    $monthly = benefitSpec([
        'key' => 'food_vouchers', 'kind' => BenefitKind::FixedAmount, 'amount' => Rate::scaled(150_000, 0),
        'frequency' => Frequency::Monthly, 'sbcIntegration' => SbcIntegration::UpToCap,
    ]);

    // $1,500 a month is 1,800,000 a year, 4,931.51 a day: 239.51 over the 4,692 of the exemption.
    expect(dailyIntegrating($monthly)->compareTo(Rate::fraction(1_800_000 - 4_692 * 365, 365)))->toBe(0);
});

it('resolves the aguinaldo, the vacation and the premium to the law when the contract says nothing', function (): void {
    $terms = BenefitCost::lawTerms([], benefitSubject(['tenureYears' => 6]), benefitFiscal());

    // 15 days; 22 days from the sixth year; 25 %.
    expect($terms->aguinaldoDays->compareTo(Rate::scaled(15, 0)))->toBe(0)
        ->and($terms->vacationDays->compareTo(Rate::scaled(22, 0)))->toBe(0)
        ->and($terms->vacationPremium->compareTo(Rate::scaled(25, 2)))->toBe(0)
        ->and($terms->aguinaldoTaxable)->toBeTrue()
        ->and($terms->premiumTaxable)->toBeTrue()
        ->and(BenefitCost::lawTerms([], benefitSubject(['tenureYears' => 0]), benefitFiscal())->vacationDays->compareTo(Rate::zero()))->toBe(0);
});

it('takes the contract values for the aguinaldo, the vacation table and the premium', function (): void {
    $specs = [
        benefitSpec(['key' => 'christmas_bonus', 'amount' => Rate::scaled(225, 1), 'inPayrollTax' => false]),
        benefitSpec(['key' => 'vacation', 'amount' => Rate::zero(), 'tenureTable' => [
            ['fromYears' => 1, 'value' => Rate::scaled(15, 0)],
            ['fromYears' => 3, 'value' => Rate::scaled(18, 0)],
        ]]),
        benefitSpec(['key' => 'vacation_premium', 'kind' => BenefitKind::SalaryPercent, 'amount' => Rate::scaled(40, 2), 'inPayrollTax' => false]),
    ];
    $terms = BenefitCost::lawTerms($specs, benefitSubject(['tenureYears' => 3]), benefitFiscal());

    expect($terms->aguinaldoDays->compareTo(Rate::scaled(225, 1)))->toBe(0)
        ->and($terms->vacationDays->compareTo(Rate::scaled(18, 0)))->toBe(0)
        ->and($terms->vacationPremium->compareTo(Rate::scaled(40, 2)))->toBe(0)
        ->and($terms->aguinaldoTaxable)->toBeFalse()
        ->and($terms->premiumTaxable)->toBeFalse();
});

it('falls back to the law for who a contract benefit is not for', function (): void {
    $specs = [
        benefitSpec(['key' => 'christmas_bonus', 'amount' => Rate::scaled(30, 0), 'group' => PayGroup::Trusted]),
        benefitSpec(['key' => 'vacation_premium', 'kind' => BenefitKind::SalaryPercent, 'amount' => Rate::scaled(50, 2), 'minTenureMonths' => 60]),
    ];
    $terms = BenefitCost::lawTerms($specs, benefitSubject(), benefitFiscal());

    expect($terms->aguinaldoDays->compareTo(Rate::scaled(15, 0)))->toBe(0)
        ->and($terms->vacationPremium->compareTo(Rate::scaled(25, 2)))->toBe(0);
});

it('costs the aguinaldo and the premium from the resolved terms', function (): void {
    $terms = BenefitCost::lawTerms([], benefitSubject(['tenureYears' => 1]), benefitFiscal());

    // 15 days × $500 = $7,500; 12 days × 25 % × $500 = $1,500.
    expect($terms->aguinaldoCost(50_000))->toBe(750_000)
        ->and($terms->premiumCost(50_000))->toBe(150_000)
        ->and($terms->factor()->compareTo(Rate::fraction(383, 365)))->toBe(0);
});

it('integrates an attendance bonus only for what is over 10 percent of the SBC without it', function (): void {
    // Without the bonus the SBC is $524.65 (52,465 cents): 10 % is 5,246.5 cents a day (LSS art. 27, premios de asistencia y puntualidad).
    $bonus = fn (int $dailyCents, ?int $withoutIt = 52_465): Rate => BenefitCost::dailyIntegrating(benefitSpec([
        'key' => 'attendance_bonus', 'kind' => BenefitKind::FixedAmount, 'amount' => Rate::scaled($dailyCents, 0),
        'frequency' => Frequency::Daily, 'sbcIntegration' => SbcIntegration::UpToCap,
    ]), benefitSubject(), benefitFiscal(), $withoutIt);

    expect($bonus(1_000)->compareTo(Rate::zero()))->toBe(0)
        ->and($bonus(5_246)->compareTo(Rate::zero()))->toBe(0)
        ->and($bonus(5_247)->compareTo(Rate::fraction(1, 2)))->toBe(0)
        ->and($bonus(7_500)->compareTo(Rate::fraction(4_507, 2)))->toBe(0)
        // Nobody knows the SBC without it: the whole bonus integrates, the conservative reading.
        ->and($bonus(1_000, null)->compareTo(Rate::scaled(1_000, 0)))->toBe(0);
});

it('lets the cap the contract names for an attendance bonus rule over the law', function (): void {
    $capped = benefitSpec([
        'key' => 'attendance_bonus', 'kind' => BenefitKind::FixedAmount, 'amount' => Rate::scaled(7_500, 0),
        'frequency' => Frequency::Daily, 'sbcIntegration' => SbcIntegration::UpToCap, 'sbcCapCents' => 3_000,
    ]);

    expect(BenefitCost::dailyIntegrating($capped, benefitSubject(), benefitFiscal(), 52_465)->compareTo(Rate::scaled(3_000, 0)))->toBe(0);
});

it('says which benefits need the SBC without them to know what integrates', function (): void {
    expect(BenefitCost::needsSbcWithoutIt(benefitSpec(['key' => 'attendance_bonus', 'sbcIntegration' => SbcIntegration::UpToCap])))->toBeTrue()
        ->and(BenefitCost::needsSbcWithoutIt(benefitSpec(['key' => 'attendance_bonus', 'sbcIntegration' => SbcIntegration::UpToCap, 'sbcCapCents' => 100])))->toBeFalse()
        ->and(BenefitCost::needsSbcWithoutIt(benefitSpec(['key' => 'attendance_bonus', 'sbcIntegration' => SbcIntegration::Always])))->toBeFalse()
        ->and(BenefitCost::needsSbcWithoutIt(benefitSpec(['key' => 'food_vouchers', 'sbcIntegration' => SbcIntegration::UpToCap])))->toBeFalse()
        ->and(BenefitCost::needsSbcWithoutIt(benefitSpec(['key' => 'custom', 'sbcIntegration' => SbcIntegration::UpToCap])))->toBeFalse();
});
