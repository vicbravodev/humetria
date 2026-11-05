<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Compensation\Actions\ImportTabulator;
use App\Modules\Compensation\Http\Controllers\TabulatorImportController;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Support\CsvFile;
use App\Modules\Compensation\Support\TabulatorCsv;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(TabulatorImportController::class, ImportTabulator::class, TabulatorCsv::class, CsvFile::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
});

/** @param  list<string>  $rows */
function tabulatorFile(array $rows, string $header = 'categoria,nivel,salario_diario,vigencia'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('tabulador.csv', implode("\n", [$header, ...$rows])."\n");
}

/** What the last import left in the session for the page: the rows it read and the problems of each. */
function salaryImportFlash(): array
{
    $flash = session(SessionKey::FLASH_DATA);

    return is_array($flash) && is_array($flash['salary_import'] ?? null) ? $flash['salary_import'] : [];
}

function importTabulator(UploadedFile $file): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.compensation.tabulator.import', test()->tenant), ['file' => $file]);
}

it('lives at /admin/{slug}/compensation/tabulator/import', function (): void {
    expect(route('tenant.compensation.tabulator.import', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/compensation/tabulator/import')
        ->and(route('tenant.compensation.tabulator.template', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/compensation/tabulator/import/template');
});

it('imports the file whole, creating the categories and levels that are missing', function (): void {
    $operator = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);

    importTabulator(tabulatorFile(['Operador,A,524.65,2026-01-01', 'OPERADOR,a,560,2027-01-01', 'Técnico,B,"1,5",2026-01-01']))->assertSessionHasNoErrors()->assertRedirect();

    expect(salaryImportFlash())->toBe([])
        ->and(JobGrade::query()->count())->toBe(2)
        ->and(GradeSalary::query()->where('job_grade_id', $operator->id)->orderBy('effective_from')->pluck('daily_salary_cents')->all())->toBe([52465, 56000])
        ->and(GradeSalary::query()->count())->toBe(3)
        ->and(JobGrade::query()->where('category', 'Técnico')->sole()->level)->toBe('B');
});

it('reads a file as a spreadsheet saves it: semicolons, Windows encoding, dates DD/MM/AAAA and a dollar sign', function (): void {
    $content = mb_convert_encoding("Categoría;Nivel;Salario diario;Vigencia\r\nTécnico;B;\$ 524,65;15/03/2026\r\n\r\n", 'Windows-1252', 'UTF-8');

    importTabulator(UploadedFile::fake()->createWithContent('tabulador.csv', $content))->assertSessionHasNoErrors();

    $salary = GradeSalary::query()->sole();

    expect(JobGrade::query()->sole()->only(['category', 'level']))->toBe(['category' => 'Técnico', 'level' => 'B'])
        ->and($salary->daily_salary_cents)->toBe(52465)
        ->and($salary->effective_from->toDateString())->toBe('2026-03-15');
});

it('reads a file with a byte order mark', function (): void {
    importTabulator(UploadedFile::fake()->createWithContent('tabulador.csv', "\u{FEFF}categoria,nivel,salario_diario,vigencia\nOperador,A,500,2026-01-01\n"))->assertSessionHasNoErrors();

    expect(GradeSalary::query()->count())->toBe(1);
});

it('replaces the salary a grade already has on that date and keeps the others', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 40000, 'effective_from' => '2025-01-01']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 50000, 'effective_from' => '2026-01-01']);

    importTabulator(tabulatorFile(['Operador,A,555.55,2026-01-01']))->assertSessionHasNoErrors();

    expect(GradeSalary::query()->orderBy('effective_from')->pluck('daily_salary_cents')->all())->toBe([40000, 55555]);
});

it('writes nothing, not even a new category, when any row has a problem, and says which row and what', function (): void {
    importTabulator(tabulatorFile([
        'Operador,A,524.65,2026-01-01',
        'Nueva,Z,500,2026-01-01',
        'Soldador,B,0,2026-01-01',
        'Soldador,C,-5,2026-01-01',
        ',B,500,2026-01-01',
        'Técnico,,500,2026-01-01',
        'Técnico,A,quinientos,2026-01-01',
        'Técnico,B,500,31/02/2026',
        'Técnico,C,500,',
    ]))->assertRedirect();

    $flash = salaryImportFlash();

    expect(GradeSalary::query()->count())->toBe(0)
        ->and(JobGrade::query()->count())->toBe(0)
        ->and($flash['status'])->toBe('invalid')
        ->and($flash['rows'])->toBe(9)
        ->and(array_column($flash['errors'], 'row'))->toBe([4, 5, 6, 7, 8, 9, 10])
        ->and($flash['errors'][0]['messages'])->toBe(['El salario diario debe ser mayor que cero.'])
        ->and($flash['errors'][2]['messages'])->toBe(['La categoría no puede quedar vacía.'])
        ->and($flash['errors'][4]['messages'])->toBe(['El salario diario debe ser un monto en pesos como 524.65, sin signos ni comas de miles.'])
        ->and($flash['errors'][5]['messages'])->toBe(['La vigencia debe ser una fecha AAAA-MM-DD o DD/MM/AAAA que exista.']);
});

it('rejects a category, a level and a date that repeat in the file, naming the first row', function (): void {
    importTabulator(tabulatorFile([
        'Operador,A,500,2026-01-01',
        'OPERADOR,a,600,2026-01-01',
        'Operador,A,600,2027-01-01',
        'Operador,B,600,2026-01-01',
    ]));

    expect(GradeSalary::query()->count())->toBe(0)
        ->and(salaryImportFlash()['errors'])->toBe([['row' => 3, 'messages' => ['Esta categoría, nivel y vigencia ya están en la fila 2.']]]);
});

