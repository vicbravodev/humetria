<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\ScenarioInputBuilder;
use App\Modules\Compensation\Support\ScenarioPreview;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Route;

mutates(ScenarioPreview::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);

    foreach (range(1, 10) as $number) {
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $grade->id, 'hired_on' => '2025-01-01', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => "E-{$number}"]);
    }

    // One person without a category and one in a category with no salary: both are left out of the cost.
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => null, 'employee_number' => 'E-90']);
    $unpaid = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Coordinador', 'level' => 'A']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $unpaid->id, 'hired_on' => '2025-01-01', 'staff_group' => 'unionized', 'employee_number' => 'E-91']);
});

/** @param  array<string, mixed>  $override */
function previewForm(array $override = []): array
{
    return [
        'name' => 'Revisión 2027', 'effective_on' => '2027-01-01', 'retroactive_months' => '2', 'horizon_years' => '3',
        'unionized_raise' => '5', 'trusted_raise' => '', 'default_state' => '', ...$override,
    ];
}

function previewUrl(Tenant $tenant): string
{
    return route('tenant.compensation.scenarios.preview', $tenant);
}

it('costs what is typed with the engine of the report, and saves nothing', function (): void {
    $response = $this->postJson(previewUrl($this->tenant), previewForm())->assertOk();
    $same = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'effective_on' => '2027-01-01', 'retroactive_months' => 2, 'group_changes' => ['unionized' => 500, 'trusted' => null]]);
    $cost = ScenarioCost::firstYear(resolve(ScenarioInputBuilder::class)->build($same));

    expect(Scenario::query()->count())->toBe(1)
        ->and($response->json())->toMatchArray([
            'people' => 10,
            'left_out' => 2,
            'first_year_cents' => $cost->firstYearCents(),
            'first_year_text' => Money::pesos($cost->firstYearCents()),
            'retroactive_text' => Money::pesos($cost->retroactiveCents),
            'retroactive_month_text' => Money::pesos($cost->retroactiveMonthCents()),
            'recurring_text' => Money::pesos($cost->recurringCents),
            'per_person_month_text' => Money::pesos(intdiv($cost->recurringCents + 60, 120)),
            'benefits' => [],
        ])
        // 5 % of $500 for 10 people is $91,250 of salary alone over a year of more than $2 million: between 5 and 6 %.
        ->and($response->json('over_today_text'))->toMatch('/\A[56](\.\d)?\z/');
});

it('costs a fixed amount a month and leaves the name for later', function (): void {
    $amount = $this->postJson(previewUrl($this->tenant), previewForm(['name' => '', 'raise_unit' => 'amount', 'unionized_raise' => '760', 'retroactive_months' => '0']))->assertOk();
    $percent = $this->postJson(previewUrl($this->tenant), previewForm(['unionized_raise' => '5', 'retroactive_months' => '0']))->assertOk();

    // $760 a month is $25 a day: 5 % of $500.
    expect($amount->json('first_year_cents'))->toBe($percent->json('first_year_cents'))->toBeGreaterThan(0);
});

it('says what each benefit that changes costs a year, by benefit', function (): void {
    $bonus = Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bono de transporte', 'kind' => 'fixed_amount', 'frequency' => 'monthly']);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $bonus->id, 'effective_from' => '2026-01-01', 'value' => 100_000]);

    $response = $this->postJson(previewUrl($this->tenant), previewForm([
        'unionized_raise' => '0', 'term_changes' => [['benefit_id' => $bonus->id, 'value' => '1100']],
    ]))->assertOk();

    // $100 more a month for 10 people is $12,000 a year.
    expect($response->json('benefits'))->toBe([['benefit_id' => $bonus->id, 'yearly_text' => '$12,000']]);
});

it('answers the problems of what is typed as validation errors', function (): void {
    $this->postJson(previewUrl($this->tenant), previewForm(['unionized_raise' => 'cinco']))->assertUnprocessable()->assertJsonValidationErrors('unionized_raise');
    $this->postJson(previewUrl($this->tenant), previewForm(['retroactive_months' => '37']))->assertUnprocessable()->assertJsonValidationErrors('retroactive_months');
    $this->postJson(previewUrl($this->tenant), previewForm(['unionized_raise' => '101']))->assertUnprocessable()->assertJsonValidationErrors('unionized_raise');
});

it('lets whoever manages compensation preview, and nobody else', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->postJson(previewUrl($this->tenant), previewForm())->assertOk();
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->postJson(previewUrl($this->tenant), previewForm())->assertForbidden();
    $this->actingAs(memberOf(Tenant::factory()->create(), TenantRole::Owner))->postJson(previewUrl($this->tenant), previewForm())->assertNotFound();
});

it('limits how often one person asks for a preview', function (): void {
    expect(Route::getRoutes()->getByName('tenant.compensation.scenarios.preview')?->gatherMiddleware())->toContain('throttle:compensation-preview');
});
