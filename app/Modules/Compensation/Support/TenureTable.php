<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

/**
 * The table of a benefit that grows with seniority (vacations, a premium by years): from each year of service on, a value.
 * The rows ascend by year and none repeats; the row with the greatest year not after the person's rules.
 */
final class TenureTable
{
    public const int MAX_ROWS = 40;

    /** Nobody serves longer. */
    public const int MAX_YEARS = 80;

    /**
     * What is wrong with the rows, in words for the person who typed them; null when they are fine.
     *
     * @param  list<array{from_years: int, value: int}>  $rows
     */
    public static function problem(array $rows): ?string
    {
        if (count($rows) > self::MAX_ROWS) {
            return 'La tabla puede tener '.self::MAX_ROWS.' renglones a lo más.';
        }

        $previous = -1;

        foreach ($rows as $row) {
            if ($row['from_years'] < 0 || $row['from_years'] > self::MAX_YEARS || $row['value'] < 0) {
                return 'Cada renglón lleva años de servicio de 0 a '.self::MAX_YEARS.' y un valor que no sea negativo.';
            }

            if ($row['from_years'] <= $previous) {
                return 'Ordena los renglones por años de servicio, de menos a más, sin repetir un año.';
            }

            $previous = $row['from_years'];
        }

        return null;
    }

    /**
     * The value for a person with those completed years of service: the row with the greatest year not after theirs, or the
     * base value when none applies (no table, or fewer years than its first row).
     *
     * @param  ?list<array{from_years: int, value: int}>  $rows  ascending
     */
    public static function valueAt(?array $rows, int $base, int $years): int
    {
        $value = $base;

        foreach ($rows ?? [] as $row) {
            if ($row['from_years'] > $years) {
                break;
            }

            $value = $row['value'];
        }

        return $value;
    }
}
