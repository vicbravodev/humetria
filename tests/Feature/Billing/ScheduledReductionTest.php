<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Billing\Actions\ApplyScheduledChanges;
use App\Modules\Billing\Actions\RenewSubscriptions;
use App\Modules\Billing\Actions\SetSubscription;
use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

mutates(ApplyScheduledChanges::class);

function reductionWish(Plan $plan, int $seats, int $centers): SubscriptionData
{
    return new SubscriptionData($plan, Profile::Direct, SubscriptionStatus::Active, $seats, $centers, CarbonImmutable::parse('2026-09-01'));
}

function reductionLimits(Tenant $tenant): array
{
    return array_map(fn ($row): array => [$row->resource, $row->amount], resolve(CompanyLimits::class)->usage($tenant->id));
}

/** A company paid by a direct account, with its plan set through the action, on 26 November. */
function reductionCompany(Plan $plan = Plan::Cumplimiento, int $seats = 50, int $centers = 3): Tenant
{
    [$tenant] = paidCompany();
    App\Modules\Billing\Models\AccountCompany::query()->update(['starts_on' => '2026-09-01']);
    resolve(SetSubscription::class)->handle($tenant->id, reductionWish($plan, $seats, $centers));
    Subscription::query()->where('tenant_id', $tenant->id)->update(['renews_on' => '2026-12-01']);

    return $tenant;
}

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
});

it('does not apply a reduction: the company keeps its plan, people, centers and modules, and the change waits for the cut', function (): void {
    $tenant = reductionCompany();

    resolve(SetSubscription::class)->handle($tenant->id, reductionWish(Plan::Jornada, 30, 1));

    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->sole();
    expect([$subscription->plan, $subscription->seats, $subscription->centers])->toBe([Plan::Cumplimiento, 50, 3])
        ->and([$subscription->pending_plan, $subscription->pending_seats, $subscription->pending_centers])->toBe([Plan::Jornada, 30, 1])
        ->and($subscription->pending_effective_on?->toDateString())->toBe('2026-12-01')
        ->and(reductionLimits($tenant))->toBe([['seats', 50], ['centers', 3]])
        ->and(resolve(CompanyModules::class)->enabled($tenant->id, 'nom035'))->toBeTrue();
});

it('applies an increase at once, even while a reduction waits', function (): void {
    $tenant = reductionCompany();

    resolve(SetSubscription::class)->handle($tenant->id, reductionWish(Plan::Cumplimiento, 80, 1));

    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->sole();
    expect([$subscription->seats, $subscription->centers, $subscription->pending_seats, $subscription->pending_centers])->toBe([80, 3, null, 1])
        ->and(reductionLimits($tenant))->toBe([['seats', 80], ['centers', 3]]);
});

it('says in the audit what waits and for when', function (): void {
    $tenant = reductionCompany();
    resolve(SetSubscription::class)->handle($tenant->id, reductionWish(Plan::Cumplimiento, 40, 3));

    $entry = AuditEntry::query()->where('event', 'billing.subscription_set')->orderByDesc('id')->firstOrFail();

    expect($entry->metadata['pending'])->toEqual(['seats' => 40, 'effective_on' => '2026-12-01']);
});

it('cancels the wait when the same plan is asked for again', function (): void {
    $tenant = reductionCompany();
    resolve(SetSubscription::class)->handle($tenant->id, reductionWish(Plan::Cumplimiento, 40, 3));

    resolve(SetSubscription::class)->handle($tenant->id, reductionWish(Plan::Cumplimiento, 50, 3));

    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->sole();
    expect($subscription->pending_seats)->toBeNull()->and($subscription->pending_effective_on)->toBeNull();
});

