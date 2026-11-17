<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Billing\Support\Quote;

it('has an Enterprise plan with its own rate, minimum and label', function (): void {
    $catalog = Catalog::version('v1');

    expect(Plan::from('enterprise'))->toBe(Plan::Enterprise)
        ->and(Plan::Enterprise->label())->toBe('Enterprise')
        ->and($catalog->rateCents(Plan::Enterprise))->toBe(19_900)
        ->and($catalog->minimumCents(Plan::Enterprise))->toBe(999_000);
});

it('gives Enterprise at least the centers, modules and quotas of Integral', function (): void {
    $catalog = Catalog::version('v1');

    expect($catalog->includedCenters(Plan::Enterprise))->toBeGreaterThanOrEqual($catalog->includedCenters(Plan::Integral))
        ->and($catalog->modules(Plan::Enterprise))->toContain(...$catalog->modules(Plan::Integral));

    foreach (['assistant_tokens', 'email_per_seat', 'email_minimum', 'storage_bytes'] as $resource) {
        expect($catalog->quota(Plan::Enterprise, $resource))->toBeGreaterThanOrEqual($catalog->quota(Plan::Integral, $resource));
    }
});

it('prices Enterprise by the rate above the minimum and by the minimum below it', function (): void {
    expect(Quote::for(Plan::Enterprise, Profile::Direct, 60, 3, 'v1')->subtotalCents)->toBe(60 * 19_900)
        ->and(Quote::for(Plan::Enterprise, Profile::Direct, 10, 3, 'v1')->subtotalCents)->toBe(999_000);
});

it('does not let a channel profile take Enterprise', function (): void {
    Quote::for(Plan::Enterprise, Profile::ChannelIntegral, 60, 3, 'v1');
})->throws(InvalidArgumentException::class);

it('lets the database store the Enterprise plan', function (): void {
    [$tenant] = paidCompany();

    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan' => Plan::Enterprise, 'profile' => Profile::Direct]);

    expect($subscription->fresh()?->plan)->toBe(Plan::Enterprise);
});
