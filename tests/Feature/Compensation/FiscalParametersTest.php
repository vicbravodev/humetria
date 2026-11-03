<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\FiscalParameter;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\FiscalParameters;
use App\Modules\Tenancy\Enums\MexicanState;
use Carbon\CarbonImmutable;
use Database\Seeders\FiscalParametersSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * The values come from docs/normas/compensation/criterios.md (consulted 2026-11-03). They are written out here by hand,
 * so a typo in the seeder cannot hide behind the data it seeded.
 */

mutates(FiscalParameters::class);

function fiscalOn(string $date): App\Modules\Compensation\Data\FiscalSnapshot
{
    return resolve(FiscalParameters::class)->at(CarbonImmutable::parse($date));
}

it('returns the 2026 values of criterios.md', function (): void {
    $fiscal = fiscalOn('2026-11-03');

    expect($fiscal->uma())->toBe(11731)
        ->and($fiscal->minimumWage())->toBe(31504)
        ->and($fiscal->cents('minimum_wage_border'))->toBe(44087)
        ->and($fiscal->sbcCap())->toBe(293275)
        ->and($fiscal->rate('infonavit')->compareTo(Rate::fraction(5, 100)))->toBe(0)
        ->and($fiscal->rate('imss_fixed')->of($fiscal->uma()))->toBe(2393)
        ->and($fiscal->rate('imss_surplus')->compareTo(Rate::scaled(110, 4)))->toBe(0)
        ->and($fiscal->rate('imss_pensioners')->compareTo(Rate::scaled(105, 4)))->toBe(0)
        ->and($fiscal->rate('imss_cash')->compareTo(Rate::scaled(70, 4)))->toBe(0)
        ->and($fiscal->rate('imss_disability_life')->compareTo(Rate::scaled(175, 4)))->toBe(0)
        ->and($fiscal->rate('imss_childcare')->compareTo(Rate::scaled(100, 4)))->toBe(0)
        ->and($fiscal->rate('retirement')->compareTo(Rate::scaled(200, 4)))->toBe(0)
        ->and($fiscal->rate('imss_surplus_umas')->compareTo(Rate::fraction(3, 1)))->toBe(0)
        ->and($fiscal->isStale())->toBeFalse()
        ->and($fiscal->staleKeys())->toBe([]);
});

it('gives the legal minimums of the Ley Federal del Trabajo', function (): void {
    $fiscal = fiscalOn('2026-11-03');

    expect($fiscal->rate('christmas_bonus_days')->compareTo(Rate::fraction(15, 1)))->toBe(0)
        ->and($fiscal->rate('vacation_premium')->compareTo(Rate::fraction(1, 4)))->toBe(0)
        ->and($fiscal->rate('seniority_premium_days')->compareTo(Rate::fraction(12, 1)))->toBe(0)
        ->and($fiscal->rate('seniority_premium_cap_minimum_wages')->compareTo(Rate::fraction(2, 1)))->toBe(0)
        ->and($fiscal->rate('voucher_exempt_uma')->compareTo(Rate::fraction(2, 5)))->toBe(0);
});

it('steps the legal vacation days of article 76 by years of service', function (int $years, int $days): void {
    expect(fiscalOn('2026-11-03')->legalVacationDays($years))->toBe($days);
})->with([
    [0, 0], [1, 12], [2, 14], [3, 16], [4, 18], [5, 20], [6, 22], [10, 22], [11, 24], [15, 24],
    [16, 26], [20, 26], [21, 28], [25, 28], [26, 30], [30, 30], [31, 32], [35, 32], [40, 32],
]);

it('picks the cesantia and vejez rate by SBC range and year, upper limits inclusive', function (string $date, int $sbc, string $percent): void {
    $rate = fiscalOn($date)->severanceOldAgeRate($sbc);

    expect($rate->compareTo(Rate::scaled((int) str_replace('.', '', $percent), 5)))->toBe(0);
})->with([
    'the minimum wage itself' => ['2026-03-01', 31504, '3.150'],
    'a centavo above the minimum wage falls in 2.51 to 3.00 UMA' => ['2026-03-01', 31505, '6.026'],
    'below the minimum wage' => ['2026-03-01', 100, '3.150'],
    '3.00 UMA is in 2.51 to 3.00' => ['2026-03-01', 35193, '6.026'],
    'a centavo above 3.00 UMA' => ['2026-03-01', 35194, '6.361'],
    '3.50 UMA' => ['2026-03-01', 41058, '6.361'],
    'a centavo above 3.50 UMA' => ['2026-03-01', 41059, '6.613'],
    '4.00 UMA' => ['2026-03-01', 46924, '6.613'],
    'a centavo above 4.00 UMA' => ['2026-03-01', 46925, '7.513'],
    'the cap' => ['2026-03-01', 293275, '7.513'],
    '4.47 UMA of the worked example' => ['2026-11-03', 52465, '7.513'],
    'the next year' => ['2027-06-01', 52465, '8.603'],
    'the year after' => ['2028-06-01', 52465, '9.694'],
    'in 2030' => ['2030-01-01', 52465, '11.875'],
    'after 2030 the text stays fixed' => ['2033-05-05', 52465, '11.875'],
]);

