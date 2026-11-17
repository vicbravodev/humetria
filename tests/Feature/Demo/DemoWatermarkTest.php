<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\DemoCompanyRegistry;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DemoCompanyRegistry::class);

/** A closed evaluation of the company, its report as HR sees it and the token of a link that opens it with no account. */
function reportOf(Tenant $tenant): array
{
    $center = WorkCenter::factory()->for($tenant)->create();
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $token = Str::random(64);
    ReportShare::query()->withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'campaign_id' => $campaign->id,
        'token_hash' => ReportShare::hashToken($token),
        'expires_at' => now()->addHour(),
        'created_by' => memberOf($tenant, TenantRole::Owner)->id,
    ]);

    return [route('tenant.campaigns.report', [$tenant, $campaign->id]), route('report.shared', $token)];
}

it('marks every report of the demo company as DEMO', function (): void {
    $demo = Tenant::factory()->demo()->create();
    [$report, $shared] = reportOf($demo);

    $this->actingAs(memberOf($demo, TenantRole::Hr))->get($report)
        ->assertInertia(fn (Assert $page) => $page->component('results/report')->where('demo', true));

    auth()->logout();
    $this->get($shared)
        ->assertInertia(fn (Assert $page) => $page->component('results/shared-report')->where('demo', true));
});

it('shows no mark in another company', function (): void {
    Tenant::factory()->demo()->create();
    $other = Tenant::factory()->create();
    [$report, $shared] = reportOf($other);

    $this->actingAs(memberOf($other, TenantRole::Hr))->get($report)
        ->assertInertia(fn (Assert $page) => $page->component('results/report')->where('demo', false));

    auth()->logout();
    $this->get($shared)
        ->assertInertia(fn (Assert $page) => $page->component('results/shared-report')->where('demo', false));
});

it('shows no mark outside a company', function (): void {
    Tenant::factory()->demo()->create();

    $this->get(route('report.shared', 'unknown'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->where('demo', false));
});
