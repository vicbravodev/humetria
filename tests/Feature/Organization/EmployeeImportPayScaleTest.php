<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\ImportEmployees;
use App\Modules\Organization\Actions\PlanEmployeeImport;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Organization\Support\EmployeeCsv;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Http\UploadedFile;
use Inertia\Support\SessionKey;

mutates(PlanEmployeeImport::class, ImportEmployees::class, EmployeeCsv::class);

const PAY_SCALE_HEADER = 'nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado,categoria,nivel,fecha_de_ingreso,grupo,estado';
const OLD_HEADER = 'nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado';

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
});

/** @param  list<string>  $rows */
function payScaleFile(array $rows, string $header = PAY_SCALE_HEADER): UploadedFile
{
    return UploadedFile::fake()->createWithContent('empleados.csv', implode("\n", [$header, ...$rows])."\n");
}

function importPayScale(mixed $test, array $rows, string $header = PAY_SCALE_HEADER, bool $confirm = true): void
{
    $test->actingAs($test->owner)
        ->post(route('tenant.employees.import.store', $test->tenant), [...($confirm ? ['confirm' => '1'] : []), 'file' => payScaleFile($rows, $header)])
        ->assertSessionHasNoErrors();
}

/** @return array<string, mixed> the flashed import summary */
function payScaleFlash(): array
{
    $flash = session(SessionKey::FLASH_DATA);

    return is_array($flash) && is_array($flash['import'] ?? null) ? $flash['import'] : [];
}

function payScaleOf(string $number): array
{
    $employee = Employee::query()->withoutGlobalScopes()->where('employee_number', $number)->sole();
    $grade = $employee->job_grade_id === null ? null : JobGrade::query()->withoutGlobalScopes()->findOrFail($employee->job_grade_id);

    return [$grade?->category, $grade?->level, $employee->hired_on?->toDateString(), $employee->staff_group?->value, $employee->state];
}

it('reads category, level, hire date, group and state, and creates a job grade once however many rows name it', function (): void {
    $existing = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Técnico', 'level' => 'B']);

    importPayScale($this, [
        'Ana López,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,A-1,Operador,A,2024-03-15,Sindicalizado,Querétaro',
        'Beto Díaz,beto@acme.mx,,Hombre,Planta Querétaro,Producción,Operador,B-2,OPERADOR,a,15/03/2023,sindicalizada,que',
        'Carla Ruiz,carla@acme.mx,,Mujer,Planta Querétaro,Producción,Técnica,C-3,técnico,b,1/2/2020,Confianza,Ciudad de México',
        'Dani Paz,dani@acme.mx,,Otro,Planta Querétaro,Producción,Auxiliar,D-4,,,,,',
    ]);

    expect(payScaleOf('A-1'))->toBe(['Operador', 'A', '2024-03-15', 'unionized', 'QUE'])
        ->and(payScaleOf('B-2'))->toBe(['Operador', 'A', '2023-03-15', 'unionized', 'QUE'])
        ->and(payScaleOf('C-3'))->toBe(['Técnico', 'B', '2020-02-01', 'trusted', 'CMX'])
        ->and(payScaleOf('D-4'))->toBe([null, null, null, null, null])
        ->and(JobGrade::query()->withoutGlobalScopes()->orderBy('category')->pluck('category')->all())->toBe(['Operador', 'Técnico'])
        ->and(Employee::query()->withoutGlobalScopes()->where('employee_number', 'C-3')->sole()->job_grade_id)->toBe($existing->id);
});

it('previews a file with the new columns without creating job grades', function (): void {
    importPayScale($this, ['Ana López,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,A-1,Operador,A,2024-03-15,Sindicalizado,Querétaro'], confirm: false);

    expect(payScaleFlash())->toBe(['status' => 'ready', 'rows' => 1, 'created' => 1, 'updated' => 0, 'errors' => []])
        ->and(JobGrade::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Employee::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('keeps working with an old template and leaves the pay scale of those it updates as it is', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);
    Employee::factory()->create([
        'tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'employee_number' => 'A-1',
        'job_grade_id' => $grade->id, 'hired_on' => '2024-03-15', 'staff_group' => 'trusted', 'state' => 'JAL',
    ]);

    importPayScale($this, [
        'Ana López,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,A-1',
        'Beto Díaz,beto@acme.mx,,Hombre,Planta Querétaro,Producción,Operador,B-2',
    ], OLD_HEADER);

    expect(payScaleOf('A-1'))->toBe([$grade->category, $grade->level, '2024-03-15', 'trusted', 'JAL'])
        ->and(payScaleOf('B-2'))->toBe([null, null, null, null, null])
        ->and(Employee::query()->withoutGlobalScopes()->where('employee_number', 'A-1')->sole()->name)->toBe('Ana López');
});

it('clears what a column of the file leaves empty when it updates someone, and leaves what has no column', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    Employee::factory()->create([
        'tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'employee_number' => 'A-1',
        'job_grade_id' => $grade->id, 'hired_on' => '2024-03-15', 'staff_group' => 'trusted', 'state' => 'JAL',
    ]);

    importPayScale($this, ['Ana López,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,A-1,,,,,'], PAY_SCALE_HEADER);
    expect(payScaleOf('A-1'))->toBe([null, null, null, null, null]);

    Employee::query()->withoutGlobalScopes()->where('employee_number', 'A-1')->update(['job_grade_id' => $grade->id, 'hired_on' => '2024-03-15', 'staff_group' => 'trusted', 'state' => 'JAL']);

    // Only the group column is in the file.
    importPayScale($this, ['Ana López,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,A-1,Sindicalizado'], OLD_HEADER.',grupo');
    expect(payScaleOf('A-1'))->toBe(['Operador', 'A', '2024-03-15', 'unionized', 'JAL']);
});