it('carries the last value past its end and flags it stale', function (): void {
    $fiscal = fiscalOn('2027-06-01');

    expect($fiscal->uma())->toBe(11731)
        ->and($fiscal->minimumWage())->toBe(31504)
        ->and($fiscal->isStale())->toBeTrue()
        ->and($fiscal->staleKeys())->toContain('uma', 'minimum_wage', 'minimum_wage_border', 'payroll_tax:JAL')
        ->and($fiscal->staleKeys())->not->toContain('infonavit', 'severance_old_age.8')
        ->and($fiscal->staleKeys())->toBe(collect($fiscal->staleKeys())->sort()->values()->all());
});

it('keeps the UMA of the year before until 31 January', function (): void {
    expect(fiscalOn('2027-01-31')->staleKeys())->not->toContain('uma')
        ->and(fiscalOn('2027-02-01')->staleKeys())->toContain('uma')
        ->and(fiscalOn('2026-12-31')->staleKeys())->not->toContain('minimum_wage')
        ->and(fiscalOn('2027-01-01')->staleKeys())->toContain('minimum_wage');
});

it('resolves the value valid on a date and the one before a vigencia change', function (): void {
    expect(fiscalOn('2024-02-01')->uma())->toBe(10857)
        ->and(fiscalOn('2025-01-31')->uma())->toBe(10857)
        ->and(fiscalOn('2025-02-01')->uma())->toBe(11314)
        ->and(fiscalOn('2026-01-31')->uma())->toBe(11314)
        ->and(fiscalOn('2026-02-01')->uma())->toBe(11731)
        ->and(fiscalOn('2026-02-01')->sbcCap())->toBe(293275)
        ->and(fiscalOn('2026-01-31')->sbcCap())->toBe(282850);
});

it('resolves a seeded row by its own vigencia, valid_to included', function (): void {
    FiscalParameter::factory()->create(['key' => 'test_amount', 'valid_from' => '2026-01-01', 'valid_to' => '2026-06-30', 'value_int' => 100, 'scale' => 0]);
    FiscalParameter::factory()->create(['key' => 'test_amount', 'valid_from' => '2026-07-01', 'valid_to' => null, 'value_int' => 250, 'scale' => 0]);

    expect(fiscalOn('2026-06-30')->cents('test_amount'))->toBe(100)
        ->and(fiscalOn('2026-06-30')->staleKeys())->not->toContain('test_amount')
        ->and(fiscalOn('2026-07-01')->cents('test_amount'))->toBe(250)
        ->and(fiscalOn('2031-01-01')->staleKeys())->not->toContain('test_amount');
});

it('flags a row that ended before the date and was not replaced', function (): void {
    FiscalParameter::factory()->create(['key' => 'test_ended', 'valid_from' => '2026-01-01', 'valid_to' => '2026-06-30', 'value_int' => 100, 'scale' => 0]);

    expect(fiscalOn('2026-06-30')->staleKeys())->not->toContain('test_ended')
        ->and(fiscalOn('2026-07-01')->staleKeys())->toContain('test_ended')
        ->and(fiscalOn('2026-07-01')->cents('test_ended'))->toBe(100);
});

it('refuses a value that no vigencia covers yet instead of guessing', function (): void {
    fiscalOn('2024-01-31')->uma();
})->throws(OutOfBoundsException::class);

it('refuses an unknown key', function (): void {
    fiscalOn('2026-11-03')->rate('no_such_rate');
})->throws(OutOfBoundsException::class);

