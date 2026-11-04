<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Compensation\Actions\ImportSalaryOverrides;
use App\Modules\Compensation\Http\Controllers\SalaryOverrideImportController;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Support\OverridesCsv;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(SalaryOverrideImportController::class, ImportSalaryOverrides::class, OverridesCsv::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    foreach (['E-001', 'E-002', 'E-003'] as $number) {
        importablePerson($this->tenant, $this->grade, $number);
    }
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
});

/**
 * A person of the roster the calculator can cost, in the account `$tenant`; `$attributes` overrides any column.
 *
 * @param  array<string, mixed>  $attributes
 */
function importablePerson(Tenant $tenant, JobGrade $grade, string $number, array $attributes = []): Employee
{
    return Employee::factory()->create([
        'tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized',
        'employee_number' => $number, ...$attributes,
    ]);
}

/** @param  list<string>  $rows */
function overridesFile(array $rows, string $header = 'id_de_la_empresa,salario_diario,vigencia'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('sueldos.csv', implode("\n", [$header, ...$rows])."\n");
}

/** What the last import left in the session for the page: the rows it read and the problems of each. */
function overridesImportFlash(): array
{
    $flash = session(SessionKey::FLASH_DATA);

    return is_array($flash) && is_array($flash['salary_import'] ?? null) ? $flash['salary_import'] : [];
}

function importOverrides(UploadedFile $file): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.compensation.overrides.import', test()->tenant), ['file' => $file]);
}

it('lives at /admin/{slug}/compensation/overrides/import', function (): void {
    expect(route('tenant.compensation.overrides.import', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/compensation/overrides/import')
        ->and(route('tenant.compensation.overrides.template', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/compensation/overrides/import/template');
});

it('imports the file whole, keeping the IDs as the roster has them and the history of each person', function (): void {
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'daily_salary_cents' => 55000, 'effective_from' => '2025-01-01']);

    importOverrides(overridesFile(['e-001,650.25,2026-01-01', 'E-002,"700,5",15/03/2026']))->assertSessionHasNoErrors()->assertRedirect();

    expect(overridesImportFlash())->toBe([])
        ->and(SalaryOverride::query()->orderBy('employee_number')->orderBy('effective_from')->get()->map(fn (SalaryOverride $override): array => [$override->employee_number, $override->daily_salary_cents, $override->effective_from->toDateString()])->all())
        ->toBe([['E-001', 55000, '2025-01-01'], ['E-001', 65025, '2026-01-01'], ['E-002', 70050, '2026-03-15']]);
});

it('reads the optional note column and leaves the note empty without it', function (): void {
    importOverrides(overridesFile(['E-001,650,2026-01-01,Convenio de planta', 'E-002,700,2026-01-01,'], 'ID de la empresa,Salario diario,Vigencia,Nota'))->assertSessionHasNoErrors();

    expect(SalaryOverride::query()->orderBy('employee_number')->pluck('note')->all())->toBe(['Convenio de planta', null]);

    importOverrides(overridesFile(['E-003,800,2026-01-01']))->assertSessionHasNoErrors();

    expect(SalaryOverride::query()->where('employee_number', 'E-003')->sole()->note)->toBeNull();
});

it('replaces the salary a person already has on that date', function (): void {
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'daily_salary_cents' => 55000, 'effective_from' => '2026-01-01', 'note' => 'Vieja']);

    importOverrides(overridesFile(['E-001,666.66,2026-01-01']))->assertSessionHasNoErrors();

    expect(SalaryOverride::query()->count())->toBe(1)->and(SalaryOverride::query()->sole()->daily_salary_cents)->toBe(66666);
});

