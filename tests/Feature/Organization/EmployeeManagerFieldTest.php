<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Actions\CreateEmployee;
use App\Modules\Organization\Actions\ListEmployees;
use App\Modules\Organization\Actions\UpdateEmployee;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

mutates(CreateEmployee::class, UpdateEmployee::class, ListEmployees::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    // Ana reports to Beto, who reports to Carla.
    $this->carla = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Carla Ruiz', 'job_title' => 'Directora']);
    $this->beto = Employee::factory()->reportingTo($this->carla)->create(['name' => 'Beto Paz']);
    $this->ana = Employee::factory()->reportingTo($this->beto)->create(['name' => 'Ana Sol']);
});

function managerForm(WorkCenter $workCenter, array $overrides = []): array
{
    return [
        'name' => 'Dora Luz',
        'email' => 'dora@acme.mx',
        'gender' => 'female',
        'work_center_id' => $workCenter->id,
        'area' => 'Producción',
        'job_title' => 'Operadora',
        ...$overrides,
    ];
}

it('adds an employee under a manager, with recognized seniority, and audits the manager', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), managerForm($this->plant, ['manager_id' => $this->carla->id, 'seniority_on' => '2019-01-10']))
        ->assertSessionHasNoErrors();

    $dora = Employee::query()->withoutGlobalScopes()->where('email', 'dora@acme.mx')->sole();
    $entry = AuditEntry::query()->where('event', 'employee.manager_changed')->sole();

    expect($dora->manager_id)->toBe($this->carla->id)
        ->and($dora->seniority_on?->toDateString())->toBe('2019-01-10')
        ->and($entry->subject_id)->toBe($dora->id)
        ->and($entry->metadata)->toEqual(['from_manager_id' => null, 'to_manager_id' => $this->carla->id]);
});

it('adds an employee with no manager without touching the audit trail', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), managerForm($this->plant, ['manager_id' => '', 'seniority_on' => '']))
        ->assertSessionHasNoErrors();

    $dora = Employee::query()->withoutGlobalScopes()->where('email', 'dora@acme.mx')->sole();

    expect($dora->manager_id)->toBeNull()
        ->and($dora->seniority_on)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('changes and removes the manager of an employee when correcting them', function (): void {
    $form = managerForm($this->plant, ['name' => 'Ana Sol', 'email' => 'ana@acme.mx']);

    $this->actingAs($this->owner)
        ->put(route('tenant.employees.update', [$this->tenant, $this->ana]), [...$form, 'manager_id' => $this->carla->id, 'seniority_on' => '2020-02-02'])
        ->assertSessionHasNoErrors();

    expect($this->ana->refresh()->manager_id)->toBe($this->carla->id)
        ->and($this->ana->seniority_on?->toDateString())->toBe('2020-02-02')
        ->and(AuditEntry::query()->where('event', 'employee.manager_changed')->sole()->metadata)
        ->toEqual(['from_manager_id' => $this->beto->id, 'to_manager_id' => $this->carla->id]);

    $this->actingAs($this->owner)
        ->put(route('tenant.employees.update', [$this->tenant, $this->ana]), $form)
        ->assertSessionHasNoErrors();

    expect($this->ana->refresh()->manager_id)->toBeNull()
        ->and($this->ana->seniority_on)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'employee.manager_changed')->count())->toBe(2);
});

it('rejects a manager who may not be it, and changes nothing', function (Closure $manager, string $message): void {
    $this->actingAs($this->owner)
        ->put(route('tenant.employees.update', [$this->tenant, $this->carla]), managerForm($this->plant, ['name' => 'Otro nombre', 'manager_id' => $manager($this)]))
        ->assertSessionHasErrors(['manager_id' => $message]);

    expect($this->carla->refresh()->manager_id)->toBeNull()
        ->and($this->carla->name)->toBe('Carla Ruiz')
        ->and(AuditEntry::query()->count())->toBe(0);
})->with([
    'someone who reports to them, indirectly' => [fn ($test): string => $test->ana->id, 'Esa persona le reporta, directa o indirectamente. Elige a alguien más.'],
    'themselves' => [fn ($test): string => $test->carla->id, 'Una persona no puede ser su propio jefe.'],
    'someone of another company' => [fn (): string => Employee::factory()->create()->id, 'Elige a una persona de la lista.'],
    'someone who left' => [fn ($test): string => Employee::factory()->create(['tenant_id' => $test->tenant->id, 'deactivated_at' => now()])->id, 'Esa persona está dada de baja.'],
    'not an id' => [fn (): string => 'carla', 'El campo jefe directo debe ser un UUID válido.'],
]);