describe('at the cut', function (): void {
    it('applies what waited, moves the limits and the modules, clears it and logs and audits it', function (): void {
        Log::spy();
        $tenant = reductionCompany();
        resolve(SetSubscription::class)->handle($tenant->id, reductionWish(Plan::Jornada, 30, 1));
        $this->travelTo('2026-12-01 10:00:00');

        $applied = resolve(ApplyScheduledChanges::class)->handle();

        $subscription = Subscription::query()->where('tenant_id', $tenant->id)->sole();
        expect($applied)->toBe(1)
            ->and([$subscription->plan, $subscription->seats, $subscription->centers])->toBe([Plan::Jornada, 30, 1])
            ->and($subscription->pending_effective_on)->toBeNull()->and($subscription->pending_plan)->toBeNull()
            ->and(reductionLimits($tenant))->toBe([['seats', 30], ['centers', 1]])
            ->and(resolve(CompanyModules::class)->enabled($tenant->id, 'nom035'))->toBeFalse()
            ->and(resolve(CompanyModules::class)->enabled($tenant->id, 'attendance'))->toBeTrue();
        Log::shouldHaveReceived('info')->with('billing.subscription_change_applied', ['tenant_id' => $tenant->id, 'effective_on' => '2026-12-01'])->once();
        expect(AuditEntry::query()->where('event', 'billing.subscription_change_applied')->sole()->metadata)
            ->toEqual(['tenant_id' => $tenant->id, 'plan' => 'jornada', 'seats' => 30, 'centers' => 1, 'effective_on' => '2026-12-01']);
    });

    it('waits for its day: the day before the cut nothing is applied', function (): void {
        $tenant = reductionCompany();
        resolve(SetSubscription::class)->handle($tenant->id, reductionWish(Plan::Cumplimiento, 30, 3));

        expect(resolve(ApplyScheduledChanges::class)->handle())->toBe(0);

        expect(Subscription::query()->where('tenant_id', $tenant->id)->sole()->seats)->toBe(50);
    });

    it('applies it once: running again changes nothing', function (): void {
        $tenant = reductionCompany();
        resolve(SetSubscription::class)->handle($tenant->id, reductionWish(Plan::Cumplimiento, 30, 3));
        $this->travelTo('2026-12-01 10:00:00');
        resolve(ApplyScheduledChanges::class)->handle();

        expect(resolve(ApplyScheduledChanges::class)->handle())->toBe(0)
            ->and(AuditEntry::query()->where('event', 'billing.subscription_change_applied')->count())->toBe(1);
    });

    it('is applied before the month is drafted: the statement of the cut is already the smaller plan, with no credit for the days before', function (): void {
        $tenant = reductionCompany(Plan::Cumplimiento, 100, 2);
        $before = resolve(App\Modules\Billing\Actions\BuildStatement::class)->handle($tenant->id, CarbonImmutable::parse('2026-12-01'))->subtotalCents;
        resolve(SetSubscription::class)->handle($tenant->id, reductionWish(Plan::Cumplimiento, 70, 2));
        $this->travelTo('2026-12-01 10:00:00');

        resolve(RenewSubscriptions::class)->handle();

        $statement = Statement::query()->where('tenant_id', $tenant->id)->sole();
        expect($statement->subtotal_cents)->toBeLessThan($before)->and($statement->snapshot['seats'])->toBe(70);
    });

    it('applies only to the company whose change it is', function (): void {
        $first = reductionCompany();
        $second = reductionCompany();
        resolve(SetSubscription::class)->handle($first->id, reductionWish(Plan::Cumplimiento, 30, 3));
        $this->travelTo('2026-12-01 10:00:00');

        resolve(ApplyScheduledChanges::class)->handle();

        expect(Subscription::query()->where('tenant_id', $first->id)->sole()->seats)->toBe(30)
            ->and(Subscription::query()->where('tenant_id', $second->id)->sole()->seats)->toBe(50);
    });
});

it('applies a company\'s whole plan at once while it is in trial: nobody is billed yet', function (): void {
    [$tenant] = paidCompany();
    resolve(SetSubscription::class)->handle($tenant->id, new SubscriptionData(Plan::Cumplimiento, Profile::Direct, SubscriptionStatus::Trial, 50, 3, CarbonImmutable::parse('2026-09-01')));

    resolve(SetSubscription::class)->handle($tenant->id, new SubscriptionData(Plan::Jornada, Profile::Direct, SubscriptionStatus::Trial, 10, 1, CarbonImmutable::parse('2026-09-01')));

    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->sole();
    expect([$subscription->plan, $subscription->seats])->toBe([Plan::Jornada, 10])->and($subscription->pending_effective_on)->toBeNull();
});
