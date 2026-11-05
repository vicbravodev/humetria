<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The database holds the rules of the individual salaries by itself (CLAUDE.md, «Base de datos»): what the form and the
 * importer check is checked again here, so nothing that skips them can store a salary that makes no sense.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/** Writes an individual salary past the model, inside a savepoint so the failed statement does not poison the test's transaction. */
function rejectedOverride(Tenant $tenant, string $constraint, string $number = 'E-001', int $cents = 60000, string $from = '2026-01-01'): void
{
    expect(fn () => DB::transaction(fn () => DB::table('salary_overrides')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'employee_number' => $number,
        'daily_salary_cents' => $cents, 'effective_from' => $from, 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class, $constraint);
}

it('stores an individual salary with a random UUID v4', function (): void {
    expect(Str::isUuid(SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id])->id, 4))->toBeTrue();
});

it('refuses a salary of zero or less', function (int $cents): void {
    rejectedOverride($this->tenant, 'salary_overrides_salary_positive', cents: $cents);
})->with([0, -1]);

it('refuses an employee number that is empty', function (): void {
    rejectedOverride($this->tenant, 'salary_overrides_number_filled', number: '  ');
});

it('refuses a second salary of the same person and date, whatever the case of the number', function (): void {
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'effective_from' => '2026-01-01']);

    rejectedOverride($this->tenant, 'salary_overrides_person_date_unique', number: 'e-001');
});

it('allows the same date for another person, another date for the same person and the same person in another company', function (): void {
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'effective_from' => '2026-01-01']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'effective_from' => '2027-01-01']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-002', 'effective_from' => '2026-01-01']);
    SalaryOverride::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'employee_number' => 'E-001', 'effective_from' => '2026-01-01']);

    expect(SalaryOverride::query()->withoutGlobalScopes()->count())->toBe(4);
});
