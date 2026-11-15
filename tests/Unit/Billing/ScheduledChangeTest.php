<?php

declare(strict_types=1);

use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\ScheduledChange;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

mutates(ScheduledChange::class);

function currentPlan(array $attributes = []): Subscription
{
    return new Subscription(['catalog_version' => 'v1', 'plan' => Plan::Cumplimiento, 'profile' => Profile::Direct, 'status' => SubscriptionStatus::Active, 'seats' => 50, 'centers' => 3, ...$attributes]);
}

function wantedPlan(array $attributes = []): SubscriptionData
{
    return new SubscriptionData(
        $attributes['plan'] ?? Plan::Cumplimiento,
        $attributes['profile'] ?? Profile::Direct,
        $attributes['status'] ?? SubscriptionStatus::Active,
        $attributes['seats'] ?? 50,
        $attributes['centers'] ?? 3,
        CarbonImmutable::parse('2026-01-01'),
    );
}

const CUT = '2026-12-01';

it('applies at once what stays or grows and schedules for the cut what shrinks', function (array $wanted, array $now, array $pending): void {
    $split = ScheduledChange::split(currentPlan(), wantedPlan($wanted), CarbonImmutable::parse(CUT));

    expect([$split->plan, $split->seats, $split->centers])->toBe($now)
        ->and([$split->pendingPlan, $split->pendingSeats, $split->pendingCenters])->toBe($pending)
        ->and($split->hasPending())->toBe($pending !== [null, null, null])
        ->and($split->effectiveOn?->toDateString())->toBe($pending === [null, null, null] ? null : CUT);
})->with([
    'nothing changes' => [[], [Plan::Cumplimiento, 50, 3], [null, null, null]],
    'more people' => [['seats' => 51], [Plan::Cumplimiento, 51, 3], [null, null, null]],
    'one fewer person' => [['seats' => 49], [Plan::Cumplimiento, 50, 3], [null, 49, null]],
    'more centers' => [['centers' => 4], [Plan::Cumplimiento, 50, 4], [null, null, null]],
    'one fewer center' => [['centers' => 2], [Plan::Cumplimiento, 50, 3], [null, null, 2]],
    'a bigger plan' => [['plan' => Plan::Integral], [Plan::Integral, 50, 3], [null, null, null]],
    'a smaller plan' => [['plan' => Plan::Jornada], [Plan::Cumplimiento, 50, 3], [Plan::Jornada, null, null]],
    'more people but fewer centers' => [['seats' => 60, 'centers' => 1], [Plan::Cumplimiento, 60, 3], [null, null, 1]],
    'a bigger plan with fewer people' => [['plan' => Plan::Integral, 'seats' => 10], [Plan::Integral, 50, 3], [null, 10, null]],
]);

it('keeps the profile with the plan: a smaller plan carries its profile to the cut', function (): void {
    $current = currentPlan(['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral]);

    $split = ScheduledChange::split($current, wantedPlan(['plan' => Plan::Cumplimiento, 'profile' => Profile::ChannelCompliance]), CarbonImmutable::parse(CUT));

    expect($split->plan)->toBe(Plan::Integral)->and($split->profile)->toBe(Profile::ChannelIntegral)
        ->and($split->pendingPlan)->toBe(Plan::Cumplimiento)->and($split->pendingProfile)->toBe(Profile::ChannelCompliance);
});

it('changes the profile at once when the plan does not shrink', function (): void {
    $split = ScheduledChange::split(currentPlan(), wantedPlan(['profile' => Profile::ChannelCompliance]), CarbonImmutable::parse(CUT));

    expect($split->profile)->toBe(Profile::ChannelCompliance)->and($split->hasPending())->toBeFalse();
});

it('applies everything at once for a company that has no plan or is still in trial: nobody is billed yet', function (): void {
    $wanted = wantedPlan(['seats' => 1, 'centers' => 1, 'plan' => Plan::Jornada]);

    foreach ([null, currentPlan(['status' => SubscriptionStatus::Trial])] as $current) {
        $split = ScheduledChange::split($current, $wanted, CarbonImmutable::parse(CUT));

        expect([$split->plan, $split->seats, $split->centers])->toBe([Plan::Jornada, 1, 1])->and($split->hasPending())->toBeFalse();
    }
});

it('cancels what was scheduled when the wish goes back to what the company has', function (): void {
    $current = currentPlan(['pending_seats' => 40, 'pending_effective_on' => CUT]);

    $split = ScheduledChange::split($current, wantedPlan(['seats' => 50]), CarbonImmutable::parse(CUT));

    expect($split->hasPending())->toBeFalse();
});

it('does not touch status, price or start: they are not a reduction', function (): void {
    $split = ScheduledChange::split(currentPlan(), wantedPlan(['status' => SubscriptionStatus::CancelScheduled]), CarbonImmutable::parse(CUT));

    expect($split->hasPending())->toBeFalse();
});
