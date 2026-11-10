<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Organization\Contracts\Roster;

/**
 * What the check of a file of opening balances tells the page: how many rows can come in and, row by row and in Spanish, why
 * the others cannot. Only the first rows of each list travel; the page says how many more there are. Never a salary or an
 * amount, only names, years and days.
 *
 * @phpstan-type Plan array{ready: list<array{line: int, employeeId: string, years: int, anniversary: string, days: int}>, rejected: list<array{line: int, reason: string}>}
 */
final readonly class ImportPages
{
    /** Rows of each list the page shows. */
    public const int SHOWN = 50;

    private const array REASONS = [
        'unknown_employee' => 'No hay una persona con ese número de empleado.',
        'no_start_date' => 'La persona no tiene fecha de ingreso.',
        'not_an_anniversary' => 'Ese año no es un aniversario que ya haya pasado.',
        'after_tracking_start' => 'Ese periodo lo otorga el sistema desde la fecha de arranque.',
        'invalid_days' => 'Los días pendientes deben ser un número entero.',
        'too_many_days' => 'Son más días de los que daba ese periodo.',
        'already_loaded' => 'Ese periodo ya está cargado.',
        'duplicate_in_file' => 'El archivo repite ese periodo.',
    ];

    public function __construct(private Roster $roster) {}

    /**
     * @param  Plan  $plan
     * @return array{ready: int, rejected: int, readyRows: list<array{line: int, name: string, year: int, days: int}>, rejectedRows: list<array{line: int, reason: string}>, hiddenRejected: int}
     */
    public function summary(array $plan): array
    {
        $shown = array_slice($plan['ready'], 0, self::SHOWN);
        $names = $this->roster->findMany(array_values(array_unique(array_column($shown, 'employeeId'))));

        return [
            'ready' => count($plan['ready']),
            'rejected' => count($plan['rejected']),
            'readyRows' => array_map(fn (array $row): array => [
                'line' => $row['line'],
                'name' => ($names[$row['employeeId']] ?? null)->name ?? '',
                'year' => (int) substr($row['anniversary'], 0, 4),
                'days' => $row['days'],
            ], $shown),
            'rejectedRows' => array_map(fn (array $row): array => ['line' => $row['line'], 'reason' => self::REASONS[$row['reason']] ?? 'No se pudo cargar esta fila.'], array_slice($plan['rejected'], 0, self::SHOWN)),
            'hiddenRejected' => max(0, count($plan['rejected']) - self::SHOWN),
        ];
    }
}
