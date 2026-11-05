<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\ReportShare;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\File;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** A CSV on disk for the browser to upload, in a folder of this process that is deleted after each test. */
function salaryCsvOnDisk(string $content): string
{
    $path = storage_path('framework/testing/salaries-'.getmypid().'/'.uniqid().'.csv');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $content);

    return $path;
}

afterEach(function (): void {
    File::deleteDirectory(storage_path('framework/testing/salaries-'.getmypid()));
});

it('shows Compensación of a company with its next step', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('compensation/index', "{$url}/compensation", $dark, $mobile)
        ->assertSee('Compensación')
        ->assertSee('Cargar el padrón');
})->with($looks);

it('shows Compensación as the start of a union', function (bool $dark, bool $mobile): void {
    $union = Tenant::factory()->union()->create(['slug' => 'sindicato']);
    $this->actingAs(ownerOf($union));

    visitPage('compensation/index', '/admin/sindicato', $dark, $mobile)
        ->assertSee('Compensación')
        ->assertSee('Cargar el padrón');
})->with($looks);

it('shows the catalog of benefits with the base ones and the risk premium', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('compensation/benefits', "{$url}/compensation/benefits", $dark, $mobile)
        ->assertSee('Prestaciones')
        ->assertSee('Aguinaldo')
        ->assertSee('Prima de riesgo de trabajo');
})->with($looks);

it('shows the tabulator with the salary of each category and level', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'daily_salary_cents' => 52465, 'effective_from' => '2020-01-01']);
    JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Técnico', 'level' => 'B']);

    visitPage('compensation/tabulator', "{$url}/compensation/tabulator", $dark, $mobile)
        ->assertSee('Tabulador')
        ->assertSee('Operador')
        ->assertSee('$524.65')
        ->assertSee('Sin salario');
})->with($looks);

it('imports a tabulator file from its page and lists the new categories', function (): void {
    $url = signInToPanel();
    $path = salaryCsvOnDisk("categoria,nivel,salario_diario,vigencia\nOperador,A,524.65,2020-01-01\nTécnico,B,700,2020-01-01\n");

    visit("{$url}/compensation/tabulator")
        ->attach('file', $path)
        ->press('Importar')
        ->assertSee('Se importó el tabulador: 2 salarios.')
        ->assertSee('Operador')
        ->assertSee('$700.00')
        ->assertNoJavaScriptErrors();

    expect(GradeSalary::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('deletes a salary of the tabulator that was typed by mistake, after confirming', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'daily_salary_cents' => 50000, 'effective_from' => '2020-01-01']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'daily_salary_cents' => 99000, 'effective_from' => '2031-06-01']);

    visit("{$url}/compensation/tabulator")
        ->click('Salarios')
        ->click('[aria-label^="Eliminar el salario desde el 1 de junio de 2031"]')
        ->assertSee('¿Eliminar este salario?')
        ->press('Eliminar salario')
        ->assertSee('Salario eliminado.')
        ->assertNoJavaScriptErrors();

    expect(GradeSalary::query()->withoutGlobalScopes()->sole()->daily_salary_cents)->toBe(50000);
});

it('lists the rows of a tabulator file to fix and imports nothing', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $path = salaryCsvOnDisk("categoria,nivel,salario_diario,vigencia\nOperador,A,0,2020-01-01\n");

    visitPage('compensation/tabulator', "{$url}/compensation/tabulator", $dark, $mobile, fn ($page) => $page
        ->attach('file', $path)
        ->press('Importar')
        ->assertSee('No se importó nada'))
        ->assertSee('Corrige 1 fila')
        ->assertSeeIn('[aria-labelledby="import-problems"] tbody tr', 'Fila 2')
        ->assertSee('El salario diario debe ser mayor que cero.');

    expect(GradeSalary::query()->withoutGlobalScopes()->count())->toBe(0);
})->with($looks);

it('shows the people with an individual salary and what they have earned', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'employee_number' => 'E-001']);
    SalaryOverride::factory()->create(['tenant_id' => $tenant->id, 'employee_number' => 'E-001', 'daily_salary_cents' => 65025, 'effective_from' => '2020-01-01', 'note' => 'Convenio de planta']);
    SalaryOverride::factory()->create(['tenant_id' => $tenant->id, 'employee_number' => 'E-404', 'effective_from' => '2020-01-01']);

    visitPage('compensation/overrides', "{$url}/compensation/overrides", $dark, $mobile)
        ->assertSee('Sueldos individuales')
        ->assertSee('E-001')
        ->assertSee('Operador · A')
        ->assertSee('$650.25')
        ->assertSee('Convenio de planta')
        ->assertSee('Sin persona que costear');
})->with($looks);

