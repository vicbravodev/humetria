<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Support\Calculation\Money;
use Carbon\CarbonImmutable;

/**
 * What a daily salary, a category and level and a vigencia may be, wherever they come from (a form or a file): one place, so
 * the screens and the importers refuse the same things.
 */
final class SalaryRules
{
    /** A daily salary above this is a typing mistake, and a bigint holds it by far. In cents: $1,000,000. */
    public const int MAX_DAILY_CENTS = 100_000_000;

    public const int CATEGORY_LENGTH = 120;

    public const int LEVEL_LENGTH = 60;

    public const int NOTE_LENGTH = 500;

    public const int NUMBER_LENGTH = 50;

    /** The cents of a salary written in pesos ("524.65", "$ 524,65"); null when it is not pesos and cents. */
    public static function pesos(string $text): ?int
    {
        return BenefitValue::parse((string) preg_replace('/\A\$\s*/', '', trim($text)));
    }

    /** Why a salary in cents cannot be stored, if it cannot; null for no salary at all (it was not a number). */
    public static function salaryProblem(?int $cents): ?string
    {
        return match (true) {
            $cents === null => 'El salario diario debe ser un monto en pesos como 524.65, sin comas de miles.',
            $cents <= 0 => 'El salario diario debe ser mayor que cero.',
            $cents > self::MAX_DAILY_CENTS => 'El salario diario no puede pasar de '.Money::format(self::MAX_DAILY_CENTS).'.',
            default => null,
        };
    }

    /**
     * What is wrong with a category and a level, by field.
     *
     * @return array<string, string>
     */
    public static function gradeProblems(string $category, string $level): array
    {
        return [
            ...self::textProblem('category', 'La categoría', trim($category), self::CATEGORY_LENGTH),
            ...self::textProblem('level', 'El nivel', trim($level), self::LEVEL_LENGTH),
        ];
    }

    /**
     * A date as a spreadsheet writes it, AAAA-MM-DD or DD/MM/AAAA; null when it is neither or does not exist.
     */
    public static function date(string $text): ?CarbonImmutable
    {
        if (preg_match('#\A(\d{4})-(\d{2})-(\d{2})\z#', $text, $iso) === 1) {
            [, $year, $month, $day] = $iso;
        } elseif (preg_match('#\A(\d{1,2})/(\d{1,2})/(\d{4})\z#', $text, $mexican) === 1) {
            [, $day, $month, $year] = $mexican;
        } else {
            return null;
        }

        return checkdate((int) $month, (int) $day, (int) $year) ? CarbonImmutable::create((int) $year, (int) $month, (int) $day, 0, 0, 0, 'UTC') : null;
    }

    /**
     * @return array<string, string>
     */
    private static function textProblem(string $field, string $label, string $text, int $length): array
    {
        return match (true) {
            $text === '' => [$field => "{$label} no puede quedar vacío."],
            mb_strlen($text) > $length => [$field => "{$label} puede tener {$length} caracteres a lo más."],
            default => [],
        };
    }
}
