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

/** The scenario of the report tests: two people costed, one left out, a 5 % raise with two months of retroactivity. */
function reportScenario(Tenant $tenant): Scenario
{
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'daily_salary_cents' => 50000, 'effective_from' => '2020-01-01']);
    foreach (['E-001', 'E-002'] as $number) {
        Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'hired_on' => '2006-03-15', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => $number]);
    }
    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => null, 'hired_on' => null, 'employee_number' => 'E-900']);

    return Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revisión 2027', 'effective_on' => '2027-01-01', 'retroactive_months' => 2, 'group_changes' => ['unionized' => 500, 'trusted' => null]]);
}

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
        ->assertSee('Antes de costear, 3 pasos')
        ->assertSee('Cargar el padrón')
        ->assertSee('La empresa no ve tus datos ni tus propuestas.')
        // The first step keeps its text whole: on a phone its button goes under the text, not squeezed beside it.
        ->assertScript('() => { const step = document.querySelector("[aria-labelledby=first-steps] li"); return step.querySelector("span.font-semibold:not([aria-hidden])").getBoundingClientRect().width > step.getBoundingClientRect().width / 2; }', true);
})->with($looks);

it('shows a union its own proposals, with what the company pays today', function (): void {
    $union = Tenant::factory()->union()->create(['slug' => 'sindicato']);
    $this->actingAs(ownerOf($union));
    $operator = JobGrade::factory()->create(['tenant_id' => $union->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $union->id, 'job_grade_id' => $operator->id, 'daily_salary_cents' => 50000, 'effective_from' => '2020-01-01']);
    Employee::factory()->create(['tenant_id' => $union->id, 'work_center_id' => null, 'job_grade_id' => $operator->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'employee_number' => 'E-001']);
    Scenario::factory()->create(['tenant_id' => $union->id, 'name' => 'Propuesta del sindicato · 8 %', 'group_changes' => ['unionized' => 800, 'trusted' => null]]);

    visitPage('compensation/index', '/admin/sindicato/compensation')
        ->assertSee('Tus propuestas')
        ->assertSee('Hoy la empresa paga')
        ->assertSee('1 agremiado')
        ->assertSee('Propuesta del sindicato · 8 %');
});

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
        ->assertSee('Los datos de tu contrato')
        ->assertSee('Tabulador')
        ->assertSee('1 categoría sin salario')
        ->assertSee('Operador')
        ->assertSee('$524.65')
        ->assertSee('nadie todavía')
        ->assertSee('Arrastra aquí tu CSV o elige un archivo')
        ->assertSee('Descargar plantilla');
})->with($looks);

it('marks in its row a salary under the minimum, and counts those on the card of the tabulator', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();

    foreach (['Auxiliar', 'Ayudante'] as $category) {
        $grade = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => $category, 'level' => 'A']);
        GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 25000, 'effective_from' => '2025-01-01']);
    }

    visitPage('compensation/tabulator', "{$url}/compensation/tabulator", $dark, $mobile)
        ->assertSee('2 categorías bajo el salario mínimo')
        ->assertScript('() => document.querySelectorAll("tbody tr").length === 2 && [...document.querySelectorAll("tbody tr")].every((row) => row.textContent.includes("Bajo el salario mínimo."))', true);
})->with($looks);

