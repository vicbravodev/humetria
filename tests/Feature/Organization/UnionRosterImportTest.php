<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\ImportEmployees;
use App\Modules\Organization\Actions\PlanEmployeeImport;
use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Organization\Support\EmployeeCsv;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Inertia\Support\SessionKey;

mutates(PlanEmployeeImport::class, ImportEmployees::class, EmployeeCsv::class);

const UNION_HEADER = 'numero_de_empleado,nombre,categoria,nivel,fecha_de_ingreso,grupo,estado,area,puesto';

beforeEach(function (): void {
    $this->union = Tenant::factory()->union()->create();
    $this->owner = ownerOf($this->union);
});

/** @param  list<string>  $rows */
function unionFile(array $rows, string $header = UNION_HEADER): UploadedFile
{
    return UploadedFile::fake()->createWithContent('padron.csv', implode("\n", [$header, ...$rows])."\n");
}

/** @param  list<string>  $rows */
function importUnion(mixed $test, array $rows, string $header = UNION_HEADER, bool $confirm = true): void
{
    $test->actingAs($test->owner)
        ->post(route('tenant.employees.import.store', $test->union), [...($confirm ? ['confirm' => '1'] : []), 'file' => unionFile($rows, $header)])
        ->assertSessionHasNoErrors();
}

/** @return array<string, mixed> the flashed import summary */
function unionFlash(): array
{
    $flash = session(SessionKey::FLASH_DATA);

    return is_array($flash) && is_array($flash['import'] ?? null) ? $flash['import'] : [];
}

function importedUnionPerson(string $number): Employee
{
    return Employee::query()->withoutGlobalScopes()->where('employee_number', $number)->sole();
}

it('imports a union file with only the company ID, category, level, hire date and group', function (): void {
    importUnion($this, [
        'E-001,Operador,A,2024-03-15,Sindicalizado',
        'E-002,Operador,B,15/03/2023,Confianza',
    ], 'numero_de_empleado,categoria,nivel,fecha_de_ingreso,grupo');

    $first = importedUnionPerson('E-001');
    $second = importedUnionPerson('E-002');

    expect([$first->name, $first->email, $first->gender, $first->work_center_id, $first->area_id, $first->job_title])->each->toBeNull()
        ->and($first->hired_on?->toDateString())->toBe('2024-03-15')
        ->and($first->staff_group?->value)->toBe('unionized')
        ->and($second->hired_on?->toDateString())->toBe('2023-03-15')
        ->and($second->staff_group?->value)->toBe('trusted')
        ->and(JobGrade::query()->withoutGlobalScopes()->orderBy('level')->get()->map(fn (JobGrade $grade): string => $grade->category.$grade->level)->all())->toBe(['OperadorA', 'OperadorB']);
});

it('reads the whole union template: name, area, job title and state too', function (): void {
    importUnion($this, ['E-001,Ana López,Operador,A,2024-03-15,Sindicalizado,Querétaro,Producción,Operadora']);

    $employee = importedUnionPerson('E-001');

    expect($employee->name)->toBe('Ana López')
        ->and($employee->job_title)->toBe('Operadora')
        ->and($employee->state)->toBe('QUE')
        ->and(Area::query()->withoutGlobalScopes()->sole()->name)->toBe('Producción')
        ->and($employee->area_id)->toBe(Area::query()->withoutGlobalScopes()->sole()->id);
});

it('needs only the company ID column, and tells which one is missing', function (): void {
    importUnion($this, ['E-001'], 'numero_de_empleado');
    expect(importedUnionPerson('E-001')->name)->toBeNull();

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->union), ['confirm' => '1', 'file' => unionFile(['Ana,Operador'], 'nombre,categoria')])
        ->assertSessionHasErrors(['file' => 'Falta la columna «numero_de_empleado». Usa la plantilla.']);
});

it('updates by company ID without caring about case, leaving what the file has no column for', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->union->id, 'category' => 'Operador', 'level' => 'A']);
    $area = Area::factory()->create(['tenant_id' => $this->union->id, 'name' => 'Producción']);
    Employee::factory()->create([
        'tenant_id' => $this->union->id, 'work_center_id' => null, 'area_id' => $area->id, 'gender' => null, 'email' => null, 'mobile' => null,
        'name' => 'Ana López', 'job_title' => 'Operadora', 'employee_number' => 'e-001', 'job_grade_id' => $grade->id, 'hired_on' => '2024-03-15', 'staff_group' => 'trusted', 'state' => 'JAL',
    ]);

    importUnion($this, ['E-001,2020-01-02'], 'numero_de_empleado,fecha_de_ingreso');

    $employee = Employee::query()->withoutGlobalScopes()->sole();

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and($employee->hired_on?->toDateString())->toBe('2020-01-02')
        ->and([$employee->name, $employee->job_title, $employee->area_id, $employee->job_grade_id, $employee->staff_group?->value, $employee->state])
        ->toBe(['Ana López', 'Operadora', $area->id, $grade->id, 'trusted', 'JAL']);
});

