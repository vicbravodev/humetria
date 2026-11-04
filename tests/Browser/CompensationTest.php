<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

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
