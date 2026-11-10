<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Billing\Actions\SetSubscription;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Http\Requests\SubscriptionRules;
use App\Modules\Billing\Http\Requests\UpdateSubscriptionRequest;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(SetSubscription::class, SubscriptionRules::class, UpdateSubscriptionRequest::class);

it('lives under /admin/billing/companies/{company}/subscription', function (): void {
    expect(route('billing.subscriptions.update', 'abc', absolute: false))->toBe('/admin/billing/companies/abc/subscription');
});

it('sets the plan of a company, quoted with the current catalog, renewing on the 1st of the next month, and logs and audits it', function (): void {
    Log::spy();
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())
        ->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput())
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Plan asignado.']]);

    $subscription = Subscription::query()->sole();

    expect($subscription->tenant_id)->toBe($tenant->id)
        ->and($subscription->plan)->toBe(Plan::Cumplimiento)
        ->and($subscription->profile)->toBe(Profile::Direct)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->catalog_version)->toBe('v1')
        ->and($subscription->seats)->toBe(100)
        ->and($subscription->centers)->toBe(2)
        ->and($subscription->starts_on->toDateString())->toBe('2026-12-01')
        ->and($subscription->renews_on->toDateString())->toBe('2027-01-01')
        ->and($subscription->negotiated_price_cents)->toBeNull()
        ->and($subscription->negotiated_valid_until)->toBeNull();

    Log::shouldHaveReceived('info')->with('billing.subscription_set', ['tenant_id' => $tenant->id, 'created' => true]);
    $entry = AuditEntry::query()->where('event', 'billing.subscription_set')->sole();
    expect($entry->subject_id)->toBe($subscription->id)
        ->and($entry->metadata)->toEqualCanonicalizing([
            'tenant_id' => $tenant->id, 'created' => true, 'plan' => 'cumplimiento', 'profile' => 'direct', 'status' => 'active',
            'seats' => 100, 'centers' => 2, 'catalog_version' => 'v1', 'negotiated_price_cents' => null,
        ]);
});

it('redirects back to the account that pays the company', function (): void {
    [$tenant, $account] = paidCompany();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput())
        ->assertRedirect(route('billing.accounts.show', $account));
});

it('renews on the 1st after a start in the middle of a month, and a start on the 1st renews the next 1st', function (string $start, string $renews): void {
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['starts_on' => $start]))->assertSessionHasNoErrors();

    expect(Subscription::query()->sole()->renews_on->toDateString())->toBe($renews);
})->with([
    'the 1st' => ['2026-12-01', '2027-01-01'],
    'the 15th' => ['2026-12-15', '2027-01-01'],
    'the 31st' => ['2026-12-31', '2027-01-01'],
    'the 31st of a month before one of 30 days' => ['2026-10-31', '2026-11-01'],
    'February' => ['2027-02-10', '2027-03-01'],
]);

it('changes the plan of a company that has one, keeps its catalog and its row, and audits it as an update', function (): void {
    Log::spy();
    [$tenant] = paidCompany();
    $existing = Subscription::factory()->create(['tenant_id' => $tenant->id, 'catalog_version' => 'v0', 'seats' => 50, 'starts_on' => '2026-12-01', 'renews_on' => '2027-01-01']);

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['seats' => 51, 'status' => 'trial']))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Plan actualizado.']]);

    $existing->refresh();

    expect(Subscription::query()->count())->toBe(1)
        ->and($existing->seats)->toBe(51)
        ->and($existing->status)->toBe(SubscriptionStatus::Trial)
        ->and($existing->catalog_version)->toBe('v0')
        ->and($existing->renews_on->toDateString())->toBe('2027-01-01');

    Log::shouldHaveReceived('info')->with('billing.subscription_set', ['tenant_id' => $tenant->id, 'created' => false]);
    expect(AuditEntry::query()->where('event', 'billing.subscription_set')->sole()->metadata['created'])->toBeFalse();
});

it('recomputes the renewal when the start day moves', function (): void {
    [$tenant] = paidCompany();
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-12-01', 'renews_on' => '2027-01-01']);

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['starts_on' => '2027-03-10']))->assertSessionHasNoErrors();

    expect(Subscription::query()->sole()->renews_on->toDateString())->toBe('2027-04-01');
});

it('keeps a negotiated price in cents, with the day it is valid until', function (): void {
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['profile' => 'negotiated', 'seats' => 180, 'negotiated_price' => '8149.50', 'negotiated_valid_until' => '2027-06-30']))
        ->assertSessionHasNoErrors();

    $subscription = Subscription::query()->sole();

    expect($subscription->profile)->toBe(Profile::Negotiated)
        ->and($subscription->negotiated_price_cents)->toBe(814_950)
        ->and($subscription->negotiated_valid_until?->toDateString())->toBe('2027-06-30')
        ->and(AuditEntry::query()->where('event', 'billing.subscription_set')->sole()->metadata['negotiated_price_cents'])->toBe(814_950);
});

it('lets a direct plan carry a negotiated price too', function (): void {
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['negotiated_price' => '4000']))->assertSessionHasNoErrors();

    expect(Subscription::query()->sole())->profile->toBe(Profile::Direct)->negotiated_price_cents->toBe(400_000);
});

