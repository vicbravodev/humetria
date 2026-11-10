<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\StatementLine;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCharge;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Runs a write the database should refuse, in a savepoint so the test's own transaction survives. */
function billingDbRefuses(Closure $write, string $message): void
{
    expect(fn () => DB::transaction($write))->toThrow(QueryException::class, $message);
}

/** A draft with one line that adds up to its total, ready to issue. */
function billingDraftWithLine(): Statement
{
    $statement = Statement::factory()->create(['subtotal_cents' => 100_000, 'vat_cents' => 16_000, 'total_cents' => 116_000]);
    StatementLine::factory()->for($statement)->create(['amount_cents' => 100_000, 'position' => 1]);
    StatementLine::factory()->for($statement)->create(['amount_cents' => 16_000, 'position' => 2, 'code' => 'vat', 'label' => 'IVA']);

    return $statement;
}

function billingIssueDirectly(Statement $statement): void
{
    $statement->update(['status' => StatementStatus::Issued, 'issued_at' => now(), 'issued_by' => User::factory()->create()->id]);
}

it('builds each model from its factory, with the casts of its columns', function (): void {
    $statement = Statement::factory()->issued()->create();
    $line = StatementLine::factory()->for(Statement::factory())->create();
    $payment = Payment::factory()->create();

    expect($statement->refresh()->status)->toBe(StatementStatus::Issued)
        ->and($statement->period_start->toDateString())->toBe('2026-12-01')
        ->and($statement->snapshot)->toBe([])
        ->and($line->refresh()->amount_cents)->toBeInt()
        ->and($payment->refresh()->isReversed())->toBeFalse()
        ->and(Payment::query()->standing()->count())->toBe(1);
});

it('keeps one statement per company and month, but lets an adjustment share the month of the one it corrects', function (): void {
    $first = Statement::factory()->create();
    $same = ['billing_account_id' => $first->billing_account_id, 'tenant_id' => $first->tenant_id];

    billingDbRefuses(fn () => Statement::factory()->create($same), 'statements_one_per_company_period');

    Statement::factory()->create([...$same, 'period_start' => '2027-01-01', 'period_end' => '2027-02-01']);
    Statement::factory()->create([...$same, 'adjusts_statement_id' => $first->id, 'reason' => 'Error de captura', 'total_cents' => -100, 'subtotal_cents' => -100, 'vat_cents' => 0]);

    expect(Statement::query()->count())->toBe(3);
});

it('refuses a statement the console would refuse, whatever path writes it', function (array $overrides, string $constraint): void {
    billingDbRefuses(fn () => Statement::factory()->create($overrides), $constraint);
})->with([
    'a period that ends before it starts' => [['period_end' => '2026-11-30'], 'statements_period_ordered'],
    'a total that is not the subtotal plus the VAT' => [['total_cents' => 1], 'statements_total_adds_up'],
    'an issued one with no one who issued it' => [['status' => 'issued', 'issued_at' => '2026-12-01 10:00:00'], 'statements_issued_has_who'],
    'a negative statement that corrects nothing' => [['subtotal_cents' => -5, 'vat_cents' => 0, 'total_cents' => -5], 'statements_regular_not_negative'],
]);

it('refuses a status that does not exist', function (): void {
    $statement = Statement::factory()->create();

    billingDbRefuses(fn () => DB::table('statements')->where('id', $statement->id)->update(['status' => 'paid']), 'statements_status_known');
});

it('refuses an adjustment with no reason', function (): void {
    $original = Statement::factory()->create();

    billingDbRefuses(fn () => Statement::factory()->create(['adjusts_statement_id' => $original->id, 'period_start' => '2027-01-01', 'period_end' => '2027-02-01']), 'statements_adjustment_has_reason');
});

it('lets a draft change and be deleted, with its lines', function (): void {
    $statement = billingDraftWithLine();

    $statement->update(['subtotal_cents' => 100_000]);
    StatementLine::query()->where('statement_id', $statement->id)->first()?->update(['label' => 'Otro']);
    $statement->delete();

    expect(Statement::query()->count())->toBe(0)->and(StatementLine::query()->count())->toBe(0);
});