it('saves the individual salary of a person from its sheet', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'employee_number' => 'E-001']);

    visit("{$url}/compensation/overrides")
        ->press('Nuevo sueldo')
        ->fill('employee_number', 'E-404')
        ->fill('daily_salary', '650.25')
        ->press('Guardar sueldo')
        ->assertSee('No hay una persona activa con este ID de la empresa.')
        ->fill('employee_number', 'e-001')
        ->press('Guardar sueldo')
        ->assertSee('Sueldo individual guardado.')
        ->assertSee('$650.25')
        ->assertNoJavaScriptErrors();

    expect(SalaryOverride::query()->withoutGlobalScopes()->sole()->employee_number)->toBe('E-001');
});

it('imports a file of individual salaries from its page', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'employee_number' => 'E-001']);
    $path = salaryCsvOnDisk("id_de_la_empresa,salario_diario,vigencia\nE-001,650.25,2020-01-01\n");

    visit("{$url}/compensation/overrides")
        ->attach('file', $path)
        ->press('Importar')
        ->assertSee('Se importó 1 sueldo individual.')
        ->assertSee('$650.25')
        ->assertNoJavaScriptErrors();

    expect(SalaryOverride::query()->withoutGlobalScopes()->sole()->daily_salary_cents)->toBe(65025);
});

it('lists the rows of an individual salaries file to fix and imports nothing', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $path = salaryCsvOnDisk("id_de_la_empresa,salario_diario,vigencia\nE-404,650.25,2020-01-01\n");

    visitPage('compensation/overrides', "{$url}/compensation/overrides", $dark, $mobile, fn ($page) => $page
        ->attach('file', $path)
        ->press('Importar')
        ->assertSee('No se importó nada'))
        ->assertSee('Corrige 1 fila')
        ->assertSeeIn('[aria-labelledby="import-problems"] tbody tr', 'Fila 2')
        ->assertSee('No hay una persona activa con este ID de la empresa.');

    expect(SalaryOverride::query()->withoutGlobalScopes()->count())->toBe(0);
})->with($looks);

it('shows the scenarios with what each proposes and costs', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'daily_salary_cents' => 50000, 'effective_from' => '2020-01-01']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => 'E-001']);
    Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revisión 2027', 'effective_on' => '2027-01-01', 'retroactive_months' => 2, 'group_changes' => ['unionized' => 575, 'trusted' => 300]]);

    visitPage('compensation/scenarios', "{$url}/compensation/scenarios", $dark, $mobile)
        ->assertSee('Escenarios')
        ->assertSee('Revisión 2027')
        ->assertSee('5.75 % sindicalizados · 3 % confianza')
        ->assertSee('con 2 meses de retroactivo')
        ->assertSee('1 persona costeada');
})->with($looks);

it('compares two ticked scenarios by concept', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'daily_salary_cents' => 50000, 'effective_from' => '2020-01-01']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => 'E-001']);
    Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Cinco', 'effective_on' => '2027-01-01', 'group_changes' => ['unionized' => 500, 'trusted' => null]]);
    Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Seis', 'effective_on' => '2027-01-01', 'group_changes' => ['unionized' => 600, 'trusted' => null]]);

    visit("{$url}/compensation/scenarios")
        ->click('[aria-label="Comparar Cinco"]')
        ->click('[aria-label="Comparar Seis"]')
        ->click('Comparar 2')
        ->assertPathBeginsWith("{$url}/compensation/scenarios/compare")
        ->assertSee('Comparar escenarios')
        ->assertSee('$9,125.00')
        ->assertSee('$10,950.00')
        ->assertSee('IMSS: invalidez y vida')
        ->assertNoJavaScriptErrors();

    $ids = Scenario::query()->withoutGlobalScopes()->pluck('id')->all();

    visitPage('compensation/scenario-compare', "{$url}/compensation/scenarios/compare?ids[]={$ids[0]}&ids[]={$ids[1]}", $dark, $mobile)
        ->assertSee('Costo del primer año')
        ->assertSee('Salario');
})->with($looks);

it('shows the report of a scenario with its numbers and assumptions', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'daily_salary_cents' => 50000, 'effective_from' => '2020-01-01']);
    foreach (['E-001', 'E-002'] as $number) {
        Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'hired_on' => '2006-03-15', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => $number]);
    }
    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => null, 'hired_on' => null, 'employee_number' => 'E-900']);
    $scenario = Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revisión 2027', 'effective_on' => '2027-01-01', 'retroactive_months' => 2, 'group_changes' => ['unionized' => 500, 'trusted' => null]]);

    visitPage('compensation/report', "{$url}/compensation/scenarios/{$scenario->id}/report", $dark, $mobile)
        ->assertSee('Revisión 2027')
        ->assertSee('2 de 3 personas costeadas')
        ->assertSee('E-900')
        ->assertSee('Cascada por concepto')
        ->assertSee('Prima de antigüedad')
        ->assertSee('Supuestos y alcance')
        ->assertSee('Meta de inflación del Banco de México')
        ->assertSee('Operador · A')
        ->assertDontSee('Valor para la persona y costo para la empresa');
})->with($looks);

