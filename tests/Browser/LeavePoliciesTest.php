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

it('shows the law first and the policies of the company', function (bool $dark, bool $mobile): void {
    [$url] = leavePoliciesBrowserSetup();

    visitPage('leave/policies', "{$url}/policies", $dark, $mobile)
        ->assertSee('Políticas')
        ->assertSee('12 días el primer año · prima 25 % · no se puede editar')
        ->assertSee('Corporativo')
        ->assertSee('24 días el primer año')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);
