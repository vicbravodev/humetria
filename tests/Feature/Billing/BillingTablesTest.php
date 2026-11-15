<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Enums\ChargeKind;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCharge;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('builds each model from its factory, with the casts of its columns', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    $link = AccountCompany::factory()->for($account, 'account')->create();
    $subscription = Subscription::factory()->create(['tenant_id' => $link->tenant_id]);
    $charge = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $link->tenant_id]);

    expect($account->refresh()->kind)->toBe(AccountKind::Consultant)
        ->and($link->refresh()->ends_on)->toBeNull()
        ->and($link->starts_on->toDateString())->toBe($link->starts_on->format('Y-m-d'))
        ->and($subscription->refresh()->plan)->toBeInstanceOf(Plan::class)
        ->and($subscription->profile)->toBeInstanceOf(Profile::class)
        ->and($charge->refresh()->amount_cents)->toBeInt();
});

it('keeps one current payer per company: a second open link is refused, a closed one is history', function (): void {
    $tenant = Tenant::factory()->create();
    AccountCompany::factory()->create(['tenant_id' => $tenant->id]);

    // A savepoint, so the refused insert does not abort the test's own transaction.
    expect(fn () => DB::transaction(fn () => AccountCompany::factory()->create(['tenant_id' => $tenant->id])))->toThrow(QueryException::class);

    AccountCompany::query()->where('tenant_id', $tenant->id)->update(['ends_on' => '2026-12-31']);
    AccountCompany::factory()->create(['tenant_id' => $tenant->id]);
    AccountCompany::factory()->create(['tenant_id' => $tenant->id, 'ends_on' => '2027-01-31', 'starts_on' => '2027-01-01']);

    expect(AccountCompany::query()->where('tenant_id', $tenant->id)->count())->toBe(3);
});

it('refuses a link that ends before it starts', function (): void {
    expect(fn () => AccountCompany::factory()->create(['starts_on' => '2026-12-10', 'ends_on' => '2026-12-09']))->toThrow(QueryException::class);
});

it('keeps one subscription per company', function (): void {
    $tenant = Tenant::factory()->create();
    Subscription::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => Subscription::factory()->create(['tenant_id' => $tenant->id]))->toThrow(QueryException::class);
});

/**
 * A subscription written straight to the table, past the casts of the model.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertSubscription(array $overrides): void
{
    $row = [...Subscription::factory()->raw(), 'id' => (string) Str::uuid(), ...$overrides];
    $row = array_map(fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value, $row);

    // A savepoint, so a refused row does not abort the test's own transaction.
    DB::transaction(fn () => DB::table('subscriptions')->insert($row));
}

it('refuses what the console would refuse, whatever path writes it', function (string $column, mixed $value): void {
    expect(fn () => insertSubscription([$column => $value]))->toThrow(QueryException::class);
})->with([
    'a plan that does not exist' => ['plan', 'platino'],
    'a profile that does not exist' => ['profile', 'regalado'],
    'a status that does not exist' => ['status', 'perdida'],
    'negative seats' => ['seats', -1],
    'no centers' => ['centers', 0],
    'a negative negotiated price' => ['negotiated_price_cents', -1],
]);

it('requires a price for the negotiated profile', function (): void {
    expect(fn () => insertSubscription(['profile' => 'negotiated', 'negotiated_price_cents' => null]))->toThrow(QueryException::class);
    Subscription::factory()->create(['profile' => 'negotiated', 'negotiated_price_cents' => 800_000]);

    expect(Subscription::query()->count())->toBe(1);
});

it('keeps the RFC of an account unique, in the format of the SAT, and its kind to the two that exist', function (): void {
    BillingAccount::factory()->create(['rfc' => 'ABC010101AB1']);

    expect(fn () => BillingAccount::factory()->create(['rfc' => 'ABC010101AB1']))->toThrow(QueryException::class)
        ->and(fn () => BillingAccount::factory()->create(['rfc' => 'no-es-rfc']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('billing_accounts')->insert([...array_map(fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value, BillingAccount::factory()->raw()), 'id' => (string) Str::uuid(), 'kind' => 'regalo']))->toThrow(QueryException::class);
});

it('refuses a charge of nothing or less, and a recurring one that ends before it starts', function (int $amount, ChargeKind $kind, string $chargeOn, ?string $endsOn): void {
    $subscription = Subscription::factory()->create();

    expect(fn () => SubscriptionCharge::factory()->for($subscription)->create([
        'tenant_id' => $subscription->tenant_id,
        'amount_cents' => $amount,
        'kind' => $kind,
        'charge_on' => $chargeOn,
        'ends_on' => $endsOn,
    ]))->toThrow(QueryException::class);
})->with([
    'zero' => [0, ChargeKind::OneTime, '2026-12-01', null],
    'negative' => [-100, ChargeKind::OneTime, '2026-12-01', null],
    'ends before it starts' => [100, ChargeKind::Recurring, '2026-12-10', '2026-12-09'],
    'a one-time charge with an end date' => [100, ChargeKind::OneTime, '2026-12-01', '2027-01-01'],
]);

it('removes the charges and the subscription of a company that is deleted, and nothing else', function (): void {
    $subscription = Subscription::factory()->create();
    SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $subscription->tenant_id]);
    $link = AccountCompany::factory()->create(['tenant_id' => $subscription->tenant_id]);

    Tenant::query()->whereKey($subscription->tenant_id)->delete();

    expect(Subscription::query()->count())->toBe(0)
        ->and(SubscriptionCharge::query()->count())->toBe(0)
        ->and(AccountCompany::query()->count())->toBe(0)
        ->and(BillingAccount::query()->whereKey($link->billing_account_id)->exists())->toBeTrue();
});