it('knows payroll tax for the 32 states, or says it does not model it', function (): void {
    $flat = [
        'AGU' => '0.0250', 'BCN' => '0.0425', 'BCS' => '0.0300', 'CAM' => '0.0399', 'CHP' => '0.0300', 'CHH' => '0.0400',
        'CMX' => '0.0400', 'COA' => '0.0300', 'COL' => '0.0300', 'DUR' => '0.0300', 'GUA' => '0.0300', 'GRO' => '0.0300',
        'HID' => '0.0300', 'JAL' => '0.0300', 'MEX' => '0.0300', 'MIC' => '0.0300', 'MOR' => '0.0300', 'NAY' => '0.0300',
        'NLE' => '0.0300', 'OAX' => '0.0300', 'PUE' => '0.0300', 'QUE' => '0.0300', 'ROO' => '0.0400', 'SLP' => '0.0300',
        'SON' => '0.0300', 'TAB' => '0.0350', 'TAM' => '0.0300', 'TLA' => '0.0300', 'VER' => '0.0300', 'YUC' => '0.0375',
        'ZAC' => '0.0350',
    ];
    $fiscal = fiscalOn('2026-11-03');
    $codes = array_map(fn (MexicanState $state): string => $state->value, MexicanState::cases());

    // 31 flat rates + Sinaloa's progressive tariff, which is not a rate: 32 states, none forgotten.
    expect($codes)->toHaveCount(32)
        ->and(array_diff($codes, [...array_keys($flat), 'SIN']))->toBe([]);

    foreach ($flat as $code => $decimal) {
        expect($fiscal->payrollTax($code)?->compareTo(Rate::scaled((int) str_replace('.', '', $decimal), 4)))->toBe(0, $code);
    }

    expect($fiscal->payrollTax('SIN'))->toBeNull()
        ->and($fiscal->payrollTax('XXX'))->toBeNull();
});

it('keeps the particularities of a state in its note and marks what is still to verify', function (): void {
    $note = fn (string $code): string => (string) FiscalParameter::query()->where('key', 'payroll_tax')->where('state', $code)->value('note');

    expect($note('GRO'))->toContain('por verificar')
        ->and($note('CAM'))->toContain('33 %')
        ->and($note('SON'))->toContain('100 o más trabajadores')
        ->and($note('CMX'))->toContain('por verificar')
        ->and(FiscalParameter::query()->where('key', 'payroll_tax')->where('state', 'SIN')->exists())->toBeFalse();
});

it('follows the Chihuahua transitory: 4 percent in 2026 and 2027, 3 percent from 2028', function (): void {
    expect(fiscalOn('2027-12-31')->payrollTax('CHH')?->compareTo(Rate::scaled(400, 4)))->toBe(0)
        ->and(fiscalOn('2027-12-31')->staleKeys())->not->toContain('payroll_tax:CHH')
        ->and(fiscalOn('2028-01-01')->payrollTax('CHH')?->compareTo(Rate::scaled(300, 4)))->toBe(0);
});

it('stores the source and the consulted date of every seeded value', function (): void {
    $rows = FiscalParametersSeeder::rows();

    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        expect(trim($row['source']))->not->toBe('', $row['key'])
            ->and($row['consulted_on'])->toBe('2026-11-03', $row['key']);
    }

    expect(FiscalParameter::query()->count())->toBeGreaterThanOrEqual(count($rows))
        ->and(FiscalParameter::query()->whereRaw("btrim(source) = ''")->orWhereNull('consulted_on')->count())->toBe(0);
});

it('seeds neither the work risk premium nor anything criterios.md leaves to verify', function (): void {
    $keys = FiscalParameter::query()->distinct()->pluck('key')->all();

    expect(array_filter($keys, fn (string $key): bool => str_contains($key, 'risk')))->toBe([])
        ->and(FiscalParameter::query()->where('key', 'minimum_wage')->where('valid_from', '<', '2026-01-01')->exists())->toBeFalse();
});

it('rejects at the database a row without source, with a vigencia that ends before it starts, or repeated', function (): void {
    $insert = fn (array $row) => DB::transaction(fn () => FiscalParameter::factory()->create($row));

    expect(fn () => $insert(['source' => '  ']))->toThrow(QueryException::class);
    expect(fn () => $insert(['valid_from' => '2026-05-01', 'valid_to' => '2026-04-30']))->toThrow(QueryException::class);

    FiscalParameter::factory()->create(['key' => 'test_unique', 'state' => null, 'valid_from' => '2026-01-01']);
    expect(fn () => $insert(['key' => 'test_unique', 'state' => null, 'valid_from' => '2026-01-01']))->toThrow(QueryException::class);
});
