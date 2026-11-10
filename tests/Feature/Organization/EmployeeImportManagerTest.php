<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\PlanEmployeeImport;
use App\Modules\Organization\Data\EmployeeImportPlan;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\EmployeeCsv;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Http\UploadedFile;
use Inertia\Support\SessionKey;

mutates(PlanEmployeeImport::class, EmployeeCsv::class);

const MANAGER_HEADER = 'nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado,fecha_de_ingreso,jefe';

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta']);
    workIn($this->tenant);
});

/** @param  list<string>  $rows */
function managerPlan(array $rows, string $header = MANAGER_HEADER): EmployeeImportPlan
{
    return resolve(PlanEmployeeImport::class)->handle(EmployeeCsv::read(implode("\n", [$header, ...$rows])."\n")['rows']);
}

it('offers a template with the hire date and the manager, which the import reads', function (): void {
    $template = $this->actingAs($this->owner)->get(route('tenant.employees.import.template', $this->tenant))->assertOk()->getContent();

    expect($template)->toBe("\u{FEFF}".MANAGER_HEADER."\nAna López,ana@empresa.mx,55 1234 5678,Mujer,Planta,Producción,Operadora,A-001,2024-03-15,A-000\n")
        ->and(EmployeeCsv::COLUMNS)->toBe(explode(',', MANAGER_HEADER));
});

it('plans each row under the manager its cell names: someone the company has or another row of the file', function (): void {
    $boss = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'X-9', 'email' => 'boss@acme.mx']);
    $moved = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'M-1', 'email' => 'moved@acme.mx']);

    $plan = managerPlan([
        'Ana López,ana@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,,nueva@acme.mx',
        'Beto Díaz,beto@acme.mx,,Hombre,Planta,Producción,Operador,B-2,,x-9',
        'Nueva Jefa,nueva@acme.mx,,Mujer,Planta,Producción,Gerente,,,M-1',
        'Movida,moved@acme.mx,,Mujer,Planta,Producción,Directora,M-1,,',
    ]);

    expect($plan->isValid())->toBeTrue()
        ->and(array_map(fn ($row): array => [$row->managerId, $row->managerRow], $plan->rows))->toBe([
            // The manager comes later in the file and is new there, known by her email alone.
            [null, 2],
            [$boss->id, null],
            // A manager the file also updates is who they already are.
            [$moved->id, null],
            [null, null],
        ])
        ->and($plan->rows[0]->absent)->not->toContain('manager_id')
        // No column carries the recognized seniority.
        ->and($plan->rows[0]->absent)->toContain('seniority_on');
});

it('leaves the manager out of what a file without the column changes', function (): void {
    $plan = managerPlan(['Ana López,ana@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,2024-03-15'], 'nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado,fecha_de_ingreso');

    expect($plan->isValid())->toBeTrue()
        ->and($plan->rows[0]->absent)->toContain('manager_id')
        ->and($plan->rows[0]->absent)->not->toContain('hired_on')
        ->and($plan->rows[0]->data->hiredOn?->toDateString())->toBe('2024-03-15')
        ->and([$plan->rows[0]->managerId, $plan->rows[0]->managerRow])->toBe([null, null]);
});