it('clears the name of someone the file lists with an empty name cell', function (): void {
    Employee::factory()->create(['tenant_id' => $this->union->id, 'work_center_id' => null, 'area_id' => null, 'gender' => null, 'email' => null, 'mobile' => null, 'name' => 'Ana López', 'employee_number' => 'E-001']);

    importUnion($this, ['E-001,']);

    expect(importedUnionPerson('E-001')->name)->toBeNull();
});

it('requires the company ID in every row and rejects one repeated in the file without caring about case', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->union), ['confirm' => '1', 'file' => unionFile([
            ',Ana López,,,,,,,',
            'E-001,Beto,,,,,,,',
            'e-001,Carla,,,,,,,',
            'E-003,Dani,Operador,,,,,,',
        ])])
        ->assertRedirect();

    expect(unionFlash())->toBe(['status' => 'invalid', 'rows' => 4, 'created' => 0, 'updated' => 0, 'errors' => [
        ['row' => 2, 'messages' => ['El campo ID de la empresa es obligatorio.']],
        ['row' => 4, 'messages' => ['El ID de la empresa se repite en la fila 3.']],
        ['row' => 5, 'messages' => ['El campo nivel es obligatorio cuando categoría esté presente.']],
    ]])->and(Employee::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('previews a union file without writing anything', function (): void {
    importUnion($this, ['E-001,Ana,Operador,A,,,,,'], confirm: false);

    expect(unionFlash())->toBe(['status' => 'ready', 'rows' => 1, 'created' => 1, 'updated' => 0, 'errors' => []])
        ->and(Employee::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(JobGrade::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('ignores the company-only columns a union file carries', function (): void {
    importUnion($this, ['E-001,ana@acme.mx,5512345678,Mujer,Planta Inexistente'], 'numero_de_empleado,correo,celular,genero,centro_de_trabajo');

    $employee = importedUnionPerson('E-001');

    expect([$employee->email, $employee->mobile, $employee->gender, $employee->work_center_id])->each->toBeNull();
});

it('does not announce the people of a union import to the evaluations', function (): void {
    Event::fake([EmployeeCreated::class, EmployeeMoved::class]);
    Employee::factory()->create(['tenant_id' => $this->union->id, 'work_center_id' => null, 'area_id' => null, 'employee_number' => 'E-002']);

    importUnion($this, ['E-001,Ana', 'E-002,Beto'], 'numero_de_empleado,nombre');

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(2);
    Event::assertNotDispatched(EmployeeCreated::class);
    Event::assertNotDispatched(EmployeeMoved::class);
});

it('serves a union template without the company-only columns', function (): void {
    $response = $this->actingAs($this->owner)->get(route('tenant.employees.import.template', $this->union))->assertOk();
    $content = $response->getContent();

    expect($response->headers->get('Content-Disposition'))->toContain('plantilla-padron.csv')
        ->and($content)->toStartWith("\u{FEFF}".UNION_HEADER."\n")
        ->and($content)->not->toContain('correo')
        ->and($content)->not->toContain('celular')
        ->and($content)->not->toContain('genero')
        ->and($content)->not->toContain('centro_de_trabajo');

    // The template is a valid file as it comes.
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->union), ['confirm' => '1', 'file' => UploadedFile::fake()->createWithContent('plantilla.csv', $content)])
        ->assertSessionHasNoErrors();
    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('keeps the company template and the company columns as they were', function (): void {
    $company = Tenant::factory()->create();
    $owner = ownerOf($company);

    $template = $this->actingAs($owner)->get(route('tenant.employees.import.template', $company))->assertOk()->getContent();
    expect($template)->toStartWith("\u{FEFF}nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado\n");

    $this->actingAs($owner)
        ->post(route('tenant.employees.import.store', $company), ['confirm' => '1', 'file' => unionFile(['E-001,Ana'], 'numero_de_empleado,nombre')])
        ->assertSessionHasErrors(['file' => 'Falta la columna «genero». Usa la plantilla.']);

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('never lets a company import a person without contact, work center or area', function (): void {
    $company = Tenant::factory()->create();

    $this->actingAs(ownerOf($company))
        ->post(route('tenant.employees.import.store', $company), ['confirm' => '1', 'file' => unionFile(['Ana,,,,,,,E-001'], 'nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado')])
        ->assertRedirect();

    expect(unionFlash()['status'])->toBe('invalid')
        ->and(Employee::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('keeps two accounts\' company IDs apart in an import', function (): void {
    $company = Tenant::factory()->create();
    Employee::factory()->create(['tenant_id' => $company->id, 'employee_number' => 'E-001', 'name' => 'De la empresa']);

    importUnion($this, ['E-001,Del sindicato'], 'numero_de_empleado,nombre');

    expect(Employee::query()->withoutGlobalScopes()->where('tenant_id', $company->id)->sole()->name)->toBe('De la empresa')
        ->and(Employee::query()->withoutGlobalScopes()->where('tenant_id', $this->union->id)->sole()->name)->toBe('Del sindicato');
});
