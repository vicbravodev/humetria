<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Actions\ListAccountPayments;
use App\Modules\Billing\Actions\ListOpenStatements;
use App\Modules\Billing\Http\Requests\RecordPaymentRequest;
use App\Modules\Billing\Http\Requests\ReversePaymentRequest;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ListAccountPayments::class, ListOpenStatements::class, RecordPaymentRequest::class, ReversePaymentRequest::class);

/**
 * An account with two issued statements of $3,980 each.
 *
 * @return array{0: BillingAccount, 1: Statement, 2: Statement}
 */
function receivableAccount(): array
{
    $account = BillingAccount::factory()->consultant()->create();
    [$one, $two] = array_map(function (string $name) use ($account): Statement {
        $november = $name === 'Cliente Uno';
        $tenant = Tenant::factory()->create(['trade_name' => $name]);
        App\Modules\Billing\Models\AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $tenant->id]);

        return Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'tenant_id' => $tenant->id, 'period_start' => $november ? '2026-11-01' : '2026-12-01', 'period_end' => $november ? '2026-12-01' : '2027-01-01', 'subtotal_cents' => 398_000, 'vat_cents' => 0, 'total_cents' => 398_000]);
    }, ['Cliente Uno', 'Cliente Dos']);

    return [$account, $one, $two];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paymentInput(array $overrides = []): array
{
    return ['amount' => '7980', 'paid_on' => '2026-12-05', 'reference' => 'SPEI-001', 'note' => 'Banorte', 'allocations' => [], ...$overrides];
}

it('records a transfer applied to two statements: one paid, the other with a balance', function (): void {
    [$account, $one, $two] = receivableAccount();
    $admin = superadmin();

    $this->actingAs($admin)->post(route('billing.payments.store', $account), paymentInput(['amount' => '6980', 'allocations' => [$one->id => '3980', $two->id => '3000']]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('billing.accounts.show', $account))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Pago registrado.']]);

    $payment = Payment::query()->sole();
    expect($payment->amount_cents)->toBe(698_000)
        ->and($payment->paid_on->toDateString())->toBe('2026-12-05')
        ->and($payment->reference)->toBe('SPEI-001')
        ->and($payment->recorded_by)->toBe($admin->id)
        ->and(PaymentAllocation::query()->where('statement_id', $one->id)->sole()->amount_cents)->toBe(398_000)
        ->and(PaymentAllocation::query()->where('statement_id', $two->id)->sole()->amount_cents)->toBe(300_000);

    $this->get(route('billing.accounts.show', $account))->assertInertia(fn (Assert $page) => $page
        ->where('receivable.outstanding', '$980')
        ->where('receivable.open_statements', 1));
});

it('skips the statements left blank or at zero in the dialog', function (): void {
    [$account, $one, $two] = receivableAccount();

    $this->actingAs(superadmin())->post(route('billing.payments.store', $account), paymentInput(['amount' => '1000', 'allocations' => [$one->id => '1000', $two->id => '']]))->assertSessionHasNoErrors();

    expect(PaymentAllocation::query()->count())->toBe(1);
});

it('refuses what the rules refuse, with a message on its field, and records nothing', function (array $overrides, string $field): void {
    [$account, $one] = receivableAccount();
    $input = paymentInput($overrides);
    $input['allocations'] = array_combine(array_map(fn (string $key): string => $key === '@one' ? $one->id : $key, array_keys($input['allocations'])), array_values($input['allocations']));

    $this->actingAs(superadmin())->post(route('billing.payments.store', $account), $input)->assertSessionHasErrors($field);

    expect(Payment::query()->count())->toBe(0);
})->with([
    'no amount' => [['amount' => ''], 'amount'],
    'an amount of zero' => [['amount' => '0'], 'amount'],
    'an amount with a comma' => [['amount' => '7,980'], 'amount'],
    'no date' => [['paid_on' => ''], 'paid_on'],
    'a date that is not one' => [['paid_on' => 'ayer'], 'paid_on'],
    'no reference' => [['reference' => ''], 'reference'],
    'a reference that is too long' => [['reference' => str_repeat('a', 121)], 'reference'],
    'a note that is too long' => [['note' => str_repeat('a', 1001)], 'note'],
    'more applied than paid' => [['amount' => '10', 'allocations' => ['@one' => '10.01']], 'allocations'],
    'more than the balance' => [['amount' => '5000', 'allocations' => ['@one' => '3980.01']], 'allocations'],
    'an amount applied that is not money' => [['allocations' => ['@one' => 'mucho']], 'allocations.*'],
    'a statement that is not one' => [['allocations' => ['no-es-uuid' => '10']], 'allocations'],
]);

it('refuses the same reference twice on one account', function (): void {
    [$account] = receivableAccount();
    $this->actingAs(superadmin())->post(route('billing.payments.store', $account), paymentInput())->assertSessionHasNoErrors();

    $this->post(route('billing.payments.store', $account), paymentInput())->assertSessionHasErrors(['reference' => 'Ya hay un pago con esa referencia en esta cuenta.']);

    expect(Payment::query()->count())->toBe(1);
});

it('reverses a payment with a reason', function (): void {
    [$account, $one] = receivableAccount();
    $payment = Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 398_000]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $one->id, 'amount_cents' => 398_000]);

    $this->actingAs(superadmin())->post(route('billing.payments.reverse', $payment), ['reason' => 'Cuenta equivocada'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('billing.accounts.show', $account))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Pago revertido.']]);

    expect($payment->refresh()->reversal_reason)->toBe('Cuenta equivocada');

    $this->post(route('billing.payments.reverse', $payment), ['reason' => 'Otra vez'])->assertSessionHasErrors(['payment' => 'Ese pago ya se revirtió.']);
    $this->post(route('billing.payments.reverse', Payment::factory()->create(['billing_account_id' => $account->id])), ['reason' => ''])->assertSessionHasErrors('reason');
});

