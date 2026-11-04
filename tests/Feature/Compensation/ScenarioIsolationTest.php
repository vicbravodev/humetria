<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->a = Tenant::factory()->create(['slug' => 'acme']);
    $this->b = Tenant::factory()->create(['slug' => 'beta']);
    $this->actingAs(ownerOf($this->a));
    $this->secret = Scenario::factory()->create(['tenant_id' => $this->b->id, 'name' => 'Propuesta secreta de B', 'group_changes' => ['unionized' => 987, 'trusted' => null]]);
});

it('never lists the scenarios of another account', function (): void {
    Scenario::factory()->create(['tenant_id' => $this->a->id, 'name' => 'Propia']);

    $response = $this->get(route('tenant.compensation.scenarios.index', $this->a))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page->where('scenarios.total', 1)->where('counts', ['active' => 1, 'archived' => 0]));
    expect($response->getContent())->not->toContain('Propuesta secreta de B');
});

it('answers 404 to a scenario of another account by URL, for every action', function (): void {
    $this->get(route('tenant.compensation.scenarios.edit', [$this->a, $this->secret->id]))->assertNotFound();
    $this->put(route('tenant.compensation.scenarios.update', [$this->a, $this->secret->id]), ['name' => 'Robado'])->assertNotFound();
    $this->post(route('tenant.compensation.scenarios.duplicate', [$this->a, $this->secret->id]))->assertNotFound();
    $this->post(route('tenant.compensation.scenarios.archive', [$this->a, $this->secret->id]))->assertNotFound();

    expect($this->secret->fresh()?->name)->toBe('Propuesta secreta de B')
        ->and($this->secret->fresh()?->archived_at)->toBeNull()
        ->and(Scenario::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('answers 404 to another account\'s page even for its own owner', function (): void {
    $this->get(route('tenant.compensation.scenarios.index', $this->b))->assertNotFound();
});

it('does not take a benefit of another account as a change of a scenario', function (): void {
    $foreign = Benefit::factory()->create(['tenant_id' => $this->b->id, 'name' => 'Bono secreto de B']);
    $form = ['name' => 'Mía', 'effective_on' => '2027-01-01', 'retroactive_months' => '0', 'horizon_years' => '3', 'unionized_raise' => '5', 'term_changes' => [['benefit_id' => $foreign->id, 'value' => '100']]];

    $this->post(route('tenant.compensation.scenarios.store', $this->a), $form)->assertSessionHasErrors(['term_changes']);
    $this->getJson(route('tenant.compensation.scenarios.benefits', [$this->a, 'q' => 'secreto']))->assertOk()->assertExactJson([]);

    expect(Scenario::query()->count())->toBe(0);
});

it('allows the same scenario name in two accounts', function (): void {
    $this->post(route('tenant.compensation.scenarios.store', $this->a), ['name' => 'Propuesta secreta de B', 'effective_on' => '2027-01-01', 'retroactive_months' => '0', 'horizon_years' => '3', 'unionized_raise' => '5'])
        ->assertSessionHasNoErrors();

    expect(Scenario::query()->withoutGlobalScopes()->where('name', 'Propuesta secreta de B')->count())->toBe(2);
});
