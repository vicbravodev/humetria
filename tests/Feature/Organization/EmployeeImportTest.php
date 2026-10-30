<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\ImportEmployees;
use App\Modules\Organization\Http\Controllers\EmployeeImportController;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\EmployeeCsv;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(EmployeeImportController::class, ImportEmployees::class, EmployeeCsv::class);

const CSV_HEADER = 'nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado';

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
});

/** @param  list<string>  $rows */
function csvFile(array $rows, string $header = CSV_HEADER): UploadedFile
{
    return UploadedFile::fake()->createWithContent('empleados.csv', implode("\n", [$header, ...$rows])."\n");
}

function storedEmployees(): array
{
    return Employee::query()->withoutGlobalScopes()->orderBy('name')->pluck('name')->all();
}

function importFlash(): array
{
    $flash = session(SessionKey::FLASH_DATA);

    return is_array($flash) && is_array($flash['import'] ?? null) ? $flash['import'] : [];
}

it('lives at /admin/{slug}/employees/import', function (): void {
    expect(route('tenant.employees.import.create', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/employees/import')
        ->and(route('tenant.employees.import.store', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/employees/import')
        ->and(route('tenant.employees.import.template', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/employees/import/template');
});

it('shows the import page, saying whether the company has work centers to name', function (): void {
    $this->actingAs($this->owner)
        ->get(route('tenant.employees.import.create', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organization/employee-import')
            ->where('maxRows', 2000)
            ->where('hasWorkCenters', true)
            ->missing('workCenters'));
});

it('offers a template that Excel opens with its accents', function (): void {
    $response = $this->actingAs($this->owner)->get(route('tenant.employees.import.template', $this->tenant))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('plantilla-empleados.csv')
        ->and($response->getContent())->toStartWith("\u{FEFF}".CSV_HEADER."\n")
        ->and($response->getContent())->toContain('Planta Querétaro');
});

it('previews a valid file without storing anything', function (): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'email' => 'beto@acme.mx']);

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['file' => csvFile([
            'Ana López,ana@acme.mx,55 1234 5678,Mujer,Planta Querétaro,Producción,Operadora,A-1',
            'Beto Díaz,BETO@acme.mx,,hombre,planta queretaro,Almacén,Almacenista,',
        ])])
        ->assertRedirect();

    expect(importFlash())->toBe(['status' => 'ready', 'rows' => 2, 'created' => 1, 'updated' => 1, 'errors' => []])
        ->and(Employee::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Area::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('imports a valid file once confirmed: adds new people and updates those it finds by number or email', function (): void {
    $byNumber = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'employee_number' => 'a-1', 'job_title' => 'Viejo']);
    $byEmail = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'email' => 'beto@acme.mx']);
    Log::spy();

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => csvFile([
            'Ana López,ana@acme.mx,5512345678,Mujer,Planta Querétaro,Producción,Operadora,A-1',
            'Beto Díaz,beto@acme.mx,,Hombre,Planta Querétaro,producción,Almacenista,',
            'Carla Ruiz,,5587654321,Otro,Planta Querétaro,Ventas,Vendedora,C-3',
        ])])
        ->assertRedirect(route('tenant.employees.index', $this->tenant))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Se importaron 3 empleados: 1 nuevo y 2 actualizados.']]);

    workIn($this->tenant);

    expect($byNumber->fresh()?->only(['name', 'job_title', 'email']))->toBe(['name' => 'Ana López', 'job_title' => 'Operadora', 'email' => 'ana@acme.mx'])
        ->and($byEmail->fresh()?->name)->toBe('Beto Díaz')
        ->and(Employee::query()->where('employee_number', 'C-3')->sole()->mobile)->toBe('5587654321')
        // "Producción" and "producción" are one area, created once.
        ->and(Area::query()->whereRaw('lower(name) in (?, ?)', ['producción', 'ventas'])->orderBy('name')->pluck('name')->all())->toBe(['Producción', 'Ventas']);

    Log::shouldHaveReceived('info')->with('employees.imported', ['created' => 1, 'updated' => 2]);
});

