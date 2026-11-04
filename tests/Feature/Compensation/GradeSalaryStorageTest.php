<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The database holds the rules of the tabulator by itself (CLAUDE.md, «Base de datos»): what the forms and the importer
 * check is checked again here, so nothing that skips them can store a salary that makes no sense.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);
});

/** Writes a salary past the model, inside a savepoint so the failed statement does not poison the test's transaction. */
function rejectedGradeSalary(Tenant $tenant, string $jobGradeId, string $constraint, int $cents = 50000, string $from = '2026-01-01'): void
{
    expect(fn () => DB::transaction(fn () => DB::table('grade_salaries')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'job_grade_id' => $jobGradeId,
        'daily_salary_cents' => $cents, 'effective_from' => $from, 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class, $constraint);
}

it('stores a salary with a random UUID v4', function (): void {
    $salary = GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id]);

    expect(Str::isUuid($salary->id, 4))->toBeTrue();
});

it('refuses a salary of zero or less', function (int $cents): void {
    rejectedGradeSalary($this->tenant, $this->grade->id, 'grade_salaries_salary_positive', $cents);
})->with([0, -1]);

it('refuses a second salary of the same grade and date', function (): void {
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'effective_from' => '2026-01-01']);

    rejectedGradeSalary($this->tenant, $this->grade->id, 'grade_salaries_grade_date_unique');
});

it('allows the same date for another grade and another date for the same grade', function (): void {
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'effective_from' => '2026-01-01']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'effective_from' => '2027-01-01']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-01-01']);

    expect(GradeSalary::query()->count())->toBe(3);
});

it('refuses a salary that points to the grade of another company', function (): void {
    $foreign = JobGrade::factory()->create();

    rejectedGradeSalary($this->tenant, $foreign->id, 'grade_salaries_job_grade_id_tenant_id_foreign');
});

it('does not let a grade with salaries be deleted', function (): void {
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id]);

    expect(fn () => DB::transaction(fn () => $this->grade->delete()))->toThrow(QueryException::class, 'grade_salaries_job_grade_id_tenant_id_foreign');
});
