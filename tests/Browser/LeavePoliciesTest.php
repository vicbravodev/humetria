<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyRow;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Tenancy\Models\Tenant;

// Light and dark, on a desktop and on a phone.
$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

/**
 * The company has a policy of its own with a tier of 24 days; returns the address of its vacations.
 *
 * @return array{string, LeavePolicy}
 */
function leavePoliciesBrowserSetup(): array
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id]);
    $policy = LeavePolicy::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Corporativo']);
    LeavePolicyRow::factory()->create(['tenant_id' => $tenant->id, 'leave_policy_id' => $policy->id, 'year_from' => 1, 'year_to' => 5, 'days' => 24]);

    return ["{$url}/vacations", $policy];
}

it('shows the law first and the policies of the company side by side', function (bool $dark, bool $mobile): void {
    [$url] = leavePoliciesBrowserSetup();

    visitPage('leave/policies', "{$url}/policies", $dark, $mobile)
        ->assertSee('Políticas')
        ->assertSee('Una política solo puede mejorar la ley')
        ->assertSee('No se puede editar')
        ->assertSee('Corporativo')
        ->assertSee('24 días')
        ->assertSee('+12 días')
        ->assertSee('Días por antigüedad')
        ->assertScript('document.querySelectorAll("[data-slot=column-reference]").length', 12)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('assigns a policy to the whole company from the page', function (): void {
    [$url, $policy] = leavePoliciesBrowserSetup();

    visitPage('leave/policies', "{$url}/policies")
        ->assertSee('A quién le toca cada una')
        ->assertSee('Toda la empresa sigue la Ley (sin política)')
        ->click('button[aria-label="Acciones de Toda la empresa"]')
        ->click('[role=menuitem]:has-text("Cambiar")')
        ->click('[aria-label="Política de toda la empresa"]')
        ->click('[role=option]:has-text("Corporativo")')
        ->assertSee('Toda la empresa sigue Corporativo')
        ->assertNoJavaScriptErrors();

    expect($policy->assignments()->count())->toBe(1);
});

it('shows a company with no policy of its own the law alone, with the way to create one', function (): void {
    $url = signInToPanel();
    workIn(Tenant::query()->where('slug', 'acme')->firstOrFail());
    LeaveSetting::factory()->create(['tenant_id' => Tenant::query()->where('slug', 'acme')->firstOrFail()->id]);

    visitPage('leave/policies', "{$url}/vacations/policies")
        ->assertSee('Tu empresa sigue la ley tal cual')
        ->assertSee('Crear la primera política')
        ->assertNoJavaScriptErrors();
});
