<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Data\EmployeeImportPlan;
use App\Modules\Organization\Data\EmployeeImportRow;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Http\Requests\EmployeeRequest;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Checks every row of an import against the same rules as the form, the company's work centers and its employees,
 * and against the file's other rows, without writing anything. A row with a number updates the employee with that
 * number; a row without one, the employee with that email; any other row adds someone.
 */
final class PlanEmployeeImport
{
    /** @var array<string, string> work center id by folded name */
    private array $centers = [];

    /** @var array<string, string> employee id by email */
    private array $byEmail = [];

    /** @var array<string, string> employee id by lowercase number */
    private array $byNumber = [];

    /** @var array<string, int> the spreadsheet row where each email, then each number, first appears */
    private array $seen = [];

    public function __construct(private readonly WorkCenters $workCenters) {}

    /**
     * @param  list<array<string, string>>  $rows  as EmployeeCsv reads them.
     */
    public function handle(array $rows): EmployeeImportPlan
    {
        $this->load();
        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            [$result, $messages] = $this->check($row, $line);

            if ($result !== null) {
                $valid[] = $result;
            } else {
                $errors[] = ['row' => $line, 'messages' => $messages];
            }
        }

        return new EmployeeImportPlan(count($rows), $valid, $errors);
    }

    private function load(): void
    {
        foreach ($this->workCenters->all() as $center) {
            if (! $center->archived) {
                $this->centers[self::fold($center->name)] = $center->id;
            }
        }

        foreach (Employee::query()->get(['id', 'email', 'employee_number']) as $employee) {
            if ($employee->email !== null) {
                $this->byEmail[$employee->email] = $employee->id;
            }

            if ($employee->employee_number !== null) {
                $this->byNumber[mb_strtolower($employee->employee_number)] = $employee->id;
            }
        }
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: EmployeeImportRow|null, 1: list<string>}
     */
    private function check(array $row, int $line): array
    {
        $center = $row['centro_de_trabajo'] ?? '';
        $gender = Gender::fromSpanish($row['genero'] ?? '');
        $input = EmployeeRequest::normalize([
            'name' => $row['nombre'] ?? '',
            'email' => $row['correo'] ?? '',
            'mobile' => $row['celular'] ?? '',
            'area' => $row['area'] ?? '',
            'job_title' => $row['puesto'] ?? '',
            'employee_number' => ($row['numero_de_empleado'] ?? '') === '' ? null : $row['numero_de_empleado'],
        ]);
        $rules = Arr::except(EmployeeRequest::fieldRules(), ['gender', 'work_center_id']);
        $messages = array_values(Validator::make($input, $rules)->errors()->all());

        if ($gender === null) {
            $messages[] = 'El género debe ser Mujer, Hombre u Otro.';
        }

        $centerId = $this->centers[self::fold($center)] ?? null;

        if ($centerId === null) {
            $messages[] = $center === '' ? 'El campo centro de trabajo es obligatorio.' : "No hay un centro de trabajo «{$center}».";
        }

        $email = is_string($input['email'] ?? null) ? $input['email'] : null;
        $number = is_string($input['employee_number'] ?? null) ? $input['employee_number'] : null;
        $existingId = $number !== null ? ($this->byNumber[mb_strtolower($number)] ?? null) : ($email !== null ? ($this->byEmail[$email] ?? null) : null);
        $messages = [...$messages, ...$this->repeated($email, $number, $line), ...$this->taken($email, $existingId)];

        if ($messages !== [] || $gender === null || $centerId === null) {
            return [null, $messages];
        }

        return [new EmployeeImportRow(new EmployeeData(
            name: self::text($input, 'name'),
            email: $email,
            mobile: is_string($input['mobile'] ?? null) ? $input['mobile'] : null,
            gender: $gender,
            workCenterId: $centerId,
            area: self::text($input, 'area'),
            jobTitle: self::text($input, 'job_title'),
            employeeNumber: $number,
        ), $existingId), []];
    }

    /**
     * @return list<string>
     */
    private function repeated(?string $email, ?string $number, int $line): array
    {
        $messages = [];

        foreach (['email' => $email, 'number' => $number === null ? null : mb_strtolower($number)] as $kind => $value) {
            if ($value === null) {
                continue;
            }

            $first = $this->seen["{$kind}:{$value}"] ??= $line;

            if ($first !== $line) {
                $messages[] = $kind === 'email' ? "El correo se repite en la fila {$first}." : "El número de empleado se repite en la fila {$first}.";
            }
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    private function taken(?string $email, ?string $existingId): array
    {
        $owner = $email === null ? null : ($this->byEmail[$email] ?? null);

        return $owner !== null && $owner !== $existingId ? ['Ya hay un empleado con este correo.'] : [];
    }

    /**
     * @param  array<array-key, mixed>  $input
     */
    private static function text(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private static function fold(string $text): string
    {
        return Str::of($text)->ascii()->lower()->squish()->toString();
    }
}
