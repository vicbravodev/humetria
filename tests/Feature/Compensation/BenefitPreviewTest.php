<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Support\BenefitPreview;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Route;

mutates(BenefitPreview::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);

    foreach (range(1, 4) as $number) {
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $grade->id, 'hired_on' => '2025-01-01', 'staff_group' => $number <= 3 ? 'unionized' : 'trusted', 'state' => 'JAL', 'employee_number' => "E-{$number}"]);
    }
});

/** @param  array<string, mixed>  $override */
function benefitPreviewForm(array $override = []): array
{
    return [
        'name' => '', 'kind' => 'fixed_amount', 'frequency' => 'monthly', 'group' => '', 'min_tenure_months' => '0',
        'sbc_integration' => 'never', 'value' => '100', ...$override,
    ];
}

it('costs a new benefit today for whoever it reaches, with its charges, and saves nothing', function (): void {
    $url = route('tenant.compensation.benefits.preview', $this->tenant);

    // $100 a month to 4 people that neither integrates nor pays payroll tax: $4,800 a year, $100 a person a month.
    $this->postJson($url, benefitPreviewForm())->assertOk()->assertExactJson([
        'people' => 4, 'yearly_text' => '$4,800', 'per_person_month_text' => '$100',
    ]);
    $this->postJson($url, benefitPreviewForm(['group' => 'unionized']))->assertOk()->assertJson(['people' => 3, 'yearly_text' => '$3,600']);
    // In the payroll tax of Jalisco (3 %), it costs more than what the people receive.
    $this->postJson($url, benefitPreviewForm(['in_payroll_tax' => '1']))->assertOk()->assertJson(['people' => 4, 'yearly_text' => '$4,944']);

    expect(Benefit::query()->count())->toBe(0);
});

it('answers the problems of the form and is only for whoever manages compensation', function (): void {
    $url = route('tenant.compensation.benefits.preview', $this->tenant);

    $this->postJson($url, benefitPreviewForm(['value' => 'mucho']))->assertUnprocessable()->assertJsonValidationErrors('value');
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->postJson($url, benefitPreviewForm())->assertForbidden();
    expect(Route::getRoutes()->getByName('tenant.compensation.benefits.preview')?->gatherMiddleware())->toContain('throttle:compensation-preview');
});