it('opens the report of a scenario from its list', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revisión 2027']);

    visit("{$url}/compensation/scenarios")
        ->click('[data-test="scenario-actions"]')
        ->click('Ver reporte')
        ->assertSee('Reporte de impacto')
        ->assertSee('Revisión 2027')
        ->assertNoJavaScriptErrors();
});

it('shows a union the worker gross against the company cost', function (bool $dark, bool $mobile): void {
    $union = Tenant::factory()->union()->create(['slug' => 'sindicato']);
    $this->actingAs(ownerOf($union));
    $scenario = Scenario::factory()->create(['tenant_id' => $union->id, 'name' => 'Propuesta del sindicato']);

    visitPage('compensation/report', "/admin/sindicato/compensation/scenarios/{$scenario->id}/report", $dark, $mobile)
        ->assertSee('Valor para la persona y costo para la empresa');
})->with($looks);

it('shares the report from its page, shows the link once and revokes it', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $scenario = Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revisión 2027']);

    visit("{$url}/compensation/scenarios/{$scenario->id}/report")
        ->click('Compartir')
        ->press('Crear enlace')
        ->assertSee('Enlace al reporte')
        ->assertSee('Cópialo ahora: no se vuelve a mostrar.')
        ->assertNoJavaScriptErrors();

    expect(ReportShare::query()->withoutGlobalScopes()->sole()->scenario_id)->toBe($scenario->id);

    visit("{$url}/compensation/scenarios/{$scenario->id}/report")
        ->assertSee('Enlaces abiertos')
        ->click('[aria-label^="Revocar el enlace"]')
        ->press('Revocar enlace')
        ->assertSee('Enlace revocado.')
        ->assertNoJavaScriptErrors();

    expect(ReportShare::query()->withoutGlobalScopes()->sole()->revoked_at)->not->toBeNull();
});

it('opens the shared report with no account, the small groups folded into Others', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $grade = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 50000, 'effective_from' => '2020-01-01']);
    foreach (['Producción' => 12, 'Dirección' => 2] as $name => $count) {
        $area = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => $name]);

        foreach (range(1, $count) as $number) {
            Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'area_id' => $area->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => "{$name}-{$number}"]);
        }
    }
    $scenario = Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revisión 2027', 'effective_on' => '2027-01-01']);
    ReportShare::factory()->create(['tenant_id' => $tenant->id, 'scenario_id' => $scenario->id, 'token_hash' => ReportShare::hashToken(str_repeat('a', 64)), 'created_at' => now(), 'expires_at' => now()->addDay()]);

    visitPage('compensation/shared-report', '/compensation-report/'.str_repeat('a', 64), $dark, $mobile)
        ->assertSee('Acme compartió este reporte contigo')
        ->assertSee('Revisión 2027')
        ->assertSee('Cascada por concepto')
        ->assertDontSee('Producción-1')
        ->assertDontSee('Anexo por persona');
})->with($looks);

it('says a link that opens nothing is not available, without saying why', function (bool $dark, bool $mobile): void {
    visitPage('compensation/shared-report', '/compensation-report/'.str_repeat('z', 64), $dark, $mobile)
        ->assertSee('Este enlace ya no está disponible');
})->with($looks);