it('fits the tabulator with a below-minimum note in a 1440 px window, and at 375 px only the table scrolls', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $grade = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Auxiliar', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 25000, 'effective_from' => '2025-01-01']);

    $overflow = '() => { const frame = document.querySelector("[data-slot=table-container]"); return frame.scrollWidth - frame.clientWidth; }';

    visit("{$url}/compensation/tabulator")
        ->resize(1440, 900)
        ->assertSee('Bajo el salario mínimo.')
        ->assertScript($overflow, 0)
        // The warning is a one-line chip in the cell: the row keeps its height, and the whole reason is in the chip for a screen
        // reader and in a tooltip for the mouse and the keyboard.
        ->assertScript('() => { const chip = document.querySelector("tbody [data-slot=below-minimum]"); return chip.getClientRects().length === 1 && chip.closest("tr").getBoundingClientRect().height < 80; }', true)
        ->assertScript('() => document.querySelector("tbody [data-slot=below-minimum]").textContent.includes("Está por debajo del salario mínimo")', true)
        ->assertScript('() => new Promise((resolve) => { document.querySelector("tbody [data-slot=below-minimum]").focus(); setTimeout(resolve, 300); }).then(() => true)')
        ->assertScript('() => document.querySelector("[data-slot=tooltip-content]")?.textContent.includes("Está por debajo del salario mínimo")', true);

    visit("{$url}/compensation/tabulator")
        ->resize(375, 800)
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('captures the salary of a category that has none in its own row (board V4-Comp-Datos)', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Coordinador', 'level' => 'A']);

    visit("{$url}/compensation/tabulator")
        ->type('[aria-label^="Salario diario de Coordinador"]', '615')
        ->keys('[aria-label^="Salario diario de Coordinador"]', 'Enter')
        ->assertSee('Salario guardado.')
        ->assertSee('$615.00')
        ->assertNoJavaScriptErrors();

    expect(GradeSalary::query()->withoutGlobalScopes()->sole()->daily_salary_cents)->toBe(61500);
});

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
        ->click('Historial')
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
        ->assertSee('$9,125')
        ->assertSee('$10,950')
        ->assertSee('IMSS: invalidez y vida')
        ->assertNoJavaScriptErrors();

    $ids = Scenario::query()->withoutGlobalScopes()->pluck('id')->all();

    visitPage('compensation/scenario-compare', "{$url}/compensation/scenarios/compare?ids[]={$ids[0]}&ids[]={$ids[1]}", $dark, $mobile)
        ->assertSee('La distancia a negociar')
        ->assertSee('Entre «Cinco» y «Seis» hay')
        ->assertSee('1 mes de retroactivo')
        ->assertSee('Costo del primer año')
        ->assertSee('Salario');
})->with($looks);

it('tries the point in between of two scenarios in a new one', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $five = Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Cinco', 'group_changes' => ['unionized' => 500, 'trusted' => null]]);
    $six = Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Seis', 'group_changes' => ['unionized' => 600, 'trusted' => null]]);

    visit("{$url}/compensation/scenarios/compare?ids[]={$five->id}&ids[]={$six->id}")
        ->click('Probar un punto medio')
        ->assertScript(pathBecomes("{$url}/compensation/scenarios/create"), true)
        ->assertPathIs("{$url}/compensation/scenarios/create")
        ->assertValue('name', 'Punto medio')
        ->assertValue('unionized_raise', '5.5')
        ->assertNoJavaScriptErrors();
});

it('shows the report of a scenario with its analysis, one notice of accuracy and the detail folded (board V4-Comp-Reporte)', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $scenario = reportScenario(Tenant::query()->where('slug', 'acme')->sole());

    visitPage('compensation/report', "{$url}/compensation/scenarios/{$scenario->id}/report", $dark, $mobile)
        ->assertSee('Revisión 2027')
        ->assertSee('para que el número sea exacto')
        ->assertSee('Costo del primer año')
        ->assertSee('Hallazgos clave')
        ->assertSee('1. De dónde sale el costo')
        ->assertSee('2. Quién recibe el aumento')
        ->assertSee('Operador · A')
        ->assertSee('3. Los próximos años')
        ->assertSee('4. Anexo: detalle por concepto')
        ->assertSee('2 de 3 personas costeadas')
        ->assertDontSee('Frente a las otras propuestas')
        ->click('[data-test="report-detail"]')
        ->assertSee('E-900')
        ->assertSee('Prima de antigüedad')
        ->assertSee('Supuestos y alcance')
        ->assertSee('Meta de inflación del Banco de México')
        ->assertDontSee('Valor para la persona y costo para la empresa');
})->with($looks);

it('has the final figures of the report in print without waiting for the count', function (): void {
    $url = signInToPanel();
    $scenario = reportScenario(Tenant::query()->where('slug', 'acme')->sole());

    $page = visit("{$url}/compensation/scenarios/{$scenario->id}/report")
        ->assertScript('(() => { window.requestAnimationFrame = (callback) => setTimeout(() => callback(performance.now()), 60000); return true; })()', true)
        ->assertSee('Costo del primer año');

    emulatePrint($page)->assertScript("(() => { const figures = [...document.querySelectorAll('dd.tabular-nums')].map((figure) => figure.innerText.trim()); return figures.length >= 3 && figures.every((text) => text !== '' && !/^[$]?0(\\.0)?( %)?$/.test(text)); })()", true);
});

