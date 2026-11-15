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

it('shows the staff the tokens of Humi the company used, holds and may use this month, and the months before', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    config(['billing.unplanned_quotas.assistant_tokens' => 20_000]);
    $tenant = Tenant::factory()->create();
    App\Modules\Billing\Models\UsageCounter::factory()->create(['tenant_id' => $tenant->id, 'period_start' => '2026-10-01', 'used' => 7000, 'quota' => 15_000]);
    App\Modules\Billing\Models\UsageCounter::factory()->create(['tenant_id' => $tenant->id, 'period_start' => '2026-12-01', 'used' => 1200, 'reserved' => 9000]);
    App\Modules\Billing\Models\UsageGrant::factory()->create(['tenant_id' => $tenant->id, 'period_start' => '2026-12-01', 'quantity' => 5000]);
    App\Modules\Billing\Models\UsageCounter::factory()->create(['period_start' => '2026-12-01', 'used' => 999]);

    foreach ([superadmin(), support()] as $user) {
        $this->actingAs($user)->get(route('companies.show', $tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('usage.period_label', 'Diciembre 2026')
            ->where('usage.used', 1200)
            ->where('usage.reserved', 9000)
            ->where('usage.quota', 25_000)
            ->where('usage.granted', 5000)
            ->where('usage.remaining', 14_800)
            ->has('usage_history.data', 2)
            ->where('usage_history.data.0', ['period' => '2026-12-01', 'label' => 'Diciembre 2026', 'used' => 1200, 'quota' => null])
            ->where('usage_history.data.1', ['period' => '2026-10-01', 'label' => 'Octubre 2026', 'used' => 7000, 'quota' => 15_000]));
    }
});

it('pages the months of use', function (): void {
    $tenant = Tenant::factory()->create();

    foreach (range(1, 8) as $month) {
        App\Modules\Billing\Models\UsageCounter::factory()->create(['tenant_id' => $tenant->id, 'period_start' => "2025-0{$month}-01"]);
    }

    $this->actingAs(superadmin())->get(route('companies.show', $tenant))->assertInertia(fn (Assert $page) => $page->has('usage_history.data', 6)->where('usage_history.total', 8));
    $this->actingAs(superadmin())->get(route('companies.show', [$tenant, 'usage_page' => 2]))->assertInertia(fn (Assert $page) => $page->has('usage_history.data', 2));
});

it('shows the staff the campaign email and the files of the company this month against what the plan covers', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    $tenant = Tenant::factory()->create();
    App\Modules\Billing\Models\UsageCounter::factory()->create(['tenant_id' => $tenant->id, 'meter' => 'email_campaign', 'period_start' => '2026-12-01', 'used' => 120]);
    App\Modules\Billing\Models\UsageCounter::factory()->create(['tenant_id' => $tenant->id, 'meter' => 'storage_bytes', 'period_start' => '2026-12-01', 'used' => 1_073_741_824]);

    foreach ([superadmin(), support()] as $user) {
        $this->actingAs($user)->get(route('companies.show', $tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('other_usage', 2)
            ->where('other_usage.0', ['meter' => 'email_campaign', 'label' => 'Correo de campañas', 'unit' => 'correos', 'used' => 120, 'quota' => 500, 'remaining' => 380])
            ->where('other_usage.1', ['meter' => 'storage_bytes', 'label' => 'Archivos', 'unit' => 'bytes', 'used' => 1_073_741_824, 'quota' => 5_368_709_120, 'remaining' => 4_294_967_296]));
    }
});
