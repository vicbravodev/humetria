<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\ReportBuilder;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ReportBuilder::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
    workIn($this->tenant);
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);
    foreach (range(1, 9) as $number) {
        reportStaff($this->tenant, $this->grade, $this->area, "E-00{$number}");
    }
    $this->scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Revisión 2027', 'retroactive_months' => 3, 'horizon_years' => 4, 'default_state' => 'JAL', 'group_changes' => ['unionized' => 500, 'trusted' => null]]);
});

/** A person the calculator can cost, hired on 2006-01-01 (20 years of service in 2026) unless said otherwise. @param  array<string, mixed>  $attributes */
function reportStaff(Tenant $tenant, JobGrade $grade, Area $area, string $number, array $attributes = []): Employee
{
    return Employee::factory()->create([
        'tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'area_id' => $area->id, 'hired_on' => '2006-01-01', 'staff_group' => 'unionized',
        'state' => 'JAL', 'employee_number' => $number, ...$attributes,
    ]);
}

function reportUrl(Tenant $tenant, Scenario $scenario): string
{
    return route('tenant.compensation.scenarios.report', [$tenant, $scenario->id]);
}

it('opens with the people costed of the total and lists the missing by company ID', function (): void {
    reportStaff($this->tenant, $this->grade, $this->area, 'E-900', ['hired_on' => null]);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => null, 'employee_number' => null]);

    $this->get(reportUrl($this->tenant, $this->scenario))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/report')
            ->where('scenario.name', 'Revisión 2027')->where('scenario.horizon_years', 4)
            ->where('coverage.total', 11)->where('coverage.costed', 9)
            ->where('coverage.missing', ['E-900'])->where('coverage.missing_without_number', 1));
});

it('shows the cost of the first year with its retroactivity and the recurring cost', function (): void {
    $this->get(reportUrl($this->tenant, $this->scenario))
        ->assertInertia(fn (Assert $page) => $page
            ->where('headline.people', 9)
            ->where('headline.retroactive_months', 3)
            // 5 % of $500 for 365 days, nine people: the salary alone is $82,125.00.
            ->where('concepts.0.label', 'Salario')->where('concepts.0.delta_text', '$82,125.00')
            ->where('concepts.0.before_text', '$1,642,500.00')->where('concepts.0.after_text', '$1,724,625.00')
            ->where('headline.first_year_text', fn (string $text): bool => str_starts_with($text, '$'))
            ->has('concepts', fn (Assert $concepts) => $concepts->etc()));
});

it('cuts by area, category and level, and group with names and never ids', function (): void {
    $response = $this->get(reportUrl($this->tenant, $this->scenario))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('cuts.areas.0.label', 'Producción')->where('cuts.areas.0.people', 9)
        ->where('cuts.grades.0.label', 'Operador · A')
        ->where('cuts.groups.0.label', 'Sindicalizados'));
    expect($response->getContent())->not->toContain($this->grade->id)->not->toContain($this->area->id);
});

it('shows twelve months, a projection of the horizon, the seniority premium and the sensitivity', function (): void {
    $this->get(reportUrl($this->tenant, $this->scenario))
        ->assertInertia(fn (Assert $page) => $page
            ->has('months', 12)->where('months.0.month', '2027-01-01')
            ->has('projection', 4)->where('projection.0.year', 1)->where('projection.3.year', 4)
            // Hired in 2006 they have 21 years on 2027-01-01: 12 × 21 × $500 = $126,000 each, and $6,300 more at $525.
            ->where('seniority.people', 9)->where('seniority.delta_text', '$56,700.00')
            ->where('seniority.before_text', '$1,134,000.00')
            ->where('sensitivity.one_point_text', fn (string $text): bool => str_starts_with($text, '$') && $text !== '$0.00')
            ->has('assumptions', 5));
});

it('lists every assumption with its source and date', function (): void {
    $this->get(reportUrl($this->tenant, $this->scenario))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assumptions.0.label', 'Inflación')->where('assumptions.0.value', '3')
            ->where('assumptions.0.set_on', '2026-11-04')
            ->where('assumptions.0.source', fn (string $source): bool => str_contains($source, 'Banco de México')));
});

