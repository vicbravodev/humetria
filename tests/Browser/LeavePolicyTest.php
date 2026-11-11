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
 * A policy with a tier of 24 days for the first five years; returns the address of its form.
 */
function leavePolicyBrowserSetup(): string
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id]);
    $policy = LeavePolicy::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Corporativo']);
    LeavePolicyRow::factory()->create(['tenant_id' => $tenant->id, 'leave_policy_id' => $policy->id, 'year_from' => 1, 'year_to' => 5, 'days' => 24]);

    return "{$url}/vacations/policies/{$policy->id}/edit";
}

it('opens a policy with its rows and the minimum of the law beside each', function (bool $dark, bool $mobile): void {
    visitPage('leave/policy', leavePolicyBrowserSetup(), $dark, $mobile)
        ->assertSee('Corporativo')
        ->assertSee('Mínimo legal: 20 días')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('sends a row under the law back with its error under the field', function (): void {
    visitPage('leave/policy', leavePolicyBrowserSetup())
        ->fill('#row-0-days', '19')
        ->press('Guardar política')
        ->assertSee('No puede ser menor a los 20 días que marca la ley.')
        ->assertNoJavaScriptErrors();
});

it('adds a row of days that come every year', function (): void {
    visitPage('leave/policy', leavePolicyBrowserSetup())
        ->press('Agregar días fijos')
        ->assertSee('Nombre de estos días')
        ->assertNoJavaScriptErrors();
});
