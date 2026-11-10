<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantLimit;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the staff what the company has used of the people and centers its plan covers', function (): void {
    $tenant = Tenant::factory()->create();
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => 'seats', 'amount' => 50, 'used' => 42]);
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => 'centers', 'amount' => 2, 'used' => 3]);
    TenantLimit::factory()->create(['resource' => 'seats', 'amount' => 5, 'used' => 5]);

    foreach ([superadmin(), support()] as $user) {
        $this->actingAs($user)->get(route('companies.show', $tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/company')
            ->has('limits', 2)
            ->where('limits.0', ['resource' => 'seats', 'label' => 'Personas', 'used' => 42, 'amount' => 50, 'remaining' => 8, 'over' => false])
            ->where('limits.1', ['resource' => 'centers', 'label' => 'Centros de trabajo', 'used' => 3, 'amount' => 2, 'remaining' => 0, 'over' => true]));
    }
});

it('shows no limits for a company without a plan', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())->get(route('companies.show', $tenant))->assertOk()->assertInertia(fn (Assert $page) => $page->has('limits', 0));
});