it('rejects an ID that does not exist, is deactivated, cannot be costed or repeats in the file, and writes nothing', function (): void {
    importablePerson($this->tenant, $this->grade, 'E-GONE', ['deactivated_at' => now()]);
    importablePerson($this->tenant, $this->grade, 'E-NOGRADE', ['job_grade_id' => null]);

    importOverrides(overridesFile([
        'E-001,650,2026-01-01',
        'E-404,650,2026-01-01',
        'e-gone,650,2026-01-01',
        'E-NOGRADE,650,2026-01-01',
        'E-002,700,2026-01-01',
        'e-001,651,2027-01-01',
        ',650,2026-01-01',
    ]))->assertRedirect();

    $flash = overridesImportFlash();

    expect(SalaryOverride::query()->count())->toBe(0)
        ->and($flash['status'])->toBe('invalid')
        ->and($flash['rows'])->toBe(7)
        ->and($flash['errors'])->toBe([
            ['row' => 3, 'messages' => ['No hay una persona activa con este ID de la empresa.']],
            ['row' => 4, 'messages' => ['No hay una persona activa con este ID de la empresa.']],
            ['row' => 5, 'messages' => ['A esta persona le falta categoría y nivel, fecha de ingreso o grupo en el padrón: complétalos primero.']],
            ['row' => 7, 'messages' => ['Este ID de la empresa ya está en la fila 2.']],
            ['row' => 8, 'messages' => ['Escribe el ID de la empresa de la persona, de 50 caracteres a lo más.']],
        ]);
});

it('rejects a salary of zero or negative, a date that does not exist and a note that is too long, row by row', function (): void {
    importOverrides(overridesFile([
        'E-001,0,2026-01-01',
        'E-002,-5,2026-01-01',
        'E-003,quinientos,31/02/2026',
        'E-001,650,',
    ], 'id_de_la_empresa,salario_diario,vigencia'));

    expect(overridesImportFlash()['errors'])->toBe([
        ['row' => 2, 'messages' => ['El salario diario debe ser mayor que cero.']],
        ['row' => 3, 'messages' => ['El salario diario debe ser un monto en pesos como 524.65, sin signos ni comas de miles.']],
        ['row' => 4, 'messages' => ['El salario diario debe ser un monto en pesos como 524.65, sin signos ni comas de miles.', 'La vigencia debe ser una fecha AAAA-MM-DD o DD/MM/AAAA que exista.']],
        ['row' => 5, 'messages' => ['La vigencia debe ser una fecha AAAA-MM-DD o DD/MM/AAAA que exista.', 'Este ID de la empresa ya está en la fila 2.']],
    ]);

    importOverrides(overridesFile(['E-001,650,2026-01-01,'.str_repeat('a', 501)], 'id_de_la_empresa,salario_diario,vigencia,nota'));

    expect(overridesImportFlash()['errors'])->toBe([['row' => 2, 'messages' => ['La nota puede tener 500 caracteres a lo más.']]])
        ->and(SalaryOverride::query()->count())->toBe(0);
});

it('accepts a file of exactly 2000 rows and refuses 2001, writing nothing', function (): void {
    foreach (range(1, 2001) as $number) {
        importablePerson($this->tenant, $this->grade, "E-{$number}");
    }
    $rows = fn (int $count): array => array_map(fn (int $number): string => "E-{$number},500,2026-01-01", range(1, $count));

    importOverrides(overridesFile($rows(2001)))->assertSessionHasErrors('file');
    expect(SalaryOverride::query()->count())->toBe(0);

    importOverrides(overridesFile($rows(2000)))->assertSessionHasNoErrors();
    expect(SalaryOverride::query()->count())->toBe(2000);
});

it('refuses a file without a column, without rows or that is not a CSV, saying why', function (UploadedFile $file, string $message): void {
    importOverrides($file)->assertSessionHasErrors(['file' => $message]);

    expect(SalaryOverride::query()->count())->toBe(0);
})->with([
    'without the ID column' => [fn () => overridesFile(['650,2026-01-01'], 'salario_diario,vigencia'), 'Falta la columna «id_de_la_empresa». Usa la plantilla.'],
    'without the date column' => [fn () => overridesFile(['E-001,650'], 'id_de_la_empresa,salario_diario'), 'Falta la columna «vigencia». Usa la plantilla.'],
    'without rows' => [fn () => overridesFile([]), 'El archivo no tiene filas.'],
]);

it('refuses a file that is not a CSV', function (): void {
    importOverrides(UploadedFile::fake()->create('sueldos.pdf', 10, 'application/pdf'))->assertSessionHasErrors('file');
});