it('changes nothing about the access of the company, its dates, its restriction or its other data', function (): void {
    [$tenant] = paidCompany();
    $tenant->update(['active_until' => '2026-12-31 00:00:00+00']);
    // Its modules do follow the plan now (through Tenancy's contract); the flag that tells pages to read them is only bookkeeping.
    $before = Arr::except($tenant->refresh()->getAttributes(), ['has_module_rows']);

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput())->assertSessionHasNoErrors();

    expect(Arr::except($tenant->refresh()->getAttributes(), ['has_module_rows']))->toBe($before)
        ->and($tenant->active_until?->toDateString())->toBe('2026-12-31')
        ->and($tenant->restriction)->toBeNull();
});

it('lets a consultant account have channel plans, and refuses them on a direct one', function (string $kind, string $profile, string $plan, bool $accepted): void {
    [$tenant] = paidCompany($kind);

    $response = $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['profile' => $profile, 'plan' => $plan, 'seats' => 40]));

    if ($accepted) {
        $response->assertSessionHasNoErrors();
        expect(Subscription::query()->count())->toBe(1);
    } else {
        $response->assertSessionHasErrors('profile');
        expect(Subscription::query()->count())->toBe(0)->and(AuditEntry::query()->where('event', 'billing.subscription_set')->count())->toBe(0);
    }
})->with([
    'consultant channel compliance' => ['consultant', 'channel_compliance', 'cumplimiento', true],
    'consultant channel integral' => ['consultant', 'channel_integral', 'integral', true],
    'consultant direct' => ['consultant', 'direct', 'jornada', true],
    'direct channel compliance' => ['direct', 'channel_compliance', 'cumplimiento', false],
    'direct channel integral' => ['direct', 'channel_integral', 'integral', false],
]);

it('refuses a channel profile with a plan it does not go with', function (string $profile, string $plan): void {
    [$tenant] = paidCompany('consultant');

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['profile' => $profile, 'plan' => $plan]))
        ->assertSessionHasErrors('profile');

    expect(Subscription::query()->count())->toBe(0);
})->with([
    'channel compliance with Integral' => ['channel_compliance', 'integral'],
    'channel integral with Jornada' => ['channel_integral', 'jornada'],
]);

it('refuses a company that nobody pays, telling to link it first', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput())
        ->assertSessionHasErrors(['plan' => 'Vincula la empresa a una cuenta antes de asignarle un plan.']);

    expect(Subscription::query()->count())->toBe(0);
});

it('answers 404 for a company that does not exist', function (): void {
    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', '11111111-1111-4111-8111-111111111111'), subscriptionInput())->assertNotFound();
    $this->actingAs(superadmin())->put('/admin/billing/companies/no-es-uuid/subscription', subscriptionInput())->assertNotFound();
});

it('does not activate with zero people, but lets an active plan be cut to zero to pay its minimum', function (): void {
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['seats' => 0]))->assertSessionHasErrors('seats');
    expect(Subscription::query()->count())->toBe(0);

    $this->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['seats' => 0, 'status' => 'trial']))->assertSessionHasNoErrors();
    expect(Subscription::query()->sole()->status)->toBe(SubscriptionStatus::Trial);

    $this->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['seats' => 5]))->assertSessionHasNoErrors();
    $this->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['seats' => 0]))->assertSessionHasNoErrors();
    expect(Subscription::query()->sole())->seats->toBe(0)->status->toBe(SubscriptionStatus::Active);
});

it('refuses what the form must not let through', function (array $overrides, string $field): void {
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput($overrides))->assertSessionHasErrors($field);

    expect(Subscription::query()->count())->toBe(0);
})->with([
    'no plan' => [['plan' => ''], 'plan'],
    'an unknown plan' => [['plan' => 'platino'], 'plan'],
    'an unknown profile' => [['profile' => 'regalo'], 'profile'],
    'an unknown status' => [['status' => 'perdida'], 'status'],
    'negative seats' => [['seats' => -1], 'seats'],
    'seats that are not a whole number' => [['seats' => '10.5'], 'seats'],
    'too many seats' => [['seats' => 100001], 'seats'],
    'no centers' => [['centers' => 0], 'centers'],
    'too many centers' => [['centers' => 1001], 'centers'],
    'no start day' => [['starts_on' => ''], 'starts_on'],
    'a start that is not a date' => [['starts_on' => 'pronto'], 'starts_on'],
    'a negotiated profile with no price' => [['profile' => 'negotiated'], 'negotiated_price'],
    'a price with a comma' => [['negotiated_price' => '8,000'], 'negotiated_price'],
    'a price of three decimals' => [['negotiated_price' => '8000.123'], 'negotiated_price'],
    'a validity with no price' => [['negotiated_valid_until' => '2027-06-30'], 'negotiated_valid_until'],
    'a validity before the start' => [['negotiated_price' => '4000', 'negotiated_valid_until' => '2026-11-30'], 'negotiated_valid_until'],
]);

it('is the superadmin\'s alone: support and everyone else get a 404 and nothing is set', function (): void {
    [$tenant] = paidCompany();

    $this->actingAs(support())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput())->assertNotFound();
    $this->actingAs(ownerOf($tenant))->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput())->assertNotFound();

    expect(Subscription::query()->count())->toBe(0);
});

it('keeps the route that sets a plan out of reach while impersonating', function (): void {
    expect(App\Http\Middleware\ForbidWhileImpersonating::guards('billing.subscriptions.update'))->toBeTrue();
});