/** A script that waits a moment for the tooltip and says whether it opened telling `$start`. */
function tooltipTells(string $start): string
{
    return '() => new Promise((resolve) => setTimeout(() => resolve(document.querySelector(\'[data-slot="tooltip-content"]\')?.textContent.startsWith('.json_encode($start).') ?? false), 300))';
}

it('weighs the report against the other proposals of the account and tells each bar on focus and on hover', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $scenario = reportScenario($tenant);
    Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revisión 2027 · 4 %', 'effective_on' => '2027-01-01', 'group_changes' => ['unionized' => 400, 'trusted' => null]]);

    visit("{$url}/compensation/scenarios/{$scenario->id}/report")
        ->assertSee('4. Frente a las otras propuestas')
        ->assertSee('Revisión 2027 (esta)')
        ->assertSee('Ver la comparación completa')
        // The keyboard reaches each bar and its tooltip opens; so does the cursor. The chart is on screen first: a tooltip
        // closes when the page scrolls under it.
        ->assertScript('() => new Promise((resolve) => { document.getElementById("costo").scrollIntoView({ block: "start" }); requestAnimationFrame(() => requestAnimationFrame(() => resolve(true))); })', true)
        ->keys('[role="img"][aria-label^="Salario:"]', 'Shift')
        ->assertScript(tooltipTells('Salario:'), true)
        ->hover('[role="img"][aria-label^="Infonavit:"]')
        ->assertScript(tooltipTells('Infonavit:'), true)
        ->assertNoJavaScriptErrors()
        ->click('Ver la comparación completa')
        ->assertSee('Comparar escenarios')
        ->assertNoJavaScriptErrors();
});

it('draws the report still with reduced motion, and prints it in the light theme', function (): void {
    $url = signInToPanel();
    $scenario = reportScenario(Tenant::query()->where('slug', 'acme')->sole());

    visit("{$url}/compensation/scenarios/{$scenario->id}/report", ['reducedMotion' => 'reduce'])->inDarkMode()
        ->assertSee('Costo del primer año')
        // Nothing grows or counts up: no animation runs, and the first figure is already whole.
        ->assertScript('() => document.getAnimations().filter((animation) => animation.playState === "running").length', 0)
        ->assertScript('() => /^\$[1-9]/.test(document.querySelector("#resumen dd").textContent)', true)
        ->assertScript('() => { window.dispatchEvent(new Event("beforeprint")); return document.documentElement.classList.contains("dark"); }', false)
        ->assertNoJavaScriptErrors();
});

// With reduced motion a dialog only fades in: no scale and no slide (design-system.md, «Movimiento»).
it('opens a dialog with a fade only when the person asks for reduced motion', function (): void {
    $url = signInToPanel();
    $scenario = reportScenario(Tenant::query()->where('slug', 'acme')->sole());

    visit("{$url}/compensation/scenarios/{$scenario->id}/report", ['reducedMotion' => 'reduce'])
        ->click('Compartir')
        ->assertScript('() => { const style = getComputedStyle(document.querySelector("[data-slot=dialog-content]")); return [style.getPropertyValue("--tw-enter-scale").trim() || "1", style.getPropertyValue("--tw-enter-translate-y").trim() || "0"].join(); }', '1,0')
        ->assertNoJavaScriptErrors();
});

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
        ->assertSee('Reporte de impacto · Propuesta del sindicato')
        ->click('[data-test="report-detail"]')
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
        ->assertSee('1. De dónde sale el costo')
        ->assertSee('Operador · A')
        ->assertDontSee('Frente a las otras propuestas')
        ->assertDontSee('Completar datos')
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
        ->assertSee('sobre lo que hoy paga la empresa')
        ->press('Guardar y ver reporte')
        ->assertSee('Escenario guardado.')
        ->assertSee('Propuesta del sindicato')
        ->assertSee('8 de 8 personas costeadas')
        ->click('[data-test="report-detail"]')
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
        ->assertSee('1. De dónde sale el costo')
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
        ->assertSee('Nada se guarda hasta que tú lo decidas.')
        ->assertSee('El aumento al salario')
        ->assertSee('Costo del primer año')
        ->assertSee('Supuestos de los años siguientes')
        ->click('Revisar')
        ->assertSee('Meta de inflación del Banco de México');
})->with($looks);

