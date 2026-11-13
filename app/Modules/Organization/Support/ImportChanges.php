<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Models\Employee;
use Illuminate\Support\Str;

/**
 * What an import would change in a person who already exists, field by field, before and after, so HR sees what a file
 * does before confirming it. A column the file does not carry changes nothing and is not listed.
 */
final class ImportChanges
{
    /**
     * @param  array<string, string>  $centers  work center names by id
     * @param  list<string>  $absent  the columns the file does not have (see EmployeeImportRow)
     * @return list<array{label: string, before: string, after: string}>
     */
    public static function between(Employee $person, EmployeeData $data, array $centers, array $absent): array
    {
        $fields = [
            ['Nombre', $person->name, $data->name, 'name'],
            ['Correo', $person->email, $data->email, null],
            ['Celular', $person->mobile, $data->mobile, null],
            ['Género', $person->gender?->label(), $data->gender?->label(), null],
            ['Centro de trabajo', $centers[$person->work_center_id ?? ''] ?? null, $centers[$data->workCenterId ?? ''] ?? null, null],
            ['Área', self::text($person->getAttributeValue('area_name')), $data->area, 'area_id'],
            ['Puesto', $person->job_title, $data->jobTitle, 'job_title'],
            ['Número de empleado', $person->employee_number, $data->employeeNumber, null],
            ['Fecha de ingreso', $person->hired_on?->toDateString(), $data->hiredOn?->toDateString(), 'hired_on'],
            ['Grupo', $person->staff_group?->label(), $data->staffGroup?->label(), 'staff_group'],
        ];
        $changes = [];

        foreach ($fields as [$label, $before, $after, $column]) {
            if (($column !== null && in_array($column, $absent, true)) || self::same($before, $after)) {
                continue;
            }

            $changes[] = ['label' => $label, 'before' => $before ?? '—', 'after' => $after ?? '—'];
        }

        return $changes;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /** Capital letters and accents do not make a change: «producción» is the area «Producción». */
    private static function same(?string $before, ?string $after): bool
    {
        return Str::of($before ?? '')->ascii()->lower()->trim()->toString() === Str::of($after ?? '')->ascii()->lower()->trim()->toString();
    }
}
