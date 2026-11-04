<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->a = Tenant::factory()->create(['slug' => 'acme']);
    $this->b = Tenant::factory()->create(['slug' => 'beta']);
    $this->actingAs(ownerOf($this->a));
    $this->secret = Benefit::factory()->create(['tenant_id' => $this->b->id, 'name' => 'Bono secreto de B']);
    ContractTerm::factory()->create(['tenant_id' => $this->b->id, 'benefit_id' => $this->secret->id, 'value' => 987654]);
    WorkRiskPremium::factory()->create(['tenant_id' => $this->b->id, 'effective_from' => '2020-01-01']);
});

it('never lists the benefits, the terms or the premium of another account', function (): void {
    $response = $this->get(route('tenant.compensation.benefits.index', $this->a))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page->where('benefits.total', 11)->where('counts.active', 11)->where('risk_premium', null));

    expect($response->getContent())->not->toContain('Bono secreto de B')->and($response->getContent())->not->toContain('9876.54');
});

it('answers 404 to a benefit of another account by URL, for every action', function (): void {
    $form = ['name' => 'Robado', 'kind' => 'fixed_amount', 'frequency' => 'monthly', 'sbc_integration' => 'always', 'min_tenure_months' => '0'];

    $this->put(route('tenant.compensation.benefits.update', [$this->a, $this->secret->id]), $form)->assertNotFound();
    $this->post(route('tenant.compensation.benefits.archive', [$this->a, $this->secret->id]))->assertNotFound();
    $this->post(route('tenant.compensation.benefits.restore', [$this->a, $this->secret->id]))->assertNotFound();
    $this->post(route('tenant.compensation.benefits.terms.store', [$this->a, $this->secret->id]), ['effective_from' => '2026-01-01', 'value' => '1'])->assertNotFound();

    expect($this->secret->fresh()?->name)->toBe('Bono secreto de B')
        ->and($this->secret->fresh()?->archived_at)->toBeNull()
        ->and(ContractTerm::query()->withoutGlobalScopes()->where('benefit_id', $this->secret->id)->count())->toBe(1);
});

it('answers 404 to another account\'s page even for its own owner', function (): void {
    $this->get(route('tenant.compensation.benefits.index', $this->b))->assertNotFound();
});

it('allows the same benefit name in two accounts and keeps their catalogs apart', function (): void {
    $this->post(route('tenant.compensation.benefits.store', $this->a), ['name' => 'Bono secreto de B', 'kind' => 'salary_percent', 'frequency' => 'yearly', 'sbc_integration' => 'never', 'min_tenure_months' => '0', 'value' => '3', 'effective_from' => '2026-01-01'])
        ->assertSessionHasNoErrors();

    expect(Benefit::query()->withoutGlobalScopes()->where('name', 'Bono secreto de B')->count())->toBe(2)
        ->and(Benefit::query()->withoutGlobalScopes()->where('tenant_id', $this->b->id)->count())->toBe(1);
});

it('refuses the job grades of another account', function (): void {
    $foreign = JobGrade::factory()->create(['tenant_id' => $this->b->id, 'category' => 'Soldador', 'level' => 'A']);

    $this->post(route('tenant.compensation.benefits.store', $this->a), ['name' => 'Bono', 'kind' => 'salary_percent', 'frequency' => 'yearly', 'sbc_integration' => 'never', 'min_tenure_months' => '0', 'value' => '3', 'effective_from' => '2026-01-01', 'job_grade_ids' => [$foreign->id]])
        ->assertSessionHasErrors(['job_grade_ids']);
    $this->getJson(route('tenant.compensation.benefits.grades', [$this->a, 'q' => 'soldador']))->assertOk()->assertExactJson([]);
});

it('keeps the premium of each account apart', function (): void {
    $this->put(route('tenant.compensation.risk-premium.update', $this->a), ['effective_from' => '2020-01-01', 'rate' => '3'])->assertSessionHasNoErrors();

    expect(WorkRiskPremium::query()->withoutGlobalScopes()->where('effective_from', '2020-01-01')->count())->toBe(2);
});
