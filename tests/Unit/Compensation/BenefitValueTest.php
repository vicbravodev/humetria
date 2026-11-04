<?php

declare(strict_types=1);

use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Support\BenefitValue;

mutates(BenefitValue::class);

it('reads what a person types as hundredths, without floats', function (string $text, ?int $hundredths): void {
    expect(BenefitValue::parse($text))->toBe($hundredths);
})->with([
    'a whole number' => ['15', 1500],
    'zero' => ['0', 0],
    'one decimal' => ['12.5', 1250],
    'two decimals' => ['12.55', 1255],
    'a comma as the decimal mark' => ['12,5', 1250],
    'a trailing zero' => ['500.00', 50000],
    'spaces around it' => ['  25 ', 2500],
    'the largest it keeps' => ['999999999.99', 99999999999],
    'a tenth of a hundredth' => ['1.234', null],
    'too many digits' => ['1000000000', null],
    'a negative number' => ['-1', null],
    'a thousands separator' => ['1,500.00', null],
    'text' => ['quince', null],
    'nothing' => ['', null],
    'a bare mark' => ['.5', null],
    'a mark without decimals' => ['5.', null],
    'a line break after it' => ["5\n", 500],
    'two marks' => ['1.2.3', null],
]);

it('writes hundredths with only the decimals they need', function (int $hundredths, string $text): void {
    expect(BenefitValue::decimal($hundredths))->toBe($text);
})->with([
    [0, '0'], [1500, '15'], [1250, '12.5'], [1255, '12.55'], [5, '0.05'], [50, '0.5'], [50000, '500'], [99999999999, '999999999.99'],
]);

it('shows a value with its unit', function (BenefitKind $kind, int $hundredths, string $text): void {
    expect(BenefitValue::format($kind, $hundredths))->toBe($text);
})->with([
    'days' => [BenefitKind::SalaryDays, 1500, '15 días'],
    'one day' => [BenefitKind::SalaryDays, 100, '1 día'],
    'half a day' => [BenefitKind::SalaryDays, 50, '0.5 días'],
    'percent' => [BenefitKind::SalaryPercent, 2500, '25 %'],
    'money' => [BenefitKind::FixedAmount, 50000, '$500.00'],
    'money with cents' => [BenefitKind::FixedAmount, 123456, '$1,234.56'],
    'UMA' => [BenefitKind::UmaMultiple, 150, '1.5 UMA'],
    'zero' => [BenefitKind::SalaryPercent, 0, '0 %'],
]);
