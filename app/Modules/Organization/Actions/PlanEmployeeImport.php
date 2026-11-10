<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Data\EmployeeImportPlan;
use App\Modules\Organization\Data\EmployeeImportRow;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Organization\Http\Requests\EmployeeRequest;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\EmployeeCsv;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
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

    /** @var array<string, string> state code by folded name and by folded code */
    private array $states = [];

    /** @var array<string, string> employee id by email */
    private array $byEmail = [];

    /** @var array<string, string> employee id by lowercase number */
    private array $byNumber = [];

    /** @var array<string, int> the spreadsheet row where each email, then each number, first appears */
    private array $seen = [];

    /** A union's people are identified by the company ID alone: no contact, gender or work center. */
    private bool $union = false;

    public function __construct(private readonly WorkCenters $workCenters, private readonly Companies $companies) {}

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
            [$result, $messages] = $this->union ? $this->checkUnion($row, $line) : $this->check($row, $line);

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
        $this->union = $this->companies->current()->isUnion();

        foreach ($this->workCenters->all() as $center) {
            if (! $center->archived) {
                $this->centers[self::fold($center->name)] = $center->id;
            }
        }

        foreach ($this->workCenters->states() as $code => $label) {
            $this->states[self::fold($label)] = $code;
            $this->states[self::fold($code)] = $code;
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
        $payScale = $this->payScale($row);
        $rules = Arr::except(EmployeeRequest::fieldRules(), ['gender', 'work_center_id']);
        $messages = array_values(Validator::make([...$input, ...$payScale['input']], $rules)->errors()->all());
        $messages = [...$messages, ...$payScale['messages']];

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
            jobCategory: self::text($payScale['input'], 'job_category') ?: null,
            jobLevel: self::text($payScale['input'], 'job_level') ?: null,
            hiredOn: $payScale['hiredOn'],
            staffGroup: $payScale['group'],
            state: self::text($payScale['input'], 'state') ?: null,
        ), $existingId, $payScale['absent']), []];
    }

    /**
     * A row of a union's file: only the company ID is required. Contact, gender and work center columns, if the file
     * has them, are left out, as the form does not ask them.
     *
     * @param  array<string, string>  $row
     * @return array{0: EmployeeImportRow|null, 1: list<string>}
     */
    private function checkUnion(array $row, int $line): array
    {
        $input = ['name' => $row['nombre'] ?? '', 'area' => $row['area'] ?? '', 'job_title' => $row['puesto'] ?? '', 'employee_number' => $row['numero_de_empleado'] ?? ''];
        $payScale = $this->payScale($row);
        $errors = Validator::make([...$input, ...$payScale['input']], EmployeeRequest::unionFieldRules(), [], ['employee_number' => 'ID de la empresa'])->errors();
        $messages = [...array_values($errors->all()), ...$payScale['messages'], ...$this->repeated(null, $input['employee_number'] === '' ? null : $input['employee_number'], $line)];

        if ($messages !== []) {
            return [null, $messages];
        }

        $number = $input['employee_number'];

        return [new EmployeeImportRow(new EmployeeData(
            name: $input['name'] ?: null,
            email: null,
            mobile: null,
            gender: null,
            workCenterId: null,
            area: $input['area'] ?: null,
            jobTitle: $input['job_title'] ?: null,
            employeeNumber: $number,
            jobCategory: self::text($payScale['input'], 'job_category') ?: null,
            jobLevel: self::text($payScale['input'], 'job_level') ?: null,
            hiredOn: $payScale['hiredOn'],
            staffGroup: $payScale['group'],
            state: self::text($payScale['input'], 'state') ?: null,
        ), $this->byNumber[mb_strtolower($number)] ?? null, [
            ...$payScale['absent'],
            ...array_keys(array_filter([
                'name' => ! array_key_exists('nombre', $row),
                'area_id' => ! array_key_exists('area', $row),
                'job_title' => ! array_key_exists('puesto', $row),
            ])),
        ]), []];
    }

    /**
     * What the pay scale columns say, for the validator as the form would send it (empty is none), with what the file
     * gets wrong, and the columns it does not have.
     *
     * @param  array<string, string>  $row
     * @return array{input: array<string, string|null>, messages: list<string>, hiredOn: CarbonImmutable|null, group: StaffGroup|null, absent: list<string>}
     */
    private function payScale(array $row): array
    {
        $messages = [];
        $hired = trim($row['fecha_de_ingreso'] ?? '');
        $hiredOn = $hired === '' ? null : EmployeeCsv::date($hired);
        $groupText = trim($row['grupo'] ?? '');
        $group = $groupText === '' ? null : StaffGroup::fromSpanish($groupText);
        $stateText = trim($row['estado'] ?? '');
        $state = $stateText === '' ? null : ($this->states[self::fold($stateText)] ?? null);

        foreach ([
            [$hired !== '' && $hiredOn === null, 'La fecha de ingreso debe ser AAAA-MM-DD o DD/MM/AAAA.'],
            [$groupText !== '' && $group === null, 'El grupo debe ser Sindicalizado o Confianza.'],
            [$stateText !== '' && $state === null, "No hay un estado «{$stateText}»."],
        ] as [$wrong, $message]) {
            if ($wrong) {
                $messages[] = $message;
            }
        }

        return [
            'input' => [
                'job_category' => trim($row['categoria'] ?? '') ?: null,
                'job_level' => trim($row['nivel'] ?? '') ?: null,
                'hired_on' => $hiredOn,
                'staff_group' => $group?->value,
                'state' => $state,
            ],
            'messages' => $messages,
            'hiredOn' => $hiredOn === null ? null : CarbonImmutable::parse($hiredOn),
            'group' => $group,
            // No column carries the recognized seniority: an import never touches it.
            'absent' => ['seniority_on', ...array_keys(array_filter([
                'job_grade_id' => ! array_key_exists('categoria', $row) && ! array_key_exists('nivel', $row),
                'hired_on' => ! array_key_exists('fecha_de_ingreso', $row),
                'staff_group' => ! array_key_exists('grupo', $row),
                'state' => ! array_key_exists('estado', $row),
            ]))],
        ];
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
                $messages[] = match (true) {
                    $kind === 'email' => "El correo se repite en la fila {$first}.",
                    $this->union => "El ID de la empresa se repite en la fila {$first}.",
                    default => "El número de empleado se repite en la fila {$first}.",
                };
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
