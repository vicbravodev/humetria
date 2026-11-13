<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\File;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** A CSV on disk for the browser to upload, in a folder of this process that is deleted after each test. */
function csvOnDisk(string $content): string
{
    $path = storage_path('framework/testing/csv-'.getmypid().'/'.uniqid().'.csv');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $content);

    return $path;
}

beforeEach(function (): void {
    signInToPanel();
    WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->sole())->create(['name' => 'Planta Norte']);
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('framework/testing/csv-'.getmypid()));
});

it('explains how to fill the file and points to the work centers it can name', function (bool $dark, bool $mobile): void {
    visitPage('organization/employee-import', '/admin/acme/employees/import', $dark, $mobile)
        ->assertSee('Cómo llenarlo')
        ->assertSee('escrito como en Centros de trabajo.')
        ->assertSee('Descargar plantilla');
})->with($looks);

it('checks a file, then imports it once confirmed', function (): void {
    $path = csvOnDisk("nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado\nAna López,ana@acme.mx,,Mujer,Planta Norte,Producción,Operadora,A-1\nBeto Díaz,,5512345678,Hombre,Planta Norte,Ventas,Vendedor,\n");

    visit('/admin/acme/employees/import')
        ->attach('file', $path)
        ->press('Revisar archivo')
        ->assertSee('Listo para importar 2 empleados: 2 nuevos y 0 actualizados.')
        ->press('Importar 2 empleados')
        ->assertPathIs('/admin/acme/employees')
        ->assertSee('Se importaron 2 empleados: 2 nuevos y 0 actualizados.')
        ->assertSee('Beto Díaz')
        ->assertNoJavaScriptErrors();

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('lists the rows to fix and imports nothing', function (bool $dark, bool $mobile): void {
    $path = csvOnDisk("nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado\nAna López,,,Mujer,Planta Sur,Producción,Operadora,\n");

    visitPage('organization/employee-import', '/admin/acme/employees/import', $dark, $mobile, fn ($page) => $page
        ->attach('file', $path)
        ->press('Revisar archivo')
        ->assertSee('No se importó nada'))
        ->assertSee('Corrige 1 fila')
        ->assertSeeIn('[aria-labelledby="import-errors"] tbody tr', 'Fila 2')
        ->assertSee('Escribe un correo o un celular.')
        ->assertSee('No hay un centro de trabajo «Planta Sur».')
        ->assertDontSee('Importar 1 empleado');

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(0);
})->with($looks);

it('shows what changes in a person who is updated without having to open anything', function (): void {
    $center = WorkCenter::query()->withoutGlobalScopes()->where('name', 'Planta Norte')->sole();
    Employee::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => 'Alma Torres', 'email' => 'alma@acme.mx', 'mobile' => null, 'gender' => 'female', 'job_title' => 'Operador', 'employee_number' => 'A-1', 'hired_on' => null]);
    $path = csvOnDisk("nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado,fecha_de_ingreso,jefe\nAlma Torres,alma@acme.mx,,Mujer,Planta Norte,Producción,Operador Senior,A-1,,\n");

    visit('/admin/acme/employees/import')
        ->attach('file', $path)
        ->press('Revisar archivo')
        ->assertSee('Qué cambia en 1 persona')
        // A table of Campo / Antes / Después (visual round 5, point 4), not a sentence with an arrow.
        ->assertScript('() => [...document.querySelectorAll(\'#import-changes ~ * table th, section[aria-labelledby="import-changes"] table th\')].map((cell) => cell.textContent.trim()).join()', 'Campo,Antes,Después')
        ->assertScript('() => [...document.querySelectorAll(\'section[aria-labelledby="import-changes"] tbody tr\')].some((row) => [...row.cells].map((cell) => cell.textContent.trim()).join("|") === "Puesto|Operador|Operador Senior")', true)
        ->assertNoJavaScriptErrors();
});

it('marks in the table of changes the value that the file erases', function (): void {
    $center = WorkCenter::query()->withoutGlobalScopes()->where('name', 'Planta Norte')->sole();
    Employee::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => 'Alma Torres', 'email' => 'alma@acme.mx', 'mobile' => '5511111111', 'gender' => 'female', 'job_title' => 'Operador', 'employee_number' => 'A-1', 'hired_on' => null]);
    $path = csvOnDisk("nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado,fecha_de_ingreso,jefe\nAlma Torres,alma@acme.mx,,Mujer,Planta Norte,Producción,Operador,A-1,,\n");

    visit('/admin/acme/employees/import')
        ->attach('file', $path)
        ->press('Revisar archivo')
        ->assertSee('Qué cambia en 1 persona')
        ->assertScript(<<<'JS'
            () => {
                const row = [...document.querySelectorAll('section[aria-labelledby="import-changes"] tbody tr')].find((tr) => tr.cells[0].textContent.trim() === 'Celular');
                const probe = document.createElement('div');
                probe.style.backgroundColor = 'var(--warning)';
                document.body.append(probe);
                const warning = getComputedStyle(probe).backgroundColor;
                probe.remove();
                const erased = row.cells[2].querySelector('[data-erased]');
                return row.cells[1].textContent.includes('5511111111') && erased !== null && getComputedStyle(erased).backgroundColor === warning && erased.textContent.includes('Se borra');
            }
            JS, true);
});
