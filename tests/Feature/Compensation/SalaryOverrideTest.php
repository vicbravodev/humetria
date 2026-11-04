<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Compensation\Actions\ListSalaryOverrides;
use App\Modules\Compensation\Actions\SaveSalaryOverride;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Support\OverrideTargets;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ListSalaryOverrides::class, SaveSalaryOverride::class, OverrideTargets::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-03 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
});

/**
 * A person of the roster the calculator can cost, in the account of the test; `$attributes` overrides any column.
 *
 * @param  array<string, mixed>  $attributes
 */
function overridePerson(Tenant $tenant, JobGrade $grade, string $number, array $attributes = []): Employee
{
    return Employee::factory()->create([
        'tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized',
        'employee_number' => $number, ...$attributes,
    ]);
}

/**
 * The fields the form of an individual salary sends.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function overrideForm(array $override = []): array
{
    return ['employee_number' => 'E-001', 'daily_salary' => '650.25', 'effective_from' => '2026-01-01', 'note' => 'Por antigüedad en la planta', ...$override];
}

it('lists the people with an individual salary paginated, each with the salary in force today and its history', function (): void {
    overridePerson($this->tenant, $this->grade, 'E-001');
    overridePerson($this->tenant, $this->grade, 'E-002');
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'daily_salary_cents' => 60000, 'effective_from' => '2025-01-01']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'daily_salary_cents' => 65025, 'effective_from' => '2026-01-01', 'note' => 'Por antigüedad']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-001', 'daily_salary_cents' => 70000, 'effective_from' => '2027-01-01']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-002', 'effective_from' => '2027-06-01']);

    $this->get(route('tenant.compensation.overrides.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/overrides')
            ->where('people.total', 2)
            ->where('people.per_page', 15)
            ->where('filters', ['q' => ''])
            ->where('max_rows', 2000)
            ->where('people.data.0.employee_number', 'E-001')
            ->where('people.data.0.grade', 'Operador · A')
            ->where('people.data.0.active', true)
            // The one in force today is the 2026 salary, not the one of next year.
            ->where('people.data.0.current', ['daily_salary' => '650.25', 'daily_salary_text' => '$650.25', 'effective_from' => '2026-01-01', 'note' => 'Por antigüedad'])
            ->where('people.data.0.salaries.0.effective_from', '2027-01-01')
            ->where('people.data.0.salaries.1.effective_from', '2026-01-01')
            ->where('people.data.0.salaries.2.daily_salary', '600')
            // Everything of E-002 starts later: nothing is in force yet.
            ->where('people.data.1.employee_number', 'E-002')
            ->where('people.data.1.current', null));
});

it('searches the people by their ID or by a word of the note, without caring for case or accents', function (): void {
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'AB-100', 'note' => 'Convenio especial']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'AB-100', 'effective_from' => '2027-01-01']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'CD-200', 'note' => 'Por antigüedad']);

    $this->get(route('tenant.compensation.overrides.index', [$this->tenant, 'q' => 'ab-1']))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 1)->where('people.data.0.employee_number', 'AB-100')->has('people.data.0.salaries', 2)->where('filters.q', 'ab-1'));
    $this->get(route('tenant.compensation.overrides.index', [$this->tenant, 'q' => 'ANTIGUEDAD']))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 1)->where('people.data.0.employee_number', 'CD-200'));
});

it('marks a person who is no longer active or cannot be costed, and still lists what they were given', function (): void {
    overridePerson($this->tenant, $this->grade, 'E-GONE', ['deactivated_at' => now()]);
    overridePerson($this->tenant, $this->grade, 'E-NOGRADE', ['job_grade_id' => null]);
    foreach (['E-GONE', 'E-NOGRADE', 'E-NEVER'] as $number) {
        SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => $number]);
    }

    $this->get(route('tenant.compensation.overrides.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.total', 3)
            ->where('people.data.0.active', false)->where('people.data.0.grade', null)
            ->where('people.data.1.active', false)
            ->where('people.data.2.active', false));
});

it('pages the people fifteen at a time and keeps the search in the page links', function (): void {
    foreach (range(1, 16) as $number) {
        SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => sprintf('E-%03d', $number)]);
    }

    $this->get(route('tenant.compensation.overrides.index', [$this->tenant, 'q' => 'e-', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.current_page', 2)
            ->has('people.data', 1)
            ->where('people.data.0.employee_number', 'E-016')
            ->where('people.prev_page_url', fn (string $url): bool => str_contains($url, 'q=e-')));
});

it('loads the page with the same queries whatever the people it holds', function (): void {
    $url = route('tenant.compensation.overrides.index', $this->tenant);
    // The first request of a session also loads the permissions and the roles, which later ones find in memory.
    $this->get($url);
    $queries = function () use ($url): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();

        return count(DB::getQueryLog());
    };
    $withSalary = function (int $from, int $to): void {
        foreach (range($from, $to) as $number) {
            overridePerson($this->tenant, JobGrade::factory()->create(['tenant_id' => $this->tenant->id]), "P-{$number}");
            SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => "P-{$number}"]);
            SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => "P-{$number}", 'effective_from' => '2027-01-01']);
        }
    };

    $withSalary(1, 1);
    $few = $queries();
    $withSalary(2, 10);

    expect($queries())->toBe($few);
});

it('saves the salary of a person by their company ID, keeping the ID as the roster has it', function (): void {
    overridePerson($this->tenant, $this->grade, 'E-001');
    Log::spy();

    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm(['employee_number' => ' e-001 ']))->assertSessionHasNoErrors()->assertRedirect();

    $override = SalaryOverride::query()->sole();

    expect($override->only(['employee_number', 'daily_salary_cents', 'note', 'tenant_id']))->toBe(['employee_number' => 'E-001', 'daily_salary_cents' => 65025, 'note' => 'Por antigüedad en la planta', 'tenant_id' => $this->tenant->id])
        ->and($override->effective_from->toDateString())->toBe('2026-01-01');
    // Ids only: a salary is money, and who earns it is not for a log.
    Log::shouldHaveReceived('info')->with('compensation.override_saved', ['override_id' => $override->id])->once();
});

it('writes the audit entry of a saved salary with a count only, never the person or the salary', function (): void {
    overridePerson($this->tenant, $this->grade, 'E-001');

    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm());

    $entry = AuditEntry::query()->where('event', 'compensation.override_saved')->sole();

    expect($entry->metadata)->toBe(['overrides' => 1])
        ->and(json_encode($entry->toArray()))->not->toContain('E-001')->not->toContain('650')->not->toContain('65025');
});

it('replaces a salary with another from a later date and keeps the history', function (): void {
    overridePerson($this->tenant, $this->grade, 'E-001');

    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm(['daily_salary' => '600', 'effective_from' => '2025-01-01']))->assertSessionHasNoErrors();
    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm(['daily_salary' => '700', 'effective_from' => '2026-07-01', 'note' => '']))->assertSessionHasNoErrors();

    expect(SalaryOverride::query()->orderBy('effective_from')->get()->map(fn (SalaryOverride $override): array => [$override->daily_salary_cents, $override->effective_from->toDateString(), $override->note])->all())
        ->toBe([[60000, '2025-01-01', 'Por antigüedad en la planta'], [70000, '2026-07-01', null]]);

    // On the day the second one starts it is the one in force, and the first is only history.
    $this->get(route('tenant.compensation.overrides.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.total', 1)
            ->where('people.data.0.current.daily_salary', '700')
            ->where('people.data.0.salaries.1.daily_salary', '600'));
});

it('corrects the salary of the same date instead of adding another', function (): void {
    overridePerson($this->tenant, $this->grade, 'E-001');

    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm());
    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm(['employee_number' => 'E-001', 'daily_salary' => '651', 'note' => '']));

    expect(SalaryOverride::query()->count())->toBe(1)
        ->and(SalaryOverride::query()->sole()->daily_salary_cents)->toBe(65100)
        ->and(SalaryOverride::query()->sole()->note)->toBeNull();
});

it('rejects an ID that does not exist, is deactivated or cannot be costed, and writes nothing', function (string $number, string $message): void {
    overridePerson($this->tenant, $this->grade, 'E-GONE', ['deactivated_at' => now()]);
    overridePerson($this->tenant, $this->grade, 'E-NOGRADE', ['job_grade_id' => null]);
    overridePerson($this->tenant, $this->grade, 'E-NODATE', ['hired_on' => null]);
    Log::spy();

    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm(['employee_number' => $number]))->assertSessionHasErrors(['employee_number' => $message]);

    expect(SalaryOverride::query()->count())->toBe(0)->and(AuditEntry::query()->where('event', 'compensation.override_saved')->count())->toBe(0);
    Log::shouldNotHaveReceived('info', ['compensation.override_saved', Mockery::any()]);
})->with([
    'unknown' => ['E-404', 'No hay una persona activa con este ID de la empresa.'],
    'deactivated' => ['e-gone', 'No hay una persona activa con este ID de la empresa.'],
    'without category and level' => ['E-NOGRADE', 'A esta persona le falta categoría y nivel, fecha de ingreso o grupo en el padrón: complétalos primero.'],
    'without hire date' => ['e-nodate', 'A esta persona le falta categoría y nivel, fecha de ingreso o grupo en el padrón: complétalos primero.'],
]);

it('rejects a salary of zero or negative, a note that is too long and a missing date or ID', function (array $override, string $field): void {
    overridePerson($this->tenant, $this->grade, 'E-001');

    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm($override))->assertSessionHasErrors($field);

    expect(SalaryOverride::query()->count())->toBe(0);
})->with([
    'zero salary' => [['daily_salary' => '0'], 'daily_salary'],
    'negative salary' => [['daily_salary' => '-1'], 'daily_salary'],
    'text salary' => [['daily_salary' => 'mucho'], 'daily_salary'],
    'above the largest salary' => [['daily_salary' => '1000000.01'], 'daily_salary'],
    'a note longer than the column' => [['note' => str_repeat('a', 501)], 'note'],
    'no ID' => [['employee_number' => ''], 'employee_number'],
    'an ID longer than the column' => [['employee_number' => str_repeat('a', 51)], 'employee_number'],
    'no date' => [['effective_from' => ''], 'effective_from'],
    'a date that does not exist' => [['effective_from' => '2026-02-30'], 'effective_from'],
]);

it('accepts the longest note and the largest salary', function (): void {
    overridePerson($this->tenant, $this->grade, 'E-001');

    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm(['note' => str_repeat('a', 500), 'daily_salary' => '1000000']))->assertSessionHasNoErrors();

    expect(SalaryOverride::query()->sole()->daily_salary_cents)->toBe(100_000_000);
});

it('opens the individual salaries to owner and hr and closes them to the other roles, for reading and for writing', function (TenantRole $role, int $status): void {
    $company = Tenant::factory()->create(['slug' => 'roles']);
    $grade = JobGrade::factory()->create(['tenant_id' => $company->id]);
    overridePerson($company, $grade, 'E-001');
    $this->actingAs(memberOf($company, $role));

    $this->get(route('tenant.compensation.overrides.index', $company))->assertStatus($status);
    $this->post(route('tenant.compensation.overrides.store', $company), overrideForm())->assertStatus($status === 200 ? 302 : $status);

    expect(SalaryOverride::query()->withoutGlobalScopes()->where('tenant_id', $company->id)->count())->toBe($status === 200 ? 1 : 0);
})->with([
    'owner' => [TenantRole::Owner, 200],
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 403],
    'committee' => [TenantRole::Committee, 403],
]);

it('keeps two accounts\' individual salaries apart', function (): void {
    $other = Tenant::factory()->create(['slug' => 'beta']);
    $otherGrade = JobGrade::factory()->create(['tenant_id' => $other->id]);
    overridePerson($other, $otherGrade, 'SECRETO-1');
    SalaryOverride::factory()->create(['tenant_id' => $other->id, 'employee_number' => 'SECRETO-1', 'daily_salary_cents' => 987654, 'note' => 'Nota secreta']);

    $response = $this->get(route('tenant.compensation.overrides.index', $this->tenant))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->where('people.total', 0));
    expect($response->getContent())->not->toContain('SECRETO-1')->and($response->getContent())->not->toContain('9876.54')->and($response->getContent())->not->toContain('Nota secreta');

    // A person of the other account is not found here, even if the ID is right.
    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm(['employee_number' => 'SECRETO-1']))
        ->assertSessionHasErrors(['employee_number' => 'No hay una persona activa con este ID de la empresa.']);

    // The same ID is another person in this account, with their own salary.
    overridePerson($this->tenant, $this->grade, 'SECRETO-1');
    $this->post(route('tenant.compensation.overrides.store', $this->tenant), overrideForm(['employee_number' => 'SECRETO-1']))->assertSessionHasNoErrors();

    expect(SalaryOverride::query()->withoutGlobalScopes()->where('employee_number', 'SECRETO-1')->count())->toBe(2)
        ->and(SalaryOverride::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->sole()->daily_salary_cents)->toBe(987654)
        ->and(SalaryOverride::query()->count())->toBe(1);

    // Another account's page answers 404 (not 403) even to its own owner.
    $this->get(route('tenant.compensation.overrides.index', $other))->assertNotFound();
    $this->post(route('tenant.compensation.overrides.store', $other), overrideForm())->assertNotFound();
});