it('reads the start date of a scenario in one line beside the retroactive and the years, at 1440 px', function (): void {
    $url = signInToPanel();

    $page = visit("{$url}/compensation/scenarios/create")->assertSee('Vigente desde');

    resizeTo($page, 1440)
        ->assertScript('() => { const field = document.getElementById("scenario-effective_on"); return field.querySelector("span").getBoundingClientRect().height < field.getBoundingClientRect().height - 8; }', true)
        ->assertNoJavaScriptErrors();
});

it('costs the scenario as it is moved, against the others of the account, before saving anything (board V4-Comp-Escenario)', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'daily_salary_cents' => 50000, 'effective_from' => '2020-01-01']);
    Employee::factory()->count(3)->sequence(fn ($sequence) => ['employee_number' => 'E-00'.$sequence->index])->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'state' => 'JAL']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => null, 'employee_number' => 'E-900']);
    Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revisión 2027 · 4 %', 'group_changes' => ['unionized' => 400, 'trusted' => null]]);

    visitPage('compensation/scenario', "{$url}/compensation/scenarios/create", $dark, $mobile, fn ($page) => $page
        ->assertSee('3 personas')
        ->click('[aria-label="Subir sindicalizados"]')
        ->click('[aria-label="Subir retroactivo"]')
        ->assertSee('Incluye')
        ->assertSee('1 persona queda fuera porque no tiene categoría o salario.'))
        ->assertSee('Frente a tus otros escenarios')
        ->assertSee('Revisión 2027 · 4 %')
        ->assertSee('Este escenario');

    expect(Scenario::query()->withoutGlobalScopes()->count())->toBe(1);
})->with($looks);

it('makes a scenario from its editor and opens its report', function (): void {
    $url = signInToPanel();

    visit("{$url}/compensation/scenarios/create")
        ->fill('name', 'Revisión 2027')
        ->fill('unionized_raise', '5.75')
        ->press('Guardar y ver reporte')
        ->assertPathBeginsWith("{$url}/compensation/scenarios/")
        ->assertSee('Escenario guardado.')
        ->assertSee('Reporte de impacto')
        ->assertSee('Revisión 2027')
        ->assertNoJavaScriptErrors();

    expect(Scenario::query()->withoutGlobalScopes()->sole()->group_changes)->toEqual(['unionized' => 575, 'trusted' => null]);
});

it('saves a raise of a fixed amount a month from the editor', function (): void {
    $url = signInToPanel();

    visit("{$url}/compensation/scenarios/create")
        ->fill('name', 'Monto fijo')
        ->click('Monto fijo al mes')
        ->fill('unionized_raise', '608')
        ->press('Guardar y ver reporte')
        ->assertSee('Escenario guardado.')
        ->assertNoJavaScriptErrors();

    $scenario = Scenario::query()->withoutGlobalScopes()->sole();

    expect($scenario->raise_unit->value)->toBe('amount')->and($scenario->group_changes)->toEqual(['unionized' => 60_800, 'trusted' => null]);
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
        ->assertSee('Nueva en el contrato')
        ->assertSee('Valor nuevo (pesos)')
        ->fill('[name="term_changes[0][value]"]', '1500')
        ->fill('unionized_raise', '0')
        ->press('Guardar y ver reporte')
        ->assertSee('Escenario guardado.')
        ->assertNoJavaScriptErrors();

    expect(Scenario::query()->withoutGlobalScopes()->sole()->term_changes)->toEqual([['benefit_id' => $benefit->id, 'value' => 150000, 'tenure_table' => null]]);
});

it('shows what is wrong in the editor without saving', function (): void {
    $url = signInToPanel();

    visit("{$url}/compensation/scenarios/create")
        ->fill('name', 'Revisión 2027')
        ->fill('unionized_raise', 'cinco')
        ->assertSee('Escribe un porcentaje como 5 o 5.75.')
        ->press('Guardar y ver reporte')
        ->assertSee('Escribe un porcentaje como 5 o 5.75.')
        ->assertNoJavaScriptErrors();

    expect(Scenario::query()->withoutGlobalScopes()->count())->toBe(0);
});