it('reports every row with a problem and imports nothing, even when confirmed', function (): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'email' => 'tomado@acme.mx', 'employee_number' => 'T-1']);

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => csvFile([
            'Ana López,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,',
            'Sin Contacto,,,Mujer,Planta Querétaro,Producción,Operadora,',
            'Mal Correo,correo,,Mujer,Planta Querétaro,Producción,Operadora,',
            'Centro Raro,raro@acme.mx,,Mujer,Planta Marte,Producción,Operadora,',
            'Género Raro,genero@acme.mx,,X,Planta Querétaro,Producción,Operadora,',
            'Ana Repetida,ANA@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,',
            'Correo Tomado,tomado@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,N-9',
        ])])
        ->assertRedirect();

    expect(importFlash())->toBe(['status' => 'invalid', 'rows' => 7, 'created' => 0, 'updated' => 0, 'errors' => [
        ['row' => 3, 'messages' => ['Escribe un correo o un celular.']],
        ['row' => 4, 'messages' => ['El campo correo electrónico debe ser un correo electrónico válido.']],
        ['row' => 5, 'messages' => ['No hay un centro de trabajo «Planta Marte».']],
        ['row' => 6, 'messages' => ['El género debe ser Mujer, Hombre u Otro.']],
        ['row' => 7, 'messages' => ['El correo se repite en la fila 2.']],
        ['row' => 8, 'messages' => ['Ya hay un empleado con este correo.']],
    ]])->and(storedEmployees())->toHaveCount(1);
});

it('rejects a file it cannot read as a whole', function (string $content, string $message): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['file' => UploadedFile::fake()->createWithContent('e.csv', $content)])
        ->assertSessionHasErrors(['file' => $message]);
})->with([
    'missing column' => ["nombre,correo,celular,genero,area,puesto\nAna,a@a.mx,,Mujer,X,Y\n", 'Falta la columna «centro_de_trabajo». Usa la plantilla.'],
    'no rows' => [CSV_HEADER."\n\n", 'El archivo no tiene empleados.'],
    'too many rows' => [CSV_HEADER."\n".str_repeat("Ana,a@a.mx,,Mujer,Planta Querétaro,X,Y,\n", 2001), 'El archivo tiene más de 2,000 empleados. Divídelo en varios.'],
]);

it('accepts a file saved by Excel: semicolons, Windows encoding and a byte order mark', function (): void {
    $content = mb_convert_encoding(str_replace(',', ';', CSV_HEADER)."\r\nJosé Núñez;jose@acme.mx;;Hombre;Planta Querétaro;Producción;Operador;\r\n", 'Windows-1252', 'UTF-8');

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => UploadedFile::fake()->createWithContent('e.csv', "\xEF\xBB\xBF".$content)])
        ->assertSessionHasNoErrors();

    expect(storedEmployees())->toBe(['José Núñez']);
});

it('requires a CSV file of at most 1 MB', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['file' => UploadedFile::fake()->create('e.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['file' => UploadedFile::fake()->create('e.csv', 1025, 'text/csv')])
        ->assertSessionHasErrors('file');
});

it('never matches another company\'s work centers or employees', function (): void {
    WorkCenter::factory()->create(['name' => 'Planta Ajena']);
    $foreign = Employee::factory()->create(['email' => 'ana@acme.mx', 'name' => 'Ajena']);

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => csvFile([
            'Ana López,ana@acme.mx,,Mujer,Planta Querétaro,Producción,Operadora,',
        ])])
        ->assertRedirect(route('tenant.employees.index', $this->tenant));

    expect(Employee::query()->withoutGlobalScopes()->findOrFail($foreign->id)->name)->toBe('Ajena')
        ->and(Employee::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole()->name)->toBe('Ana López');

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['file' => csvFile(['Beto,beto@acme.mx,,Hombre,Planta Ajena,X,Y,'])]);

    expect(importFlash()['errors'])->toBe([['row' => 2, 'messages' => ['No hay un centro de trabajo «Planta Ajena».']]]);
});

it('is for those who manage the organization', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($viewer)->get(route('tenant.employees.import.create', $this->tenant))->assertForbidden();
    $this->actingAs($viewer)->get(route('tenant.employees.import.template', $this->tenant))->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => csvFile(['Ana,ana@acme.mx,,Mujer,Planta Querétaro,X,Y,'])])->assertForbidden();

    expect(storedEmployees())->toBe([]);
});
