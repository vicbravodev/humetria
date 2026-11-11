<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Compensation\Actions\DeleteGradeSalary;
use App\Modules\Compensation\Actions\DeleteSalaryOverride;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DeleteGradeSalary::class, DeleteSalaryOverride::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
    workIn($this->tenant);
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
});

it('deletes a salary of the tabulator that was typed with a wrong date, and the one in force goes back to the one before', function (): void {
    Log::spy();
    $right = GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);
    $wrong = GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'daily_salary_cents' => 99_000, 'effective_from' => '2026-06-01']);

    $this->delete(route('tenant.compensation.tabulator.destroy', [$this->tenant, $wrong->id]))->assertRedirect();

    expect(GradeSalary::query()->pluck('id')->all())->toBe([$right->id]);
    Log::shouldHaveReceived('info')->with('compensation.grade_salary_deleted', ['job_grade_id' => $this->grade->id, 'grade_salary_id' => $wrong->id])->once();
    expect(AuditEntry::query()->where('event', 'compensation.grade_salary_deleted')->sole()->metadata)->toBe(['grade_salaries' => 1])
        ->and(json_encode(AuditEntry::query()->get()->map(fn (AuditEntry $entry): array => [$entry->event, $entry->metadata])->all()))->not->toContain('99000')->not->toContain('990');
    $this->get(route('tenant.compensation.tabulator.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('grades.data.0.current.daily_salary', '500')->has('grades.data.0.salaries', 1));
});

it('deletes an individual salary typed with a wrong date and keeps the person\'s other ones', function (): void {
    Log::spy();
    $right = SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'effective_from' => '2026-01-01']);
    $wrong = SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'daily_salary_cents' => 99_000, 'effective_from' => '2026-06-01']);

    $this->delete(route('tenant.compensation.overrides.destroy', [$this->tenant, $wrong->id]))->assertRedirect();

    expect(SalaryOverride::query()->pluck('id')->all())->toBe([$right->id]);
    Log::shouldHaveReceived('info')->with('compensation.override_deleted', ['override_id' => $wrong->id])->once();
    expect(AuditEntry::query()->where('event', 'compensation.override_deleted')->sole()->metadata)->toBe(['overrides' => 1])
        ->and(json_encode(AuditEntry::query()->get()->toArray()))->not->toContain('E-001');
});

it('answers 404 for the salary of another company and 403 to whoever cannot manage', function (): void {
    $foreignSalary = GradeSalary::factory()->create();
    $foreignOverride = SalaryOverride::factory()->create();
    $mine = GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id]);

    $this->delete(route('tenant.compensation.tabulator.destroy', [$this->tenant, $foreignSalary->id]))->assertNotFound();
    $this->delete(route('tenant.compensation.overrides.destroy', [$this->tenant, $foreignOverride->id]))->assertNotFound();

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    $this->delete(route('tenant.compensation.tabulator.destroy', [$this->tenant, $mine->id]))->assertForbidden();

    expect(GradeSalary::query()->withoutGlobalScopes()->count())->toBe(2)->and(SalaryOverride::query()->withoutGlobalScopes()->count())->toBe(1);
});