it('is the superadmin\'s alone: support and owners get a 404 and guests the login', function (): void {
    [$account, $one] = receivableAccount();
    $payment = Payment::factory()->create(['billing_account_id' => $account->id]);

    foreach ([support(), ownerOf(Tenant::query()->findOrFail($one->tenant_id))] as $user) {
        $this->actingAs($user)->post(route('billing.payments.store', $account), paymentInput())->assertNotFound();
        $this->post(route('billing.payments.reverse', $payment), ['reason' => 'x'])->assertNotFound();
        $this->getJson(route('billing.accounts.open-statements', $account))->assertNotFound();
    }

    auth()->logout();
    $this->post(route('billing.payments.store', $account), paymentInput())->assertRedirect(route('login'));

    expect(Payment::query()->count())->toBe(1)->and($payment->refresh()->isReversed())->toBeFalse();
});

it('answers 404 for an account or a payment that does not exist', function (): void {
    $this->actingAs(superadmin())->post(route('billing.payments.store', '11111111-1111-4111-8111-111111111111'), paymentInput())->assertNotFound();
    $this->post(route('billing.payments.reverse', '11111111-1111-4111-8111-111111111111'), ['reason' => 'x'])->assertNotFound();
});

it('offers the statements with a balance, oldest first, for the dialog to apply a payment to', function (): void {
    [$account, $one, $two] = receivableAccount();
    $paid = Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 100_000]);
    PaymentAllocation::factory()->create(['payment_id' => $paid->id, 'statement_id' => $two->id, 'amount_cents' => 100_000]);
    Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'subtotal_cents' => 5_000, 'vat_cents' => 0, 'total_cents' => 5_000])
        ->allocations()->create(['payment_id' => Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 5_000])->id, 'amount_cents' => 5_000]);
    Statement::factory()->create(['billing_account_id' => $account->id]);
    receivableAccount();

    $this->actingAs(superadmin())->getJson(route('billing.accounts.open-statements', $account))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $one->id)
        ->assertJsonPath('data.0.label', 'Cliente Uno · nov 2026')
        ->assertJsonPath('data.0.balance', '$3,980')
        ->assertJsonPath('data.0.balance_input', '3980')
        ->assertJsonPath('data.1.id', $two->id)
        ->assertJsonPath('data.1.balance', '$2,980');
});

it('lists the payments of the account a page at a time, with what was applied and what was left, and the reversed ones marked', function (): void {
    [$account, $one] = receivableAccount();
    $user = User::factory()->create(['name' => 'Victor Bravo']);
    $payment = Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 500_000, 'reference' => 'SPEI-9', 'paid_on' => '2026-12-09', 'note' => 'Banorte', 'recorded_by' => $user->id]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $one->id, 'amount_cents' => 398_000]);
    $reversed = Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 1_000, 'paid_on' => '2026-12-01']);
    $reversed->forceFill(['reversed_at' => now(), 'reversed_by' => $user->id, 'reversal_reason' => 'Duplicado'])->save();
    Payment::factory()->create();

    $this->actingAs(support())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 2)
            ->where('payments.total', 2)
            ->hasAll(['payments.current_page', 'payments.last_page', 'payments.per_page', 'payments.links'])
            ->where('payments.data.0.id', $payment->id)
            ->where('payments.data.0.reference', 'SPEI-9')
            ->where('payments.data.0.paid_on', '9 dic 2026')
            ->where('payments.data.0.amount', '$5,000')
            ->where('payments.data.0.applied', '$3,980')
            ->where('payments.data.0.unapplied', '$1,020')
            ->where('payments.data.0.note', 'Banorte')
            ->where('payments.data.0.recorded_by', 'Victor Bravo')
            ->where('payments.data.0.reversed', false)
            ->where('payments.data.1.reversed', true)
            ->where('payments.data.1.reversal_reason', 'Duplicado'));
});

it('pages the payments 25 at a time', function (): void {
    [$account] = receivableAccount();
    Payment::factory()->count(26)->create(['billing_account_id' => $account->id]);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 25)->where('payments.total', 26));
});
