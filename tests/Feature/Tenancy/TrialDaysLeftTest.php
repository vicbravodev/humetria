<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Tenant::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-27 12:00:00', 'UTC'));
});

it('counts the days left in the trial, rounding a started day up', function (int $elapsedSeconds, int $daysLeft): void {
    $tenant = Tenant::factory()->inTrial(now()->subSeconds($elapsedSeconds))->make();

    expect($tenant->trialDaysLeft())->toBe($daysLeft);
})->with([
    'just started' => [0, 14],
    '1 second into the trial' => [1, 14],
    '13 days and 1 second left' => [86400 - 1, 14],
    'exactly 13 days left' => [86400, 13],
    'exactly 1 day left' => [13 * 86400, 1],
    '1 second left' => [14 * 86400 - 1, 1],
]);

it('has no days left once the trial ends', function (): void {
    expect(Tenant::factory()->inTrial(now()->subDays(14))->make()->trialDaysLeft())->toBeNull();
});

it('has no trial days left unless it is in trial', function (): void {
    expect(Tenant::factory()->make()->trialDaysLeft())->toBeNull()
        ->and(Tenant::factory()->inTrial(now()->subDay())->activeUntil(now()->addMonth())->make()->trialDaysLeft())->toBeNull()
        ->and(Tenant::factory()->inTrial(now()->subDay())->deactivated()->make()->trialDaysLeft())->toBeNull();
});

it('shares the days left with the company panel', function (): void {
    $tenant = Tenant::factory()->inTrial(now()->subDays(5))->create();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.status', 'trial')
            ->where('tenant.trial_days_left', 9));
});