it('issues a statement whose lines add up to its total, and from then on refuses any change to it or to its lines', function (): void {
    $statement = billingDraftWithLine();
    billingIssueDirectly($statement);

    billingDbRefuses(fn () => $statement->update(['total_cents' => 1, 'subtotal_cents' => 1, 'vat_cents' => 0]), 'an issued statement is immutable');
    billingDbRefuses(fn () => $statement->update(['reason' => 'x']), 'an issued statement is immutable');
    billingDbRefuses(fn () => $statement->delete(), 'an issued statement is immutable');
    billingDbRefuses(fn () => DB::table('statements')->where('id', $statement->id)->delete(), 'an issued statement is immutable');

    $line = StatementLine::query()->where('statement_id', $statement->id)->firstOrFail();
    billingDbRefuses(fn () => $line->update(['amount_cents' => 1]), 'the lines of an issued statement are immutable');
    billingDbRefuses(fn () => $line->delete(), 'the lines of an issued statement are immutable');
    billingDbRefuses(fn () => StatementLine::factory()->for($statement)->create(['position' => 9]), 'the lines of an issued statement are immutable');

    expect(Statement::query()->findOrFail($statement->id)->total_cents)->toBe(116_000)->and(StatementLine::query()->count())->toBe(2);
});

it('refuses to issue a statement whose lines do not add up to its total', function (): void {
    $statement = Statement::factory()->create(['subtotal_cents' => 100_000, 'vat_cents' => 16_000, 'total_cents' => 116_000]);
    StatementLine::factory()->for($statement)->create(['amount_cents' => 100_000]);

    billingDbRefuses(fn () => billingIssueDirectly($statement), 'the lines of a statement must add up to its total');

    expect($statement->refresh()->status)->toBe(StatementStatus::Draft);
});

it('bills a one-time charge on one line only', function (): void {
    $subscription = Subscription::factory()->create();
    $charge = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $subscription->tenant_id]);
    StatementLine::factory()->create(['code' => 'charge_one_time', 'charge_id' => $charge->id]);

    billingDbRefuses(fn () => StatementLine::factory()->create(['code' => 'charge_one_time', 'charge_id' => $charge->id]), 'statement_lines_one_time_charge_once');
});

it('refuses a billed date on a recurring charge', function (): void {
    $subscription = Subscription::factory()->create();
    $charge = SubscriptionCharge::factory()->recurring()->for($subscription)->create(['tenant_id' => $subscription->tenant_id]);

    billingDbRefuses(fn () => $charge->forceFill(['billed_at' => now()])->save(), 'subscription_charges_only_one_time_billed');
});

it('refuses a payment of nothing, with no reference, or half reversed', function (array $overrides, string $constraint): void {
    billingDbRefuses(fn () => Payment::factory()->create($overrides), $constraint);
})->with([
    'zero' => [['amount_cents' => 0], 'payments_amount_positive'],
    'no reference' => [['reference' => ' '], 'payments_reference_filled'],
    'a reversal with no reason' => [['reversed_at' => '2026-12-06 10:00:00', 'reversed_by' => 1], 'payments_reversal_complete'],
]);

it('detects the same reference twice on one account, but not on another, nor once the first was reversed', function (): void {
    $payment = Payment::factory()->create(['reference' => 'SPEI-1']);

    billingDbRefuses(fn () => Payment::factory()->create(['billing_account_id' => $payment->billing_account_id, 'reference' => 'SPEI-1']), 'payments_reference_unique');

    Payment::factory()->create(['reference' => 'SPEI-1']);
    $payment->forceFill(['reversed_at' => now(), 'reversed_by' => User::factory()->create()->id, 'reversal_reason' => 'Duplicado'])->save();
    Payment::factory()->create(['billing_account_id' => $payment->billing_account_id, 'reference' => 'SPEI-1']);

    expect(Payment::query()->count())->toBe(3);
});

it('never deletes or edits a payment, except to reverse it once', function (): void {
    $payment = Payment::factory()->create();

    billingDbRefuses(fn () => $payment->delete(), 'a payment is never deleted');
    billingDbRefuses(fn () => Payment::query()->findOrFail($payment->id)->update(['amount_cents' => 1]), 'a payment can only be reversed, once');
    billingDbRefuses(fn () => Payment::query()->findOrFail($payment->id)->update(['reference' => 'otra']), 'a payment can only be reversed, once');

    $payment->forceFill(['reversed_at' => now(), 'reversed_by' => User::factory()->create()->id, 'reversal_reason' => 'Equivocado'])->save();

    billingDbRefuses(fn () => Payment::query()->findOrFail($payment->id)->forceFill(['reversal_reason' => 'Otra razón'])->save(), 'a payment can only be reversed, once');
    expect($payment->refresh()->isReversed())->toBeTrue()->and(Payment::query()->standing()->count())->toBe(0);
});

