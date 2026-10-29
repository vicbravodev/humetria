<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('the owner of a company can visit its dashboard', function () {
    $tenant = Tenant::factory()->create();
    $this->actingAs(ownerOf($tenant));

    $response = $this->get(route('tenant.dashboard', $tenant));
    $response->assertOk();
});