it('rejects a manager of another company for a new employee', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), managerForm($this->plant, ['manager_id' => Employee::factory()->create()->id]))
        ->assertSessionHasErrors(['manager_id' => 'Elige a una persona de la lista.']);

    expect(Employee::query()->withoutGlobalScopes()->where('email', 'dora@acme.mx')->exists())->toBeFalse();
});

it('rejects a recognized seniority in the future, as the company counts the days', function (): void {
    // 23:30 of the 9th in Mexico City is already the 10th in UTC.
    $this->travelTo(CarbonImmutable::parse('2026-03-10 05:30:00', 'UTC'));

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), managerForm($this->plant, ['seniority_on' => '2026-03-10']))
        ->assertSessionHasErrors('seniority_on');
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), managerForm($this->plant, ['seniority_on' => '10/03/2020']))
        ->assertSessionHasErrors('seniority_on');
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), managerForm($this->plant, ['seniority_on' => '2026-03-09']))
        ->assertSessionHasNoErrors();
});

it('ignores the manager and the seniority a union account sends', function (): void {
    $union = Tenant::factory()->union()->create();
    $manager = Employee::factory()->create(['tenant_id' => $union->id]);

    $this->actingAs(ownerOf($union))
        ->post(route('tenant.employees.store', $union), ['employee_number' => 'E-001', 'manager_id' => $manager->id, 'seniority_on' => '2019-01-10'])
        ->assertSessionHasNoErrors();

    $person = Employee::query()->withoutGlobalScopes()->where('employee_number', 'E-001')->sole();

    expect($person->manager_id)->toBeNull()
        ->and($person->seniority_on)->toBeNull();
});

it('lists each employee with their manager, seniority and active direct reports', function (): void {
    $this->ana->update(['seniority_on' => '2019-01-10']);
    Employee::factory()->reportingTo($this->carla)->create(['name' => 'Eva Mar']);
    Employee::factory()->reportingTo($this->carla)->create(['name' => 'Se fue', 'deactivated_at' => now()]);

    $this->actingAs($this->owner)
        ->get(route('tenant.employees.index', $this->tenant))
        ->assertInertia(fn (AssertableInertia $page) => $page
            // By name: Ana, Beto, Carla, Eva.
            ->where('employees.data.0.manager', ['value' => $this->beto->id, 'label' => 'Beto Paz · '.$this->beto->job_title])
            ->where('employees.data.0.seniority_on', '2019-01-10')
            ->where('employees.data.0.direct_reports', 0)
            ->where('employees.data.2.manager', null)
            ->where('employees.data.2.seniority_on', null)
            ->where('employees.data.2.direct_reports', 2));
});

it('lists the employees with the same queries whatever their number', function (): void {
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->owner)->get(route('tenant.employees.index', $this->tenant))->assertOk();

        return count(DB::getQueryLog());
    };

    // The first request also loads what is cached afterwards (the permissions).
    $queries();
    $few = $queries();
    Employee::factory()->count(10)->reportingTo($this->carla)->create();

    expect($queries())->toBe($few);
});

it('keeps the recognized seniority of a person an import updates: the file has no column for it', function (): void {
    $this->ana->update(['seniority_on' => '2019-01-10', 'email' => 'ana@acme.mx', 'employee_number' => null]);
    $file = Illuminate\Http\UploadedFile::fake()->createWithContent('empleados.csv', implode("\n", [
        'nombre,correo,celular,genero,centro_de_trabajo,area,puesto,fecha_de_ingreso',
        "Ana Sol,ana@acme.mx,,Mujer,{$this->plant->name},Producción,Operadora,2024-03-15",
    ])."\n");

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => $file])
        ->assertSessionHasNoErrors();

    expect($this->ana->refresh()->seniority_on?->toDateString())->toBe('2019-01-10')
        ->and($this->ana->hired_on?->toDateString())->toBe('2024-03-15');
});