/** A payment and an issued statement of one account. */
function billingPaymentAndStatement(int $payment = 100_000, int $total = 116_000): array
{
    $account = BillingAccount::factory()->create();

    return [
        Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => $payment]),
        Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'subtotal_cents' => $total, 'vat_cents' => 0, 'total_cents' => $total]),
        $account,
    ];
}

it('applies a payment to a statement, in whole or in part, and one payment over several statements', function (): void {
    [$payment, $first, $account] = billingPaymentAndStatement(150_000, 100_000);
    $second = Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'tenant_id' => Tenant::factory()->create()->id, 'subtotal_cents' => 100_000, 'vat_cents' => 0, 'total_cents' => 100_000]);

    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $first->id, 'amount_cents' => 100_000]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $second->id, 'amount_cents' => 50_000]);

    expect((int) PaymentAllocation::query()->sum('amount_cents'))->toBe(150_000);
});

it('refuses to apply more than the payment holds, or more than the statement is worth', function (): void {
    [$payment, $statement, $account] = billingPaymentAndStatement(100_000, 116_000);
    $other = Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'tenant_id' => Tenant::factory()->create()->id, 'subtotal_cents' => 90_000, 'vat_cents' => 0, 'total_cents' => 90_000]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $statement->id, 'amount_cents' => 60_000]);

    billingDbRefuses(fn () => PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $other->id, 'amount_cents' => 40_001]), 'the payment is over-allocated');

    $second = Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 100_000]);
    billingDbRefuses(fn () => PaymentAllocation::factory()->create(['payment_id' => $second->id, 'statement_id' => $statement->id, 'amount_cents' => 56_001]), 'the statement is over-paid');

    PaymentAllocation::factory()->create(['payment_id' => $second->id, 'statement_id' => $statement->id, 'amount_cents' => 56_000]);
    expect(PaymentAllocation::query()->count())->toBe(2);
});

it('counts the payments that were reversed as nothing when it checks a statement is not over-paid', function (): void {
    [$payment, $statement, $account] = billingPaymentAndStatement(116_000, 116_000);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $statement->id, 'amount_cents' => 116_000]);
    $payment->forceFill(['reversed_at' => now(), 'reversed_by' => User::factory()->create()->id, 'reversal_reason' => 'Equivocado'])->save();
    $again = Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 116_000]);

    PaymentAllocation::factory()->create(['payment_id' => $again->id, 'statement_id' => $statement->id, 'amount_cents' => 116_000]);

    expect(PaymentAllocation::query()->count())->toBe(2);
});

it('refuses to apply a reversed payment, a payment to a draft, or a payment to another account\'s statement', function (): void {
    [$payment, $statement] = billingPaymentAndStatement();
    $draft = Statement::factory()->create(['billing_account_id' => $payment->billing_account_id, 'tenant_id' => Tenant::factory()->create()->id]);
    $foreign = Statement::factory()->issued()->create();

    billingDbRefuses(fn () => PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $draft->id]), 'a payment is applied to an issued statement');
    billingDbRefuses(fn () => PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $foreign->id]), 'a payment is applied to a statement of its own account');

    $payment->forceFill(['reversed_at' => now(), 'reversed_by' => User::factory()->create()->id, 'reversal_reason' => 'Equivocado'])->save();
    billingDbRefuses(fn () => PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $statement->id]), 'a reversed payment cannot be applied');
});

it('never changes or deletes an allocation, and refuses an amount of nothing', function (): void {
    [$payment, $statement] = billingPaymentAndStatement();
    $allocation = PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $statement->id, 'amount_cents' => 50_000]);

    billingDbRefuses(fn () => $allocation->update(['amount_cents' => 60_000]), 'an allocation is never changed');
    billingDbRefuses(fn () => $allocation->delete(), 'an allocation is never changed');
    billingDbRefuses(fn () => PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => Statement::factory()->issued()->create(['billing_account_id' => $payment->billing_account_id, 'tenant_id' => Tenant::factory()->create()->id])->id, 'amount_cents' => 0]), 'payment_allocations_amount_positive');
});
