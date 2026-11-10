<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Actions\ImportEmployees;
use App\Modules\Organization\Actions\PlanEmployeeImport;
use App\Modules\Organization\Data\EmployeeImportPlan;
use App\Modules\Organization\Data\EmployeeImportRow;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\EmployeeCsv;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

mutates(ImportEmployees::class);

const WRITE_HEADER = 'nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado,jefe';

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta']);
    workIn($this->tenant);
});

/** @param  list<string>  $rows */
function writePlan(array $rows, string $header = WRITE_HEADER): EmployeeImportPlan
{
    $plan = resolve(PlanEmployeeImport::class)->handle(EmployeeCsv::read(implode("\n", [$header, ...$rows])."\n")['rows']);
    expect($plan->errors)->toBe([]);

    return $plan;
}

/** @param  list<string>  $rows */
function importManagers(array $rows, string $header = WRITE_HEADER): void
{
    resolve(ImportEmployees::class)->handle(writePlan($rows, $header));
}

function numbered(string $number): Employee
{
    return Employee::query()->where('employee_number', $number)->sole();
}

function inPlant(mixed $test, string $number, ?Employee $manager = null): Employee
{
    return Employee::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->plant->id, 'employee_number' => $number, 'email' => mb_strtolower($number).'@acme.mx', 'manager_id' => $manager?->id]);
}

/** @return list<array{0: string, 1: string|null, 2: string|null}> person, from and to of each manager change in the audit trail */
function managerChanges(): array
{
    return AuditEntry::query()->where('event', 'employee.manager_changed')->get()
        ->map(fn (AuditEntry $entry): array => [$entry->subject_id, $entry->metadata['from_manager_id'], $entry->metadata['to_manager_id']])
        ->sortBy(0)->values()->all();
}

it('links the reports to a manager who comes later in the file, new or not', function (): void {
    $old = inPlant($this, 'O-1');
    Log::spy();

    importManagers([
        'Ana López,ana@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,jefa@acme.mx',
        'Beto Díaz,beto@acme.mx,,Hombre,Planta,Producción,Operador,B-2,jefa@acme.mx',
        'Carla Ruiz,carla@acme.mx,,Mujer,Planta,Producción,Operadora,C-3,O-1',
        'La Jefa,jefa@acme.mx,,Mujer,Planta,Producción,Gerente,,o-1',
    ]);

    $boss = Employee::query()->where('email', 'jefa@acme.mx')->sole();

    expect(numbered('A-1')->manager_id)->toBe($boss->id)
        ->and(numbered('B-2')->manager_id)->toBe($boss->id)
        ->and(numbered('C-3')->manager_id)->toBe($old->id)
        ->and($boss->manager_id)->toBe($old->id)
        ->and($old->refresh()->manager_id)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'employee.manager_changed')->count())->toBe(4);

    Log::shouldHaveReceived('info')->with('employees.imported', ['created' => 4, 'updated' => 0, 'managers_changed' => 4])->once();
});

it('lets two people swap places in one file, keeping in the audit trail whom each really reported to', function (): void {
    $carla = inPlant($this, 'C-3');
    $ana = inPlant($this, 'A-1', $carla);
    $beto = inPlant($this, 'B-2', $ana);

    importManagers([
        'Ana López,a-1@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,B-2',
        'Beto Díaz,b-2@acme.mx,,Hombre,Planta,Producción,Operador,B-2,C-3',
    ]);

    expect($ana->refresh()->manager_id)->toBe($beto->id)
        ->and($beto->refresh()->manager_id)->toBe($carla->id)
        ->and(managerChanges())->toEqualCanonicalizing([
            [$ana->id, $carla->id, $beto->id],
            [$beto->id, $ana->id, $carla->id],
        ]);
});

it('keeps the manager when the file has no column for it, and removes it when the cell is empty', function (): void {
    $carla = inPlant($this, 'C-3');
    $ana = inPlant($this, 'A-1', $carla);
    $beto = inPlant($this, 'B-2', $carla);
    Log::spy();

    importManagers(['Ana López,a-1@acme.mx,,Mujer,Planta,Producción,Operadora,A-1'], 'nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado');

    expect($ana->refresh()->manager_id)->toBe($carla->id)
        ->and(AuditEntry::query()->count())->toBe(0);
    // A file that says nothing of managers does not count them.
    Log::shouldHaveReceived('info')->with('employees.imported', ['created' => 0, 'updated' => 1])->once();

    importManagers([
        'Ana López,a-1@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,',
        'Beto Díaz,b-2@acme.mx,,Hombre,Planta,Producción,Operador,B-2,c-3',
    ]);

    expect($ana->refresh()->manager_id)->toBeNull()
        ->and($beto->refresh()->manager_id)->toBe($carla->id)
        // Only who changed: Beto still reports to Carla.
        ->and(managerChanges())->toBe([[$ana->id, $carla->id, null]]);
    Log::shouldHaveReceived('info')->with('employees.imported', ['created' => 0, 'updated' => 2, 'managers_changed' => 1])->once();
});

it('changes nobody\'s manager when a later one cannot be written: all or nothing', function (): void {
    $carla = inPlant($this, 'C-3');
    $dora = inPlant($this, 'D-4');
    $ana = inPlant($this, 'A-1', $carla);
    $beto = inPlant($this, 'B-2');
    $plan = writePlan([
        'Ana López,a-1@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,D-4',
        'Beto Díaz,b-2@acme.mx,,Hombre,Planta,Producción,Operador,B-2,',
        'Nuevo Luz,nuevo@acme.mx,,Hombre,Planta,Producción,Operador,N-9,',
    ]);
    // A plan nobody checked: Beto as his own manager, which the database refuses.
    $rows = [$plan->rows[0], new EmployeeImportRow($plan->rows[1]->data, $beto->id, $plan->rows[1]->absent, $beto->id), $plan->rows[2]];

    expect(fn () => resolve(ImportEmployees::class)->handle(new EmployeeImportPlan(3, $rows, [])))->toThrow(QueryException::class);

    expect($ana->refresh()->manager_id)->toBe($carla->id)
        ->and($ana->name)->not->toBe('Ana López')
        ->and($dora->refresh()->manager_id)->toBeNull()
        ->and(Employee::query()->where('employee_number', 'N-9')->exists())->toBeFalse()
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('never touches the recognized seniority', function (): void {
    $ana = inPlant($this, 'A-1');
    $ana->update(['seniority_on' => '2019-01-10']);

    importManagers(['Ana López,a-1@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,']);

    expect($ana->refresh()->seniority_on?->toDateString())->toBe('2019-01-10')
        ->and($ana->name)->toBe('Ana López');
});
