<?php

declare(strict_types=1);

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Demos\Support\DemoMetrics;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DemoMetrics::class);

beforeEach(fn () => $this->travelTo('2026-12-01 12:00 UTC'));

it('gives the console its figures once the page shows', function (): void {
    DemoRequest::factory()->create(['created_at' => now()->subDays(3)]);

    $this->actingAs(superadmin())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('metrics')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('metrics.new', 1)
                ->where('metrics.unattended', 1)));
});

it('takes the median time to the first answer of the last 90 days, in hours', function (): void {
    foreach ([2, 9, 30] as $hours) {
        DemoRequest::factory()->create(['status' => DemoStatus::Contacted, 'created_at' => now()->subDays(10), 'handled_at' => now()->subDays(10)->addHours($hours)]);
    }
    // Outside the 90 days, and one nobody answered yet: neither counts.
    DemoRequest::factory()->create(['status' => DemoStatus::Contacted, 'created_at' => now()->subDays(91), 'handled_at' => now()->subDays(80)]);
    DemoRequest::factory()->create(['created_at' => now()->subDays(10)]);

    expect(app(DemoMetrics::class)->handle()['first_response_hours'])->toBe(9.0);
});

it('says the share of requests of the last 90 days that became a company', function (): void {
    $tenant = Tenant::factory()->create();
    DemoRequest::factory()->create(['status' => DemoStatus::Converted, 'tenant_id' => $tenant->id, 'created_at' => now()->subDays(5)]);
    DemoRequest::factory()->count(2)->create(['created_at' => now()->subDays(5)]);
    DemoRequest::factory()->create(['status' => DemoStatus::Converted, 'created_at' => now()->subDays(120)]);

    expect(app(DemoMetrics::class)->handle()['conversion'])->toBe(33);
});

it('names the source most requests of the last 90 days came from, and its share of all of them', function (): void {
    DemoRequest::factory()->count(3)->fromCampaign('google', 'cpc')->create(['created_at' => now()->subDays(5)]);
    DemoRequest::factory()->referredBy('www.linkedin.com')->create(['created_at' => now()->subDays(5)]);
    DemoRequest::factory()->create(['created_at' => now()->subDays(5)]);
    DemoRequest::factory()->count(5)->referredBy('www.bing.com')->create(['created_at' => now()->subDays(100)]);

    expect(app(DemoMetrics::class)->handle()['source'])->toBe(['label' => 'google', 'share' => 60]);
});

it('counts a request from exactly within the 90 days, and not one a minute older', function (): void {
    DemoRequest::factory()->create(['status' => DemoStatus::Converted, 'tenant_id' => Tenant::factory()->create()->id, 'created_at' => now()->subDays(90)->addMinute()]);
    DemoRequest::factory()->create(['created_at' => now()->subDays(90)->subMinute()]);

    expect(app(DemoMetrics::class)->handle()['conversion'])->toBe(100);
});

it('says nothing it does not know when nobody asked in 90 days', function (): void {
    DemoRequest::factory()->create(['created_at' => now()->subDays(100)]);

    expect(app(DemoMetrics::class)->handle())->toBe([
        'new' => 1, 'unattended' => 1, 'first_response_hours' => null, 'conversion' => null, 'source' => null,
    ]);
});

it('reads the figures in three queries', function (): void {
    DemoRequest::factory()->count(5)->fromCampaign()->create(['created_at' => now()->subDays(5)]);

    $this->expectsDatabaseQueryCount(3);
    app(DemoMetrics::class)->handle();
});