it('refuses a category and a level longer than their columns', function (): void {
    importTabulator(tabulatorFile([str_repeat('a', 121).',A,500,2026-01-01', 'Operador,'.str_repeat('b', 61).',500,2026-01-01']));

    expect(salaryImportFlash()['errors'])->toBe([
        ['row' => 2, 'messages' => ['La categoría puede tener 120 caracteres a lo más.']],
        ['row' => 3, 'messages' => ['El nivel puede tener 60 caracteres a lo más.']],
    ]);
});

it('accepts a file of exactly 2000 rows and refuses 2001, writing nothing', function (): void {
    $rows = fn (int $count): array => array_map(fn (int $number): string => "Categoría {$number},A,500,2026-01-01", range(1, $count));

    importTabulator(tabulatorFile($rows(2001)))->assertSessionHasErrors('file');
    expect(GradeSalary::query()->count())->toBe(0);

    importTabulator(tabulatorFile($rows(2000)))->assertSessionHasNoErrors();
    expect(GradeSalary::query()->count())->toBe(2000);
});

it('refuses a file without a column, without rows or that is not a CSV, saying why', function (UploadedFile $file, string $message): void {
    importTabulator($file)->assertSessionHasErrors(['file' => $message]);

    expect(GradeSalary::query()->count())->toBe(0);
})->with([
    'without the salary column' => [fn () => tabulatorFile(['Operador,A,2026-01-01'], 'categoria,nivel,vigencia'), 'Falta la columna «salario_diario». Usa la plantilla.'],
    'without rows' => [fn () => tabulatorFile([]), 'El archivo no tiene filas.'],
    'only empty rows' => [fn () => tabulatorFile([',,,', '']), 'El archivo no tiene filas.'],
]);

it('refuses a file that is not a CSV', function (): void {
    importTabulator(UploadedFile::fake()->create('tabulador.pdf', 10, 'application/pdf'))->assertSessionHasErrors('file');
});

it('writes the log and the audit entry with counts only, never a salary', function (): void {
    Log::spy();

    importTabulator(tabulatorFile(['Operador,A,524.65,2026-01-01', 'Operador,A,560.10,2027-01-01', 'Técnico,B,777.77,2026-01-01']))->assertSessionHasNoErrors();

    Log::shouldHaveReceived('info')->with('compensation.tabulator_imported', ['rows' => 3, 'grades' => 2])->once();

    $entry = AuditEntry::query()->where('event', 'compensation.tabulator_imported')->sole();

    expect($entry->metadata)->toBe(['rows' => 3, 'grades' => 2])
        ->and(json_encode($entry->toArray()))->not->toContain('524')->not->toContain('777')->not->toContain('52465')->not->toContain('Operador');
});

it('writes neither log nor audit when the file is refused', function (): void {
    Log::spy();

    importTabulator(tabulatorFile(['Operador,A,0,2026-01-01']));

    Log::shouldNotHaveReceived('info', ['compensation.tabulator_imported', Mockery::any()]);
    expect(AuditEntry::query()->where('event', 'compensation.tabulator_imported')->count())->toBe(0);
});

it('serves the template with the four columns and an example row', function (): void {
    $response = $this->get(route('tenant.compensation.tabulator.template', $this->tenant))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('plantilla-tabulador.csv')
        ->and($response->getContent())->toBe("\u{FEFF}categoria,nivel,salario_diario,vigencia\nOperador,A,524.65,2026-01-01\n");
});

it('lets owner and hr import and closes it to the other roles', function (TenantRole $role, int $status): void {
    $company = Tenant::factory()->create(['slug' => 'roles']);
    $this->actingAs(memberOf($company, $role));

    $this->post(route('tenant.compensation.tabulator.import', $company), ['file' => tabulatorFile(['Operador,A,500,2026-01-01'])])->assertStatus($status);
    $this->get(route('tenant.compensation.tabulator.template', $company))->assertStatus($status === 302 ? 200 : $status);

    expect(GradeSalary::query()->withoutGlobalScopes()->where('tenant_id', $company->id)->count())->toBe($status === 302 ? 1 : 0);
})->with([
    'owner' => [TenantRole::Owner, 302],
    'hr' => [TenantRole::Hr, 302],
    'viewer' => [TenantRole::Viewer, 403],
    'committee' => [TenantRole::Committee, 403],
]);

it('imports into the account of the URL only, and answers 404 to another account', function (): void {
    $other = Tenant::factory()->create(['slug' => 'beta']);
    GradeSalary::factory()->create(['tenant_id' => $other->id, 'daily_salary_cents' => 12345]);

    importTabulator(tabulatorFile(['Operador,A,500,2026-01-01']));

    expect(GradeSalary::query()->count())->toBe(1)
        ->and(GradeSalary::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->sole()->daily_salary_cents)->toBe(12345);

    $this->post(route('tenant.compensation.tabulator.import', $other), ['file' => tabulatorFile(['Operador,A,500,2026-01-01'])])->assertNotFound();
    $this->get(route('tenant.compensation.tabulator.template', $other))->assertNotFound();
});

it('writes the whole file or none: a failure in the middle leaves nothing behind', function (): void {
    $this->withoutExceptionHandling();
    // The second salary row cannot be stored: the first one and the grades it created are rolled back with it.
    DB::statement('ALTER TABLE grade_salaries ADD CONSTRAINT grade_salaries_test_no_seven_hundred CHECK (daily_salary_cents <> 70000)');

    expect(fn () => importTabulator(tabulatorFile(['Operador,A,500,2026-01-01', 'Técnico,B,700,2026-01-01'])))->toThrow(Illuminate\Database\QueryException::class);

    expect(GradeSalary::query()->count())->toBe(0)->and(JobGrade::query()->count())->toBe(0);
});