it('writes the log and the audit entry with counts only, never a person or a salary', function (): void {
    Log::spy();

    importOverrides(overridesFile(['E-001,650.25,2026-01-01', 'E-002,777.77,2026-01-01']))->assertSessionHasNoErrors();

    Log::shouldHaveReceived('info')->with('compensation.overrides_imported', ['rows' => 2])->once();

    $entry = AuditEntry::query()->where('event', 'compensation.overrides_imported')->sole();

    expect($entry->metadata)->toBe(['overrides' => 2])
        ->and(json_encode($entry->toArray()))->not->toContain('E-001')->not->toContain('650.25')->not->toContain('777.77')->not->toContain('65025')->not->toContain('77777');
});

it('writes neither log nor audit when the file is refused', function (): void {
    Log::spy();

    importOverrides(overridesFile(['E-404,650,2026-01-01']));

    Log::shouldNotHaveReceived('info', ['compensation.overrides_imported', Mockery::any()]);
    expect(AuditEntry::query()->where('event', 'compensation.overrides_imported')->count())->toBe(0);
});

it('serves the template with the three columns and an example row', function (): void {
    $response = $this->get(route('tenant.compensation.overrides.template', $this->tenant))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('plantilla-sueldos-individuales.csv')
        ->and($response->getContent())->toBe("\u{FEFF}id_de_la_empresa,salario_diario,vigencia\nA-001,650.25,2026-01-01\n");
});

it('lets owner and hr import and closes it to the other roles', function (TenantRole $role, int $status): void {
    $company = Tenant::factory()->create(['slug' => 'roles']);
    importablePerson($company, JobGrade::factory()->create(['tenant_id' => $company->id]), 'E-001');
    $this->actingAs(memberOf($company, $role));

    $this->post(route('tenant.compensation.overrides.import', $company), ['file' => overridesFile(['E-001,500,2026-01-01'])])->assertStatus($status);
    $this->get(route('tenant.compensation.overrides.template', $company))->assertStatus($status === 302 ? 200 : $status);

    expect(SalaryOverride::query()->withoutGlobalScopes()->where('tenant_id', $company->id)->count())->toBe($status === 302 ? 1 : 0);
})->with([
    'owner' => [TenantRole::Owner, 302],
    'hr' => [TenantRole::Hr, 302],
    'viewer' => [TenantRole::Viewer, 403],
    'committee' => [TenantRole::Committee, 403],
]);

it('imports into the account of the URL only, and answers 404 to another account', function (): void {
    $other = Tenant::factory()->create(['slug' => 'beta']);
    importablePerson($other, JobGrade::factory()->create(['tenant_id' => $other->id]), 'B-001');
    SalaryOverride::factory()->create(['tenant_id' => $other->id, 'employee_number' => 'B-001', 'daily_salary_cents' => 12345]);

    // A person of the other account is not found here.
    importOverrides(overridesFile(['B-001,500,2026-01-01']));
    expect(overridesImportFlash()['errors'][0]['messages'])->toBe(['No hay una persona activa con este ID de la empresa.']);

    importOverrides(overridesFile(['E-001,500,2026-01-01']));

    expect(SalaryOverride::query()->count())->toBe(1)
        ->and(SalaryOverride::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->sole()->daily_salary_cents)->toBe(12345);

    $this->post(route('tenant.compensation.overrides.import', $other), ['file' => overridesFile(['B-001,500,2026-01-01'])])->assertNotFound();
    $this->get(route('tenant.compensation.overrides.template', $other))->assertNotFound();
});

it('writes the whole file or none: a failure in the middle leaves nothing behind', function (): void {
    $this->withoutExceptionHandling();
    // The second salary cannot be stored: the first one is rolled back with it.
    DB::statement('ALTER TABLE salary_overrides ADD CONSTRAINT salary_overrides_test_no_seven_hundred CHECK (daily_salary_cents <> 70000)');

    expect(fn () => importOverrides(overridesFile(['E-001,500,2026-01-01', 'E-002,700,2026-01-01'])))->toThrow(Illuminate\Database\QueryException::class);

    expect(SalaryOverride::query()->count())->toBe(0);
});

it('looks the people up in one go, not one by one', function (): void {
    importOverrides(overridesFile(['E-001,500,2026-01-01']));
    $queries = function (array $rows): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        importOverrides(overridesFile($rows));

        return count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains((string) $query['query'], 'from "employees"')));
    };

    $one = $queries(['E-001,500,2026-02-01']);

    expect($queries(['E-001,500,2026-03-01', 'E-002,500,2026-03-01', 'E-003,500,2026-03-01']))->toBe($one);
});