it('reports a manager that cannot be in its row, by the spreadsheet\'s numbering, and plans nothing with it', function (): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'G-1', 'deactivated_at' => now()]);
    Employee::factory()->create(['employee_number' => 'OTRA-1']);

    $plan = managerPlan([
        'Ana López,ana@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,,B-2',
        'Beto Díaz,beto@acme.mx,,Hombre,Planta,Producción,Operador,B-2,,A-1',
        'Carla Ruiz,carla@acme.mx,,Mujer,Planta,Producción,Operadora,C-3,,G-1',
        // Another company's person is nobody here.
        'Dani Paz,dani@acme.mx,,Otro,Planta,Producción,Auxiliar,D-4,,OTRA-1',
        'Eva Mar,eva@acme.mx,,Mujer,Planta,Producción,Auxiliar,E-5,,e-5',
        'Sin Genero,sin@acme.mx,,,Planta,Producción,Auxiliar,F-6,,',
        'Gil Sol,gil@acme.mx,,Hombre,Planta,Producción,Auxiliar,H-7,,',
    ]);

    expect($plan->errors)->toBe([
        ['row' => 2, 'messages' => ['El jefe forma un ciclo con la fila 3.']],
        ['row' => 3, 'messages' => ['El jefe forma un ciclo con la fila 2.']],
        ['row' => 4, 'messages' => ['«G-1» está dado de baja y no puede ser jefe.']],
        ['row' => 5, 'messages' => ['No hay una persona con número o correo «OTRA-1» para ser su jefe.']],
        ['row' => 6, 'messages' => ['Una persona no puede ser su propio jefe.']],
        ['row' => 7, 'messages' => ['El género debe ser Mujer, Hombre u Otro.']],
    ])->and($plan->total)->toBe(7)
        ->and(count($plan->rows))->toBe(1)
        ->and($plan->rows[0]->data->name)->toBe('Gil Sol');
});

it('lets two people the company has swap places in one file', function (): void {
    $ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'A-1', 'email' => 'ana@acme.mx']);
    $beto = Employee::factory()->reportingTo($ana)->create(['employee_number' => 'B-2', 'email' => 'beto@acme.mx']);
    // Carla is not in the file: her line to Beto stays, and closes a cycle with what the second file says.
    Employee::factory()->reportingTo($beto)->create(['employee_number' => 'C-3']);

    $swap = managerPlan([
        'Ana López,ana@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,,B-2',
        'Beto Díaz,beto@acme.mx,,Hombre,Planta,Producción,Operador,B-2,,',
    ]);

    expect($swap->isValid())->toBeTrue()
        ->and([$swap->rows[0]->managerId, $swap->rows[1]->managerId])->toBe([$beto->id, null]);

    $cycle = resolve(PlanEmployeeImport::class)->handle(EmployeeCsv::read(MANAGER_HEADER."\nAna López,ana@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,,C-3\n")['rows']);

    expect($cycle->errors)->toBe([['row' => 2, 'messages' => ['El jefe forma un ciclo con la fila 2.']]]);
});

it('shows the manager problems in the import summary', function (): void {
    $file = UploadedFile::fake()->createWithContent('empleados.csv', MANAGER_HEADER."\nAna López,ana@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,,Z-404\n");

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => $file])
        ->assertSessionHasNoErrors();

    $flash = session(SessionKey::FLASH_DATA);

    expect($flash['import']['status'])->toBe('invalid')
        ->and($flash['import']['errors'])->toBe([['row' => 2, 'messages' => ['No hay una persona con número o correo «Z-404» para ser su jefe.']]])
        ->and(Employee::query()->count())->toBe(0);
});

it('reads the company\'s people once, whatever the file asks', function (): void {
    Employee::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
    // As many as without the column: the manager and the status come with the employees the plan already read.
    $this->expectsDatabaseQueryCount(3);

    managerPlan(['Ana López,ana@acme.mx,,Mujer,Planta,Producción,Operadora,A-1,,', 'Beto Díaz,beto@acme.mx,,Hombre,Planta,Producción,Operador,B-2,,A-1']);
});

it('ignores a manager column in a union\'s file', function (): void {
    $union = Tenant::factory()->union()->create();
    workIn($union);

    $plan = resolve(PlanEmployeeImport::class)->handle(EmployeeCsv::read("numero_de_empleado,jefe\nE-1,E-404\n", union: true)['rows']);

    expect($plan->isValid())->toBeTrue()
        ->and($plan->rows[0]->absent)->toContain('manager_id')
        ->and($plan->rows[0]->managerId)->toBeNull();
});