it('warns about individual salaries and about what it could not calculate', function (): void {
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'daily_salary_cents' => 70_000, 'effective_from' => '2026-01-01']);
    $noState = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'default_state' => null]);
    Employee::query()->update(['state' => null]);

    $this->get(reportUrl($this->tenant, $this->scenario))
        ->assertInertia(fn (Assert $page) => $page
            ->where('warnings.individual', ['count' => 1, 'percent' => '11.1', 'over_tenth' => true]));
    $this->get(reportUrl($this->tenant, $noState))
        ->assertInertia(fn (Assert $page) => $page->where('warnings.uncalculated', fn ($lines): bool => in_array('Impuesto sobre nómina', collect($lines)->all(), true)));
});

it('does not warn about individual salaries up to a tenth of the staff', function (): void {
    reportStaff($this->tenant, $this->grade, $this->area, 'E-010');
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'effective_from' => '2026-01-01']);

    $this->get(reportUrl($this->tenant, $this->scenario))
        ->assertInertia(fn (Assert $page) => $page->where('warnings.individual', ['count' => 1, 'percent' => '10', 'over_tenth' => false]));
});

it('says which grades have no tabulator salary and who is left out for it', function (): void {
    $other = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Técnico', 'level' => 'B']);
    reportStaff($this->tenant, $other, $this->area, 'E-777');

    $this->get(reportUrl($this->tenant, $this->scenario))
        ->assertInertia(fn (Assert $page) => $page->where('warnings.uncosted', [['label' => 'Técnico · B', 'people' => 1]]));
});

it('shows the worker gross against the company cost, with the exempt benefits marked', function (): void {
    $vouchers = Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Vales', 'kind' => 'fixed_amount', 'frequency' => 'monthly', 'income_tax_exempt' => true]);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $vouchers->id, 'effective_from' => '2026-01-01', 'value' => 100_000]);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'group_changes' => ['unionized' => 0, 'trusted' => null], 'term_changes' => [['benefit_id' => $vouchers->id, 'value' => 150_000, 'tenure_table' => null]]]);

    $this->get(reportUrl($this->tenant, $scenario))
        ->assertInertia(fn (Assert $page) => $page
            ->where('person_value.rows', function ($rows): bool {
                $vouchers = collect($rows)->firstWhere('label', 'Vales');

                // $500 more a month for nine people: $54,000 a year, gross, for the people and the company alike.
                return $vouchers !== null && $vouchers['person_text'] === '$54,000.00' && $vouchers['exempt'] === true && $vouchers['company_text'] === '$54,000.00';
            })
            ->where('person_value.person_total_text', '$54,000.00')
            ->where('person_value.company_total_text', fn (string $text): bool => $text !== '$0.00'));
});

it('writes the audit entry and the log of a view without a salary or a person', function (): void {
    Log::spy();

    $this->get(reportUrl($this->tenant, $this->scenario))->assertOk();

    $entry = AuditEntry::query()->where('event', 'compensation.report_viewed')->sole();

    expect($entry->metadata)->toBe(['scenario_id' => $this->scenario->id])
        ->and(json_encode($entry->toArray()))->not->toContain('E-001')->not->toContain('50000');
    Log::shouldHaveReceived('info')->with('compensation.report_viewed', ['scenario_id' => $this->scenario->id])->once();
});

it('sends no name, contact, gender or company ID of a person to the page, only the missing ones by ID', function (): void {
    $response = $this->get(reportUrl($this->tenant, $this->scenario))->assertOk();

    foreach (['E-001', 'E-009', 'gender', 'mobile'] as $forbidden) {
        expect($response->getContent())->not->toContain($forbidden);
    }
});

it('is for whoever views compensation and for the scenarios of the account only', function (): void {
    $foreign = Scenario::factory()->create();

    $this->get(reportUrl($this->tenant, $foreign))->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    $this->get(reportUrl($this->tenant, $this->scenario))->assertForbidden();
});

it('loads with a bounded number of queries', function (): void {
    $url = reportUrl($this->tenant, $this->scenario);
    $this->get($url);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->get($url)->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(80);
});