/** A company with one person costed at $500 a day and two scenarios, so the start shows its data and its scenarios. */
function startWithData(): string
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $operator = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'daily_salary_cents' => 50000, 'effective_from' => '2020-01-01']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $operator->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => 'E-001']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => null, 'employee_number' => 'E-900']);
    Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revisión 2027 · 4 %', 'group_changes' => ['unionized' => 400, 'trusted' => null]]);
    Scenario::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Revisión 2027 · 5 %', 'retroactive_months' => 2, 'group_changes' => ['unionized' => 500, 'trusted' => 300]]);

    return $url;
}

it('shows the data of the contract and the scenarios with their cost on the start (board V4-Comp-Inicio)', function (bool $dark, bool $mobile): void {
    $url = startWithData();

    visitPage('compensation/index', "{$url}/compensation", $dark, $mobile)
        ->assertSee('Los datos de tu contrato')
        ->assertSee('El cálculo cubre 1 de 2 personas. Con 2 datos más, el número es exacto.')
        ->assertSee('1 sin categoría y nivel')
        ->assertSee('Tus escenarios')
        ->assertSee('5 % sindicalizados · 3 % confianza · 2 meses de retroactivo')
        ->assertSee('Comparar los 2')
        ->assertSee('Para la mesa de negociación')
        ->assertDontSee('a confianza, cerca de');
})->with($looks);

it('leads from the data of the contract on the start to each of them', function (string $link, string $path): void {
    $url = startWithData();

    visit("{$url}/compensation")
        ->click($link)
        ->assertScript(pathBecomes("{$url}/{$path}"), true)
        ->assertPathIs("{$url}/{$path}")
        ->assertNoJavaScriptErrors();
})->with([
    'tabulator' => ['Tabulador', 'compensation/tabulator'],
    'benefits' => ['Prestaciones', 'compensation/benefits'],
    'missing data' => ['Completar 2 datos', 'compensation/tabulator'],
    'scenarios to compare' => ['Comparar los 2', 'compensation/scenarios/compare'],
]);

it('shows a new benefit with what it costs today as it is answered (board V4-Comp-Prestacion)', function (bool $dark, bool $mobile): void {
    $url = startWithData();

    visitPage('compensation/benefit-create', "{$url}/compensation/benefits/create", $dark, $mobile, fn ($page) => $page
        ->assertSee('Nueva prestación')
        ->click('Monto fijo')
        ->type('[name="value"]', '100')
        ->assertSee('al año, a 1 persona, con sus cuotas'))
        ->assertSee('¿Cuenta para las cuotas del IMSS?')
        ->assertSee('Al guardar, tus 2 escenarios se recalculan solos');
})->with($looks);

it('saves a new benefit that grows with seniority from its page', function (): void {
    $url = signInToPanel();

    visit("{$url}/compensation/benefits")
        ->click('Nueva prestación')
        ->assertScript(pathBecomes("{$url}/compensation/benefits/create"), true)
        ->assertPathIs("{$url}/compensation/benefits/create")
        ->type('[name="name"]', 'Bono de puntualidad')
        ->type('[name="value"]', '10')
        ->click('label:has([name="has_seniority"][value="yes"])')
        ->type('[name="tenure_table[0][value]"]', '10')
        ->click('Agregar renglón')
        ->type('[name="tenure_table[1][from_years]"]', '5')
        ->type('[name="tenure_table[1][value]"]', '12')
        ->click('Nunca')
        ->press('Guardar prestación')
        ->assertScript(pathBecomes("{$url}/compensation/benefits"), true)
        ->assertPathIs("{$url}/compensation/benefits")
        ->assertSee('Prestación creada.')
        ->assertSee('Bono de puntualidad')
        ->assertNoJavaScriptErrors();

    $saved = Benefit::query()->withoutGlobalScopes()->where('name', 'Bono de puntualidad')->sole();

    expect($saved->kind->value)->toBe('salary_percent')->and($saved->sbc_integration->value)->toBe('never')
        ->and($saved->terms()->withoutGlobalScopes()->sole()->tenure_table)->toEqual([['from_years' => 0, 'value' => 1000], ['from_years' => 5, 'value' => 1200]]);
});
