<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Compensation\Actions\ExportPersonAnnex;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;

mutates(ExportPersonAnnex::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
    workIn($this->tenant);
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);
    $this->scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'group_changes' => ['unionized' => 500, 'trusted' => null]]);
});

function annexPerson(Tenant $tenant, JobGrade $grade, string $number, string $group = 'unionized'): Employee
{
    return Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'hired_on' => '2020-03-15', 'staff_group' => $group, 'employee_number' => $number]);
}

/** @return list<list<string|null>> the rows of the CSV the annex answers, header first */
function annexRows(string $content): array
{
    $rows = [];

    foreach (preg_split('/\r\n|\n/', trim(ltrim($content, "\u{FEFF}"))) ?: [] as $line) {
        $rows[] = str_getcsv($line, ',', '"', '');
    }

    return $rows;
}

function annexUrl(Tenant $tenant, Scenario $scenario): string
{
    return route('tenant.compensation.scenarios.annex', [$tenant, $scenario->id]);
}

it('downloads a CSV with a row per person by company ID, never by the internal id', function (): void {
    $plain = annexPerson($this->tenant, $this->grade, 'E-002');
    annexPerson($this->tenant, $this->grade, 'E-001');
    annexPerson($this->tenant, $this->grade, 'E-003', 'trusted');
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'e-001', 'daily_salary_cents' => 80_000, 'effective_from' => '2026-01-01']);

    $response = $this->get(annexUrl($this->tenant, $this->scenario))->assertOk();
    $rows = annexRows($response->streamedContent());

    $response->assertDownload('anexo-por-persona.csv');
    expect($rows[0])->toBe(['id_de_la_empresa', 'categoria', 'nivel', 'grupo', 'fecha_de_ingreso', 'sueldo_individual', 'salario_diario_actual', 'salario_diario_propuesto', 'cambio_anual_en_salario'])
        ->and($rows[1])->toBe(['E-001', 'Operador', 'A', 'Sindicalizados', '2020-03-15', 'Sí', '800.00', '840.00', '14600.00'])
        ->and($rows[2])->toBe(['E-002', 'Operador', 'A', 'Sindicalizados', '2020-03-15', 'No', '500.00', '525.00', '9125.00'])
        // The trusted take the unionized raise when they have none of their own.
        ->and($rows[3])->toBe(['E-003', 'Operador', 'A', 'Confianza', '2020-03-15', 'No', '500.00', '525.00', '9125.00'])
        ->and($rows)->toHaveCount(4)
        ->and($response->streamedContent())->not->toContain($plain->id)->not->toContain($this->grade->id);
});

it('leaves out whoever has no company ID or no salary and says nothing about them', function (): void {
    annexPerson($this->tenant, $this->grade, 'E-001');
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'employee_number' => null]);
    annexPerson($this->tenant, JobGrade::factory()->create(['tenant_id' => $this->tenant->id]), 'E-002');

    $rows = annexRows($this->get(annexUrl($this->tenant, $this->scenario))->streamedContent());

    expect($rows)->toHaveCount(2)->and($rows[1][0])->toBe('E-001');
});

it('keeps a spreadsheet from running a company ID as a formula', function (): void {
    annexPerson($this->tenant, $this->grade, '=1+1');

    $rows = annexRows($this->get(annexUrl($this->tenant, $this->scenario))->streamedContent());

    expect($rows[1][0])->toBe("'=1+1");
});

it('writes the log and the audit entry of an export with the count only', function (): void {
    Log::spy();
    annexPerson($this->tenant, $this->grade, 'E-001');
    annexPerson($this->tenant, $this->grade, 'E-002');

    $this->get(annexUrl($this->tenant, $this->scenario))->assertOk()->streamedContent();

    $entry = AuditEntry::query()->where('event', 'compensation.annex_exported')->sole();

    expect($entry->metadata)->toEqual(['scenario_id' => $this->scenario->id, 'people' => 2])
        ->and(json_encode($entry->toArray()))->not->toContain('E-001')->not->toContain('500.00');
    Log::shouldHaveReceived('info')->with('compensation.annex_exported', ['scenario_id' => $this->scenario->id, 'people' => 2])->once();
});

it('is for whoever manages compensation and for the scenarios of the account only', function (): void {
    $foreign = Scenario::factory()->create();

    $this->get(annexUrl($this->tenant, $foreign))->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    $this->get(annexUrl($this->tenant, $this->scenario))->assertForbidden();
    expect(AuditEntry::query()->where('event', 'compensation.annex_exported')->count())->toBe(0);
});

it('offers the annex on the report to whoever may export it', function (): void {
    $this->get(route('tenant.compensation.scenarios.report', [$this->tenant, $this->scenario->id]))
        ->assertInertia(fn ($page) => $page->where('can_annex', true));
});
