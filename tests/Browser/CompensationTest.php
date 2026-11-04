<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\GradeSalary;
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
