<?php

declare(strict_types=1);

use App\Modules\Compensation\Actions\ListTabulator;
use App\Modules\Compensation\Actions\SaveGradeSalary;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ListTabulator::class, SaveGradeSalary::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-03 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
});

/**
 * The fields the form of a salary sends.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function tabulatorForm(array $override = []): array
{
    return ['category' => 'Operador', 'level' => 'A', 'daily_salary' => '524.65', 'effective_from' => '2026-01-01', ...$override];
}

it('lists the tabulator paginated by category and level with server-side search', function (): void {
    $operatorA = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'B']);
    JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Técnico', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $operatorA->id, 'daily_salary_cents' => 50000, 'effective_from' => '2025-01-01']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $operatorA->id, 'daily_salary_cents' => 52465, 'effective_from' => '2026-01-01']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $operatorA->id, 'daily_salary_cents' => 60000, 'effective_from' => '2027-01-01']);

    $this->get(route('tenant.compensation.tabulator.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/tabulator')
            ->where('grades.total', 3)
            ->where('grades.per_page', 15)
            ->where('filters', ['q' => ''])
            ->where('max_rows', 2000)
            ->where('grades.data.0.category', 'Operador')
            ->where('grades.data.0.level', 'A')
            // The one in force today is the 2026 salary, not the one of next year.
            ->where('grades.data.0.current', ['daily_salary' => '524.65', 'daily_salary_text' => '$524.65', 'effective_from' => '2026-01-01'])
            ->where('grades.data.0.salaries.0.effective_from', '2027-01-01')
            ->where('grades.data.0.salaries.1.effective_from', '2026-01-01')
            ->where('grades.data.0.salaries.2.daily_salary', '500')
            ->where('grades.data.1.level', 'B')
            ->where('grades.data.1.current', null)
            ->where('grades.data.1.salaries', [])
            ->where('grades.data.2.category', 'Técnico'));

    $this->get(route('tenant.compensation.tabulator.index', [$this->tenant, 'q' => 'TECNICO']))
        ->assertInertia(fn (Assert $page) => $page->where('grades.total', 1)->where('grades.data.0.category', 'Técnico')->where('filters.q', 'TECNICO'));
});

it('pages the tabulator fifteen grades at a time and keeps the search in the page links', function (): void {
    foreach (range(1, 16) as $number) {
        JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => sprintf('Operador %02d', $number), 'level' => 'A']);
    }

    $this->get(route('tenant.compensation.tabulator.index', [$this->tenant, 'q' => 'operador', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('grades.current_page', 2)
            ->has('grades.data', 1)
            ->where('grades.data.0.category', 'Operador 16')
            ->where('grades.prev_page_url', fn (string $url): bool => str_contains($url, 'q=operador')));
});

it('loads the tabulator with the same queries whatever the grades it holds', function (): void {
    $url = route('tenant.compensation.tabulator.index', $this->tenant);
    // The first request of a session also loads the permissions and the roles, which later ones find in memory.
    $this->get($url);
    $queries = function () use ($url): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();

        return count(DB::getQueryLog());
    };
    $withSalary = fn (int $count) => GradeSalary::factory()->count($count)->create(['tenant_id' => $this->tenant->id]);

    $withSalary(1);
    $few = $queries();
    $withSalary(10);

    expect($queries())->toBe($few);
});

it('saves the salary of a category and level, creating the grade when it is new', function (): void {
    Log::spy();

    $this->post(route('tenant.compensation.tabulator.store', $this->tenant), tabulatorForm())->assertSessionHasNoErrors()->assertRedirect();

    $grade = JobGrade::query()->sole();
    $salary = GradeSalary::query()->sole();

    expect($grade->only(['category', 'level']))->toBe(['category' => 'Operador', 'level' => 'A'])
        ->and($salary->job_grade_id)->toBe($grade->id)
        ->and($salary->daily_salary_cents)->toBe(52465)
        ->and($salary->effective_from->toDateString())->toBe('2026-01-01')
        ->and($salary->tenant_id)->toBe($this->tenant->id);
    // Ids only: a salary is money, which stays out of logs.
    Log::shouldHaveReceived('info')->with('compensation.grade_salary_saved', ['job_grade_id' => $grade->id, 'grade_salary_id' => $salary->id])->once();
});

it('uses the grade the company already has, whatever the case, and keeps one salary per date', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);

    $this->post(route('tenant.compensation.tabulator.store', $this->tenant), tabulatorForm(['category' => ' OPERADOR ', 'level' => 'a']))->assertSessionHasNoErrors();
    $this->post(route('tenant.compensation.tabulator.store', $this->tenant), tabulatorForm(['category' => 'operador', 'daily_salary' => '600']))->assertSessionHasNoErrors();
    $this->post(route('tenant.compensation.tabulator.store', $this->tenant), tabulatorForm(['effective_from' => '2027-01-01', 'daily_salary' => '650,5']))->assertSessionHasNoErrors();

    expect(JobGrade::query()->count())->toBe(1)
        ->and(GradeSalary::query()->orderBy('effective_from')->get()->map(fn (GradeSalary $salary): array => [$salary->job_grade_id, $salary->daily_salary_cents, $salary->effective_from->toDateString()])->all())
        ->toBe([[$grade->id, 60000, '2026-01-01'], [$grade->id, 65050, '2027-01-01']]);
});

it('accepts a salary with a dollar sign and a space and refuses the ones that are not pesos and cents', function (string $typed, string|int $outcome): void {
    $this->post(route('tenant.compensation.tabulator.store', $this->tenant), tabulatorForm(['daily_salary' => $typed]));

    if (is_int($outcome)) {
        expect(GradeSalary::query()->sole()->daily_salary_cents)->toBe($outcome);
    } else {
        expect(GradeSalary::query()->count())->toBe(0);
    }
})->with([
    'with a dollar sign' => ['$ 524.65', 52465],
    'whole pesos' => ['500', 50000],
    'zero' => ['0', 'refused'],
    'zero with cents' => ['0.00', 'refused'],
    'negative' => ['-500', 'refused'],
    'text' => ['quinientos', 'refused'],
    'a fraction of a cent' => ['524.655', 'refused'],
    'thousands separator' => ['1,234.50', 'refused'],
    'above the largest' => ['1000000.01', 'refused'],
]);

it('rejects a salary of zero or negative and a category and level that cannot be stored', function (array $override, string $field): void {
    $this->post(route('tenant.compensation.tabulator.store', $this->tenant), tabulatorForm($override))->assertSessionHasErrors($field);

    expect(GradeSalary::query()->count())->toBe(0)->and(JobGrade::query()->count())->toBe(0);
})->with([
    'zero salary' => [['daily_salary' => '0'], 'daily_salary'],
    'negative salary' => [['daily_salary' => '-1'], 'daily_salary'],
    'no salary' => [['daily_salary' => ''], 'daily_salary'],
    'no category' => [['category' => '  '], 'category'],
    'no level' => [['level' => ''], 'level'],
    'a category longer than the column' => [['category' => str_repeat('a', 121)], 'category'],
    'a level longer than the column' => [['level' => str_repeat('a', 61)], 'level'],
    'no date' => [['effective_from' => ''], 'effective_from'],
    'a date that does not exist' => [['effective_from' => '2026-02-30'], 'effective_from'],
]);

it('accepts the longest category and level and the largest salary', function (): void {
    $this->post(route('tenant.compensation.tabulator.store', $this->tenant), tabulatorForm(['category' => str_repeat('a', 120), 'level' => str_repeat('b', 60), 'daily_salary' => '1000000']))
        ->assertSessionHasNoErrors();

    expect(GradeSalary::query()->sole()->daily_salary_cents)->toBe(100_000_000);
});

it('opens the tabulator to owner and hr and closes it to the other roles, for reading and for writing', function (TenantRole $role, int $status): void {
    $company = Tenant::factory()->create(['slug' => 'roles']);
    $this->actingAs(memberOf($company, $role));

    $this->get(route('tenant.compensation.tabulator.index', $company))->assertStatus($status);
    $this->post(route('tenant.compensation.tabulator.store', $company), tabulatorForm())->assertStatus($status === 200 ? 302 : $status);

    expect(GradeSalary::query()->withoutGlobalScopes()->where('tenant_id', $company->id)->count())->toBe($status === 200 ? 1 : 0);
})->with([
    'owner' => [TenantRole::Owner, 200],
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 403],
    'committee' => [TenantRole::Committee, 403],
]);

it('lists the tabulator of a union too', function (): void {
    $union = Tenant::factory()->union()->create(['slug' => 'sindicato']);
    $this->actingAs(memberOf($union, TenantRole::Owner));
    GradeSalary::factory()->create(['tenant_id' => $union->id]);

    $this->get(route('tenant.compensation.tabulator.index', $union))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('grades.total', 1));
});

it('keeps two accounts\' tabulators apart', function (): void {
    $other = Tenant::factory()->create(['slug' => 'beta']);
    $secretGrade = JobGrade::factory()->create(['tenant_id' => $other->id, 'category' => 'Soldador secreto', 'level' => 'Z']);
    GradeSalary::factory()->create(['tenant_id' => $other->id, 'job_grade_id' => $secretGrade->id, 'daily_salary_cents' => 987654]);

    $response = $this->get(route('tenant.compensation.tabulator.index', $this->tenant))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->where('grades.total', 0));
    expect($response->getContent())->not->toContain('Soldador secreto')->and($response->getContent())->not->toContain('9876.54');

    // The same category and level are another grade in this account, with its own salaries.
    $this->post(route('tenant.compensation.tabulator.store', $this->tenant), tabulatorForm(['category' => 'Soldador secreto', 'level' => 'Z']))->assertSessionHasNoErrors();

    expect(JobGrade::query()->withoutGlobalScopes()->where('category', 'Soldador secreto')->count())->toBe(2)
        ->and(GradeSalary::query()->withoutGlobalScopes()->where('job_grade_id', $secretGrade->id)->sole()->daily_salary_cents)->toBe(987654)
        ->and(GradeSalary::query()->count())->toBe(1);

    // Another account's page answers 404 (not 403) even to its own owner.
    $this->get(route('tenant.compensation.tabulator.index', $other))->assertNotFound();
    $this->post(route('tenant.compensation.tabulator.store', $other), tabulatorForm())->assertNotFound();
});