it('takes a union from its roster to a report shared with someone who has no account', function (): void {
    $union = Tenant::factory()->union()->create(['slug' => 'sindicato', 'trade_name' => 'Sindicato Demo']);
    $this->actingAs(ownerOf($union));
    $roster = "numero_de_empleado,categoria,nivel,fecha_de_ingreso,grupo\n".collect(range(1, 8))->map(fn (int $number): string => "E-00{$number},Operador,A,2018-03-15,Sindicalizado")->implode("\n")."\n";

    // 1. The roster, from the union's own template, checked and then imported.
    visit('/admin/sindicato/employees/import')
        ->attach('file', salaryCsvOnDisk($roster))
        ->press('Revisar archivo')
        ->assertSee('Listo para importar 8 empleados')
        ->press('Importar 8 empleados')
        ->assertSee('Se importaron 8 empleados')
        ->assertNoJavaScriptErrors();

    // 2. The tabulator, from its CSV.
    visit('/admin/sindicato/compensation/tabulator')
        ->attach('file', salaryCsvOnDisk("categoria,nivel,salario_diario,vigencia\nOperador,A,500,2018-01-01\n"))
        ->press('Importar')
        ->assertSee('Se importó el tabulador: 1 salario.')
        ->assertNoJavaScriptErrors();

    // 3. A scenario from its editor, and its report.
    visit('/admin/sindicato/compensation/scenarios/create')
        ->fill('name', 'Propuesta del sindicato')
        ->fill('unionized_raise', '6')
        ->press('Guardar escenario')
        ->assertSee('Escenario guardado.')
        ->assertSee('Propuesta del sindicato')
        ->assertSee('8 personas costeadas')
        ->assertNoJavaScriptErrors();

    $scenario = Scenario::query()->withoutGlobalScopes()->sole();

    visit("/admin/sindicato/compensation/scenarios/{$scenario->id}/report")
        ->assertSee('8 de 8 personas costeadas')
        ->assertSee('Valor para la persona y costo para la empresa')
        ->click('Compartir')
        ->press('Crear enlace')
        ->assertSee('Cópialo ahora: no se vuelve a mostrar.')
        ->assertNoJavaScriptErrors();

    // 4. The link, with the same token hash the page kept: it opens the report with nobody signed in, and without the worker's gross.
    $token = str_repeat('f', 64);
    $share = ReportShare::query()->withoutGlobalScopes()->sole();
    $share->update(['token_hash' => ReportShare::hashToken($token)]);

    auth()->logout();
    visit("/compensation-report/{$token}")
        ->assertSee('Sindicato Demo compartió este reporte contigo')
        ->assertSee('Propuesta del sindicato')
        ->assertSee('Cascada por concepto')
        ->assertDontSee('Valor para la persona y costo para la empresa')
        ->assertDontSee('E-001')
        ->assertNoJavaScriptErrors();
});

it('shows the first scenario as the next step', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('compensation/scenarios', "{$url}/compensation/scenarios", $dark, $mobile)
        ->assertSee('Todavía no hay escenarios')
        ->assertSee('Nuevo escenario');
})->with($looks);

it('shows the editor of a new scenario with its assumptions', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('compensation/scenario', "{$url}/compensation/scenarios/create", $dark, $mobile)
        ->assertSee('Nuevo escenario')
        ->assertSee('Aumento al salario')
        ->assertSee('Supuestos de largo plazo')
        ->assertSee('Meta de inflación del Banco de México');
})->with($looks);

it('makes a scenario from its editor and finds it in the list', function (): void {
    $url = signInToPanel();

    visit("{$url}/compensation/scenarios/create")
        ->fill('name', 'Revisión 2027')
        ->fill('unionized_raise', '5.75')
        ->press('Guardar escenario')
        ->assertPathIs("{$url}/compensation/scenarios")
        ->assertSee('Escenario guardado.')
        ->assertSee('Revisión 2027')
        ->assertSee('5.75 % sindicalizados y confianza')
        ->assertNoJavaScriptErrors();

    expect(Scenario::query()->withoutGlobalScopes()->sole()->group_changes)->toEqual(['unionized' => 575, 'trusted' => null]);
});

it('adds a benefit to change in the editor, in its own unit, and saves its new value', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $benefit = Benefit::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bono de transporte', 'kind' => 'fixed_amount', 'frequency' => 'monthly']);

    visit("{$url}/compensation/scenarios/create")
        ->fill('name', 'Con bono')
        ->click('#scenario-benefit-search')
        ->type('[role="combobox"][aria-controls="scenario-benefit-search-options"]', 'transp')
        ->click('[role="option"]:has-text("Bono de transporte")')
        ->assertSee('No está en el contrato de hoy')
        ->assertSee('Valor nuevo (pesos)')
        ->fill('[name="term_changes[0][value]"]', '1500')
        ->fill('unionized_raise', '0')
        ->press('Guardar escenario')
        ->assertSee('Escenario guardado.')
        ->assertNoJavaScriptErrors();

    expect(Scenario::query()->withoutGlobalScopes()->sole()->term_changes)->toEqual([['benefit_id' => $benefit->id, 'value' => 150000, 'tenure_table' => null]]);
});

it('shows what is wrong in the editor without saving', function (): void {
    $url = signInToPanel();

    visit("{$url}/compensation/scenarios/create")
        ->fill('name', 'Revisión 2027')
        ->fill('unionized_raise', 'cinco')
        ->press('Guardar escenario')
        ->assertSee('Escribe un porcentaje como 5 o 5.75.')
        ->assertNoJavaScriptErrors();

    expect(Scenario::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('leads from Compensación to the benefits, the tabulator and the individual salaries', function (string $link, string $path): void {
    $url = signInToPanel();

    visit("{$url}/compensation")
        ->click($link)
        ->assertPathIs("{$url}/compensation/{$path}")
        ->assertNoJavaScriptErrors();
})->with([
    'benefits' => ['Prestaciones', 'benefits'],
    'tabulator' => ['Tabulador', 'tabulator'],
    'individual salaries' => ['Sueldos individuales', 'overrides'],
    'scenarios' => ['Escenarios', 'scenarios'],
]);
