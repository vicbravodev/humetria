<?php

declare(strict_types=1);

use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-11-03 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
});

/**
 * The fields the form of a benefit sends.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function benefitForm(array $override = []): array
{
    return [
        'name' => 'Bono de transporte',
        'kind' => 'fixed_amount',
        'frequency' => 'monthly',
        'group' => '',
        'job_grade_ids' => [],
        'min_tenure_months' => '0',
        'sbc_integration' => 'always',
        'sbc_cap' => '',
        'in_payroll_tax' => '1',
        'cap' => '',
        'value' => '500.50',
        'effective_from' => '2026-01-01',
        ...$override,
    ];
}

it('creates the base benefits the first time and lists them paginated, base first and then by name', function (): void {
    $this->get(route('tenant.compensation.benefits.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/benefits')
            ->where('benefits.total', 11)
            ->where('counts', ['active' => 11, 'archived' => 0])
            ->where('filters', ['q' => '', 'status' => 'active'])
            ->has('benefits.data', 11)
            ->where('benefits.data.0.name', 'Aguinaldo')
            ->where('benefits.data.0.is_base', true)
            ->where('benefits.data.0.current.value_text', '15 días')
            ->where('benefits.data.0.current.effective_from', '2026-01-01')
            ->where('benefits.data.0.warning', null)
            ->has('kinds', 4)->has('frequencies', 3)->has('groups', 2)->has('sbc_integrations', 3)
            ->where('risk_premium', null));

    expect(Benefit::query()->whereNotNull('base_key')->count())->toBe(11);
});

it('searches the catalog on the server without caring for case or accents and shows the archived apart', function (): void {
    Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bono de transporte']);
    Benefit::factory()->archived()->create(['tenant_id' => $this->tenant->id, 'name' => 'Premio de antigüedad']);

    $this->get(route('tenant.compensation.benefits.index', [$this->tenant, 'q' => 'TRANSPORTE']))
        ->assertInertia(fn (Assert $page) => $page->where('benefits.total', 1)->where('benefits.data.0.name', 'Bono de transporte')->where('filters.q', 'TRANSPORTE'));

    $this->get(route('tenant.compensation.benefits.index', [$this->tenant, 'status' => 'archived', 'q' => 'antiguedad']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('benefits.total', 1)->where('benefits.data.0.name', 'Premio de antigüedad')->where('benefits.data.0.archived', true)
            ->where('counts', ['active' => 12, 'archived' => 1])->where('filters.status', 'archived'));
});

it('sends each benefit with its terms and warns when one is below the law', function (): void {
    $this->get(route('tenant.compensation.benefits.index', $this->tenant));
    $bonus = Benefit::query()->where('base_key', 'christmas_bonus')->sole();
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $bonus->id, 'effective_from' => '2027-01-01', 'value' => 1400, 'tenure_table' => null]);

    $this->get(route('tenant.compensation.benefits.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('benefits.data.0.terms.0.effective_from', '2027-01-01')
            ->where('benefits.data.0.terms.0.value', '14')
            ->where('benefits.data.0.terms.0.value_text', '14 días')
            ->where('benefits.data.0.terms.0.warning', 'Está por debajo del mínimo de la ley: 15 días.')
            ->where('benefits.data.0.terms.1.warning', null)
            // The one in force today is the 2026 term, not the one of next year.
            ->where('benefits.data.0.current.value_text', '15 días'));
});

it('sends the table of a benefit that grows with seniority', function (): void {
    $this->get(route('tenant.compensation.benefits.index', $this->tenant));
    $vacation = Benefit::query()->where('base_key', 'vacation')->sole();

    $this->get(route('tenant.compensation.benefits.index', [$this->tenant, 'q' => 'vacaciones']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('benefits.data.0.id', $vacation->id)
            ->where('benefits.data.0.terms.0.tenure_table.0', ['from_years' => 1, 'value' => '12', 'value_text' => '12 días'])
            ->where('benefits.data.0.terms.0.tenure_table.1.value', '14'));
});

it('loads the page with the same queries whatever the benefits it holds', function (): void {
    $url = route('tenant.compensation.benefits.index', $this->tenant);
    $this->get($url);
    $queries = function () use ($url): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();

        return count(DB::getQueryLog());
    };
    $withGrade = fn (int $count) => Benefit::factory()->count($count)->create(['tenant_id' => $this->tenant->id, 'job_grade_ids' => [JobGrade::factory()->create(['tenant_id' => $this->tenant->id])->id]]);

    foreach ($withGrade(1) as $benefit) {
        ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $benefit->id]);
    }

    $before = $queries();

    foreach ($withGrade(3) as $benefit) {
        ContractTerm::factory()->count(2)->sequence(['effective_from' => '2025-01-01'], ['effective_from' => '2026-01-01'])->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $benefit->id]);
    }

    expect($queries())->toBe($before);
});

it('creates a custom benefit and its first term from the form', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->post(route('tenant.compensation.benefits.store', $this->tenant), benefitForm([
        'group' => 'unionized', 'job_grade_ids' => [$grade->id], 'min_tenure_months' => '12', 'sbc_integration' => 'up_to_cap', 'sbc_cap' => '300', 'cap' => '1,5',
    ]))->assertSessionHasNoErrors();

    $saved = Benefit::query()->where('name', 'Bono de transporte')->sole();

    expect($saved->kind)->toBe(BenefitKind::FixedAmount)
        ->and($saved->sbc_integration)->toBe(SbcIntegration::UpToCap)
        ->and($saved->sbc_cap_cents)->toBe(30000)
        ->and($saved->cap_cents)->toBe(150)
        ->and($saved->in_payroll_tax)->toBeTrue()
        ->and($saved->in_ptu_base)->toBeFalse()
        ->and($saved->job_grade_ids)->toBe([$grade->id])
        ->and($saved->min_tenure_months)->toBe(12)
        ->and($saved->terms()->sole()->value)->toBe(50050);
});

it('refuses what the form gets wrong, field by field', function (array $override, string $field): void {
    $this->post(route('tenant.compensation.benefits.store', $this->tenant), benefitForm($override))->assertSessionHasErrors([$field]);

    expect(Benefit::query()->count())->toBe(0);
})->with([
    'a negative value' => [['value' => '-5'], 'value'],
    'a value that is not a number' => [['value' => 'mucho'], 'value'],
    'no value' => [['value' => ''], 'value'],
    'a negative cap' => [['cap' => '-1'], 'cap'],
    'an unknown kind' => [['kind' => 'magic'], 'kind'],
    'an unknown frequency' => [['frequency' => 'hourly'], 'frequency'],
    'an unknown group' => [['group' => 'directors'], 'group'],
    'an unknown integration' => [['sbc_integration' => 'sometimes'], 'sbc_integration'],
    'up to a cap with no cap' => [['sbc_integration' => 'up_to_cap'], 'sbc_cap'],
    'a bad date' => [['effective_from' => '03/11/2026'], 'effective_from'],
    'no date' => [['effective_from' => ''], 'effective_from'],
    'a blank name' => [['name' => '  '], 'name'],
    'a negative tenure' => [['min_tenure_months' => '-1'], 'min_tenure_months'],
    'a tenure that is not a number' => [['min_tenure_months' => 'x'], 'min_tenure_months'],
    'a job grade that is not an id' => [['job_grade_ids' => ['x']], 'job_grade_ids'],
]);

it('edits a custom benefit without touching its terms and refuses to edit a base one', function (): void {
    $this->get(route('tenant.compensation.benefits.index', $this->tenant));
    $custom = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    $term = ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $custom->id, 'value' => 777]);
    $base = Benefit::query()->where('base_key', 'christmas_bonus')->sole();

    $this->put(route('tenant.compensation.benefits.update', [$this->tenant, $custom->id]), benefitForm(['name' => 'Otro nombre', 'value' => '', 'effective_from' => '']))->assertSessionHasNoErrors();
    $this->put(route('tenant.compensation.benefits.update', [$this->tenant, $base->id]), benefitForm(['name' => 'Aguinaldo extra', 'value' => '', 'effective_from' => '']))->assertSessionHasErrors(['benefit']);

    expect($custom->fresh()?->name)->toBe('Otro nombre')
        ->and($term->fresh()?->value)->toBe(777)
        ->and($base->fresh()?->name)->toBe('Aguinaldo');
});

it('archives and restores a custom benefit, and never a base one', function (): void {
    $this->get(route('tenant.compensation.benefits.index', $this->tenant));
    $custom = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    $base = Benefit::query()->where('base_key', 'vacation')->sole();

    $this->post(route('tenant.compensation.benefits.archive', [$this->tenant, $custom->id]))->assertRedirect();
    expect($custom->fresh()?->archived_at)->not->toBeNull();

    $this->post(route('tenant.compensation.benefits.restore', [$this->tenant, $custom->id]))->assertRedirect();
    expect($custom->fresh()?->archived_at)->toBeNull();

    $this->post(route('tenant.compensation.benefits.archive', [$this->tenant, $base->id]))->assertSessionHasErrors(['benefit']);
    expect($base->fresh()?->archived_at)->toBeNull();
});

it('saves a term with its table, replaces the one of the same date and refuses a table out of order', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    $url = route('tenant.compensation.benefits.terms.store', [$this->tenant, $benefit->id]);

    $this->post($url, ['effective_from' => '2026-01-01', 'value' => '10', 'tenure_table' => [['from_years' => '1', 'value' => '12,5'], ['from_years' => '5', 'value' => '20']]])->assertSessionHasNoErrors();
    $this->post($url, ['effective_from' => '2026-01-01', 'value' => '11'])->assertSessionHasNoErrors();

    $term = $benefit->terms()->sole();
    expect($term->value)->toBe(1100)
        ->and($term->tenure_table)->toBeNull();

    $this->post($url, ['effective_from' => '2027-01-01', 'value' => '10', 'tenure_table' => [['from_years' => '1', 'value' => '12,5'], ['from_years' => '5', 'value' => '20']]]);
    expect($benefit->terms()->where('effective_from', '2027-01-01')->sole()->tenure_table)->toEqual([['from_years' => 1, 'value' => 1250], ['from_years' => 5, 'value' => 2000]]);

    $this->post($url, ['effective_from' => '2028-01-01', 'value' => '10', 'tenure_table' => [['from_years' => '5', 'value' => '1'], ['from_years' => '1', 'value' => '1']]])->assertSessionHasErrors(['tenure_table']);
    $this->post($url, ['effective_from' => '2028-01-01', 'value' => '10', 'tenure_table' => [['from_years' => 'x', 'value' => '1']]])->assertSessionHasErrors(['tenure_table']);
    $this->post($url, ['effective_from' => '2028-01-01', 'value' => '10', 'tenure_table' => [['from_years' => '1', 'value' => 'y']]])->assertSessionHasErrors(['tenure_table']);
    $this->post($url, ['effective_from' => '2028-01-01', 'value' => '-1'])->assertSessionHasErrors(['value']);
    $this->post($url, ['effective_from' => 'ayer', 'value' => '1'])->assertSessionHasErrors(['effective_from']);

    expect($benefit->terms()->count())->toBe(2);
});

it('saves the work risk premium of a vigencia and shows the one in force', function (): void {
    $url = route('tenant.compensation.risk-premium.update', $this->tenant);

    $this->put($url, ['effective_from' => '2026-01-01', 'rate' => '7,58875'])->assertSessionHasNoErrors();
    $this->put($url, ['effective_from' => '2027-01-01', 'rate' => '2'])->assertSessionHasNoErrors();
    $this->put($url, ['effective_from' => '2026-01-01', 'rate' => '16'])->assertSessionHasErrors(['rate']);
    $this->put($url, ['effective_from' => '2026-01-01', 'rate' => '0.1'])->assertSessionHasErrors(['rate']);
    $this->put($url, ['effective_from' => '2026-01-01', 'rate' => 'x'])->assertSessionHasErrors(['rate']);
    $this->put($url, ['effective_from' => 'x', 'rate' => '2'])->assertSessionHasErrors(['effective_from']);

    expect(WorkRiskPremium::query()->count())->toBe(2);

    $this->get(route('tenant.compensation.benefits.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('risk_premium', ['rate' => '7.58875', 'effective_from' => '2026-01-01']));
});

it('searches the job grades of the account on the server', function (): void {
    JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'B']);
    JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Soldador', 'level' => 'A']);
    $foreign = JobGrade::factory()->create(['category' => 'Operador', 'level' => 'Z']);

    $found = $this->getJson(route('tenant.compensation.benefits.grades', [$this->tenant, 'q' => 'oper']))->assertOk()->json();

    expect($found)->toHaveCount(1)
        ->and($found[0]['label'])->toBe('Operador · B')
        ->and($found[0]['value'])->not->toBe($foreign->id);
});

it('lets owner and hr read and write, and keeps viewer and committee out', function (TenantRole $role, bool $allowed): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'other']);
    $this->actingAs(memberOf($this->tenant, $role));
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    $calls = [
        fn () => $this->get(route('tenant.compensation.benefits.index', $this->tenant)),
        fn () => $this->getJson(route('tenant.compensation.benefits.grades', $this->tenant)),
        fn () => $this->post(route('tenant.compensation.benefits.store', $this->tenant), benefitForm(['name' => 'Otra'])),
        fn () => $this->put(route('tenant.compensation.benefits.update', [$this->tenant, $benefit->id]), benefitForm(['name' => 'Otra', 'value' => '', 'effective_from' => ''])),
        fn () => $this->post(route('tenant.compensation.benefits.archive', [$this->tenant, $benefit->id])),
        fn () => $this->post(route('tenant.compensation.benefits.restore', [$this->tenant, $benefit->id])),
        fn () => $this->post(route('tenant.compensation.benefits.terms.store', [$this->tenant, $benefit->id]), ['effective_from' => '2026-01-01', 'value' => '1']),
        fn () => $this->put(route('tenant.compensation.risk-premium.update', $this->tenant), ['effective_from' => '2026-01-01', 'rate' => '2']),
    ];

    foreach ($calls as $call) {
        $response = $call();

        if ($allowed) {
            expect($response->status())->toBeLessThan(400);
        } else {
            $response->assertForbidden();
        }
    }
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, false],
    'committee' => [TenantRole::Committee, false],
    'complaints' => [TenantRole::Complaints, false],
]);

it('opens «Nueva prestación» as a page with what its form chooses from (board V4-Comp-Prestacion, decision A14)', function (): void {
    Scenario::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('tenant.compensation.benefits.create', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/benefit-create')
            ->has('kinds', 4)->has('frequencies', 3)->has('groups', 2)->has('sbc_integrations', 3)
            ->where('scenarios', 2));

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.compensation.benefits.create', $this->tenant))->assertForbidden();
});

it('creates a benefit with its seniority table from the page and goes back to the benefits', function (): void {
    $this->post(route('tenant.compensation.benefits.store', $this->tenant), benefitForm([
        'kind' => 'salary_percent', 'value' => '10', 'tenure_table' => [['from_years' => '5', 'value' => '12'], ['from_years' => '10', 'value' => '15']],
    ]))->assertRedirect(route('tenant.compensation.benefits.index', $this->tenant))->assertSessionHasNoErrors();

    expect(Benefit::query()->where('name', 'Bono de transporte')->sole()->terms()->sole()->tenure_table)
        ->toEqual([['from_years' => 5, 'value' => 1200], ['from_years' => 10, 'value' => 1500]]);

    $this->post(route('tenant.compensation.benefits.store', $this->tenant), benefitForm(['name' => 'Otro', 'tenure_table' => [['from_years' => 'cinco', 'value' => '12']]]))
        ->assertSessionHasErrors('tenure_table');
});
