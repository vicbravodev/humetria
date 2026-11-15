<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\ApplyPayment;
use App\Modules\Billing\Exceptions\InvalidPayment;
use App\Modules\Billing\Http\Requests\ApplyPaymentRequest;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\PaymentAllocator;
use App\Modules\Billing\Support\StatementBalances;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(ApplyPayment::class, ApplyPaymentRequest::class, PaymentAllocator::class);

/**
 * A payment of $1,500 with $400 applied to the first of two issued statements ($1,000 and $800).
 *
 * @return array{0: BillingAccount, 1: Payment, 2: Statement, 3: Statement}
 */
function halfAppliedPayment(): array
{
    $account = BillingAccount::factory()->consultant()->create();
    [$first, $second] = array_map(fn (int $total): Statement => Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'subtotal_cents' => $total, 'vat_cents' => 0, 'total_cents' => $total]), [100_000, 80_000]);
    $payment = Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 150_000]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $first->id, 'amount_cents' => 40_000]);

    return [$account, $payment, $first, $second];
}

it('applies what was left of a payment to issued statements and logs it', function (): void {
    Log::spy();
    [$account, $payment, $first, $second] = halfAppliedPayment();
    $third = Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'subtotal_cents' => 30_000, 'vat_cents' => 0, 'total_cents' => 30_000]);

    resolve(ApplyPayment::class)->handle($payment, [$second->id => 80_000, $third->id => 30_000], User::factory()->create());

    expect(resolve(StatementBalances::class)->paid([$first->id, $second->id, $third->id]))->toBe([$first->id => 40_000, $second->id => 80_000, $third->id => 30_000])
        ->and(resolve(StatementBalances::class)->outstanding($account))->toBe(60_000);
    Log::shouldHaveReceived('info')->with('billing.payment_applied', ['billing_account_id' => $account->id, 'payment_id' => $payment->id, 'allocated_cents' => 110_000]);
    expect(AuditEntry::query()->where('event', 'billing.payment_applied')->sole()->metadata)->toEqualCanonicalizing(['billing_account_id' => $account->id, 'amount_cents' => 150_000, 'allocated_cents' => 110_000, 'statements' => 2]);
});

it('refuses to apply more than is left, more than a balance, or to what is not payable', function (Closure $allocate, string $field): void {
    [$account, $payment, $first, $second] = halfAppliedPayment();
    $draft = Statement::factory()->create(['billing_account_id' => $account->id]);
    $foreign = Statement::factory()->issued()->create();
    $reversed = Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 5_000]);
    $reversed->forceFill(['reversed_at' => now(), 'reversed_by' => User::factory()->create()->id, 'reversal_reason' => 'Duplicado'])->save();
    [$target, $allocations] = $allocate($payment, $first, $second, $draft, $foreign, $reversed);

    expect(fn () => resolve(ApplyPayment::class)->handle($target, $allocations, User::factory()->create()))
        ->toThrow(InvalidPayment::class);

    try {
        resolve(ApplyPayment::class)->handle($target, $allocations, User::factory()->create());
    } catch (InvalidPayment $exception) {
        expect($exception->field)->toBe($field);
    }

    expect(PaymentAllocation::query()->count())->toBe(1);
})->with([
    'more than is left of the payment' => [fn ($payment, $first, $second) => [$payment, [$second->id => 80_000, $first->id => 30_001]], 'allocations'],
    'more than the balance of the statement' => [fn ($payment, $first) => [$payment, [$first->id => 60_001]], 'allocations'],
    'nothing' => [fn ($payment) => [$payment, []], 'allocations'],
    'an amount of zero' => [fn ($payment, $first) => [$payment, [$first->id => 0]], 'allocations'],
    'a draft' => [fn ($payment, $first, $second, $draft) => [$payment, [$draft->id => 1_000]], 'allocations'],
    'a statement of another account' => [fn ($payment, $first, $second, $draft, $foreign) => [$payment, [$foreign->id => 1_000]], 'allocations'],
    'a statement it already paid' => [fn ($payment, $first) => [$payment, [$first->id => 1_000]], 'allocations'],
    'a reversed payment' => [fn ($payment, $first, $second, $draft, $foreign, $reversed) => [$reversed, [$second->id => 1_000]], 'payment'],
]);

it('applies the balance from the console and sends the person back to the account', function (): void {
    [$account, $payment, , $second] = halfAppliedPayment();

    $this->actingAs(superadmin())->post(route('billing.payments.apply', $payment), ['allocations' => [$second->id => '600']])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('billing.accounts.show', $account))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Saldo aplicado.']]);

    expect(PaymentAllocation::query()->where('statement_id', $second->id)->sole()->amount_cents)->toBe(60_000);
});

it('shows the errors of the form when it cannot apply', function (Closure $input, string $field): void {
    [, $payment, $first] = halfAppliedPayment();

    $this->actingAs(superadmin())->post(route('billing.payments.apply', $payment), $input($first))->assertSessionHasErrors($field);

    expect(PaymentAllocation::query()->count())->toBe(1);
})->with([
    'nothing to apply' => [fn () => ['allocations' => []], 'allocations'],
    'not money' => [fn (Statement $first) => ['allocations' => [$first->id => 'mucho']], 'allocations.*'],
    'not a statement' => [fn () => ['allocations' => ['no-es-uuid' => '10']], 'allocations'],
    'more than the balance' => [fn (Statement $first) => ['allocations' => [$first->id => '600.01']], 'allocations'],
]);

it('is the superadmin\'s alone', function (): void {
    [, $payment, , $second] = halfAppliedPayment();

    $this->actingAs(support())->post(route('billing.payments.apply', $payment), ['allocations' => [$second->id => '10']])->assertNotFound();
    auth()->logout();
    $this->post(route('billing.payments.apply', $payment), ['allocations' => [$second->id => '10']])->assertRedirect(route('login'));
    $this->actingAs(superadmin())->post(route('billing.payments.apply', '11111111-1111-4111-8111-111111111111'), ['allocations' => [$second->id => '10']])->assertNotFound();

    expect(PaymentAllocation::query()->count())->toBe(1);
});
