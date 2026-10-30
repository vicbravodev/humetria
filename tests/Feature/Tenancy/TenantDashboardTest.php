<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\TenantDashboardController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(TenantDashboardController::class);

it('reminds a company without active work centers to add one', function (string $case): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->create(); // Another company's never counts.

    if ($case === 'only archived') {
        WorkCenter::factory()->for($tenant)->archived()->create();
    }

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')->where('hasWorkCenters', false));
})->with(['none', 'only archived']);

it('stops reminding once the company has an active work center', function (): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->for($tenant)->create();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('hasWorkCenters', true));
});