it('reports a row\'s bad pay scale data and imports nothing', function (): void {
    $today = now(config()->string('app.display_timezone'))->addDay()->toDateString();

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => payScaleFile([
            'Ana,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,A-1,Operador,A,ayer,,',
            'Beto,beto@acme.mx,,Hombre,Planta Querétaro,Producción,Operador,B-2,Operador,A,31/02/2024,,',
            'Carla,carla@acme.mx,,Mujer,Planta Querétaro,Producción,Técnica,C-3,Operador,A,'.$today.',,',
            'Dani,dani@acme.mx,,Otro,Planta Querétaro,Producción,Auxiliar,D-4,Operador,A,,Directivo,',
            'Eva,eva@acme.mx,,Mujer,Planta Querétaro,Producción,Jefa,E-5,Operador,A,,,Narnia',
            'Fer,fer@acme.mx,,Hombre,Planta Querétaro,Producción,Jefe,F-6,Operador,,,,',
            'Gus,gus@acme.mx,,Hombre,Planta Querétaro,Producción,Jefe,G-7,,A,,,',
            'Hugo,hugo@acme.mx,,Hombre,Planta Querétaro,Producción,Jefe,H-8,Operador,A,2024-13-01,,',
        ])])
        ->assertRedirect();

    expect(payScaleFlash())->toBe(['status' => 'invalid', 'rows' => 8, 'created' => 0, 'updated' => 0, 'errors' => [
        ['row' => 2, 'messages' => ['La fecha de ingreso debe ser AAAA-MM-DD o DD/MM/AAAA.']],
        ['row' => 3, 'messages' => ['La fecha de ingreso debe ser AAAA-MM-DD o DD/MM/AAAA.']],
        ['row' => 4, 'messages' => ['La fecha de ingreso no puede ser futura.']],
        ['row' => 5, 'messages' => ['El grupo debe ser Sindicalizado o Confianza.']],
        ['row' => 6, 'messages' => ['No hay un estado «Narnia».']],
        ['row' => 7, 'messages' => ['El campo nivel es obligatorio cuando categoría esté presente.']],
        ['row' => 8, 'messages' => ['El campo categoría es obligatorio cuando nivel esté presente.']],
        ['row' => 9, 'messages' => ['La fecha de ingreso debe ser AAAA-MM-DD o DD/MM/AAAA.']],
    ]])->and(Employee::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(JobGrade::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('accepts a hire date of today', function (): void {
    importPayScale($this, ['Ana,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,A-1,,,'.now(config()->string('app.display_timezone'))->toDateString().',,']);

    expect(payScaleOf('A-1')[2])->toBe(now(config()->string('app.display_timezone'))->toDateString());
});

it('rejects a category or level longer than the pay scale allows', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['file' => payScaleFile([
            'Ana,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,A-1,'.str_repeat('a', 121).',A,,,',
            'Beto,beto@acme.mx,,Hombre,Planta Querétaro,Producción,Operador,B-2,Operador,'.str_repeat('a', 61).',,,',
        ])]);

    expect(payScaleFlash()['errors'])->toBe([
        ['row' => 2, 'messages' => ['El campo categoría no debe tener más de 120 caracteres.']],
        ['row' => 3, 'messages' => ['El campo nivel no debe tener más de 60 caracteres.']],
    ]);
});

it('keeps requiring name, contact, gender, work center, area and job title of a company in the import', function (string $row, string $message): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => payScaleFile([$row])]);

    expect(payScaleFlash()['status'])->toBe('invalid')
        ->and(payScaleFlash()['errors'])->toBe([['row' => 2, 'messages' => [$message]]])
        ->and(Employee::query()->withoutGlobalScopes()->count())->toBe(0);
})->with([
    'no contact' => ['Ana,,,Mujer,Planta Querétaro,Producción,Operadora,A-1,Operador,A,,,', 'Escribe un correo o un celular.'],
    'no name' => [',ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,A-1,Operador,A,,,', 'El campo nombre es obligatorio.'],
    'no gender' => ['Ana,ana@acme.mx,,,Planta Querétaro,Producción,Operadora,A-1,Operador,A,,,', 'El género debe ser Mujer, Hombre u Otro.'],
    'no work center' => ['Ana,ana@acme.mx,,Mujer,,Producción,Operadora,A-1,Operador,A,,,', 'El campo centro de trabajo es obligatorio.'],
    'no area' => ['Ana,ana@acme.mx,,Mujer,Planta Querétaro,,Operadora,A-1,Operador,A,,,', 'El campo área es obligatorio.'],
    'no job title' => ['Ana,ana@acme.mx,,Mujer,Planta Querétaro,Producción,,A-1,Operador,A,,,', 'El campo puesto es obligatorio.'],
]);

it('takes a hire date of February 29 only in a leap year', function (): void {
    importPayScale($this, ['Ana,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,A-1,,,29/02/2024,,']);

    expect(payScaleOf('A-1')[2])->toBe('2024-02-29');

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['file' => payScaleFile(['Beto,beto@acme.mx,,Hombre,Planta Querétaro,Producción,Operador,B-2,,,2023-02-29,,'])]);

    expect(payScaleFlash()['errors'])->toBe([['row' => 2, 'messages' => ['La fecha de ingreso debe ser AAAA-MM-DD o DD/MM/AAAA.']]]);
});
