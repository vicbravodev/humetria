<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\RecordPayment;
use App\Modules\Billing\Actions\ReversePayment;
use App\Modules\Billing\Data\PaymentData;
use App\Modules\Billing\Exceptions\InvalidPayment;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\StatementBalances;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

mutates(RecordPayment::class, ReversePayment::class, StatementBalances::class);

/**
 * An account with issued statements of those totals, one company each.
 *
 * @param  list<int>  $totals
 * @return array{0: BillingAccount, 1: list<Statement>}
 */
function paymentAccount(array $totals): array
{
    $account = BillingAccount::factory()->consultant()->create();
    $statements = array_map(fn (int $total): Statement => Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'subtotal_cents' => $total, 'vat_cents' => 0, 'total_cents' => $total]), $totals);

    return [$account, $statements];
}

/** @param  array<string, int>  $allocations */
function paymentData(int $amount, array $allocations = [], string $reference = 'SPEI-001'): PaymentData
{
    return new PaymentData($amount, CarbonImmutable::parse('2026-12-05'), $reference, 'Banorte', $allocations);
}

it('records a payment and applies it to two statements: one is paid and the other keeps a balance', function (): void {
    Log::spy();
    [$account, [$first, $second]] = paymentAccount([398_000, 398_000]);
    $by = User::factory()->create();

    $payment = resolve(RecordPayment::class)->handle($account, paymentData(798_000, [$first->id => 398_000, $second->id => 300_000]), $by);

    $balances = resolve(StatementBalances::class);
    expect($payment->amount_cents)->toBe(798_000)
        ->and($payment->reference)->toBe('SPEI-001')
        ->and($payment->note)->toBe('Banorte')
        ->and($payment->paid_on->toDateString())->toBe('2026-12-05')
        ->and($payment->recorded_by)->toBe($by->id)
        ->and($balances->paid([$first->id, $second->id]))->toBe([$first->id => 398_000, $second->id => 300_000])
        ->and($balances->outstanding($account))->toBe(98_000);
    Log::shouldHaveReceived('info')->with('billing.payment_recorded', ['billing_account_id' => $account->id, 'amount_cents' => 798_000, 'allocated_cents' => 698_000]);
    $entry = AuditEntry::query()->where('event', 'billing.payment_recorded')->sole();
    expect($entry->subject_id)->toBe($payment->id)
        ->and($entry->metadata)->toEqualCanonicalizing(['billing_account_id' => $account->id, 'amount_cents' => 798_000, 'allocated_cents' => 698_000, 'statements' => 2]);
});

it('keeps the part of a payment nobody applied yet, and a partial payment leaves the balance', function (): void {
    [$account, [$statement]] = paymentAccount([100_000]);

    $payment = resolve(RecordPayment::class)->handle($account, paymentData(150_000, [$statement->id => 40_000]), User::factory()->create());

    expect(resolve(StatementBalances::class)->paid([$statement->id]))->toBe([$statement->id => 40_000])
        ->and(PaymentAllocation::query()->where('payment_id', $payment->id)->sum('amount_cents'))->toEqual(40_000)
        ->and(resolve(StatementBalances::class)->outstanding($account))->toBe(60_000);
});

it('records a payment with nothing applied', function (): void {
    [$account] = paymentAccount([100_000]);

    resolve(RecordPayment::class)->handle($account, paymentData(100_000), User::factory()->create());

    expect(Payment::query()->count())->toBe(1)->and(PaymentAllocation::query()->count())->toBe(0);
});

it('refuses what would be wrong, writing nothing', function (Closure $make, string $field, string $message): void {
    [$account, $statements] = paymentAccount([100_000, 50_000]);
    [$other] = paymentAccount([100_000]);
    Statement::factory()->create(['billing_account_id' => $account->id]);
    resolve(RecordPayment::class)->handle($account, paymentData(10_000, [$statements[0]->id => 10_000], 'SPEI-USED'), User::factory()->create());
    $draft = Statement::query()->where('billing_account_id', $account->id)->where('status', 'draft')->sole();
    $data = $make($statements, $other, $draft);

    try {
        resolve(RecordPayment::class)->handle($account, $data, User::factory()->create());
        $this->fail('Should have been refused.');
    } catch (InvalidPayment $exception) {
        expect($exception->field)->toBe($field)->and($exception->getMessage())->toBe($message);
    }

    expect(Payment::query()->count())->toBe(1)->and(PaymentAllocation::query()->count())->toBe(1);
})->with([
    'a reference already recorded' => [fn ($s) => paymentData(1_000, [], 'SPEI-USED'), 'reference', 'Ya hay un pago con esa referencia en esta cuenta.'],
    'a reference with only spaces' => [fn ($s) => paymentData(1_000, [], '  '), 'reference', 'Pon la referencia del pago.'],
    'an amount of nothing' => [fn ($s) => paymentData(0), 'amount', 'El pago debe ser de más de cero.'],
    'more applied than paid' => [fn ($s) => paymentData(1_000, [$s[1]->id => 1_001]), 'allocations', 'Lo aplicado es mayor que el pago.'],
    'more than the balance of the statement' => [fn ($s) => paymentData(500_000, [$s[0]->id => 90_001]), 'allocations', 'No se puede aplicar más que el saldo de un estado de cuenta.'],
    'an amount of nothing on a statement' => [fn ($s) => paymentData(5_000, [$s[1]->id => 0]), 'allocations', 'Cada importe aplicado debe ser de más de cero.'],
    'a statement of another account' => [fn ($s, $o) => paymentData(5_000, [$o->id => 5_000]), 'allocations', 'Elige estados de cuenta emitidos de esta cuenta.'],
    'a draft' => [fn ($s, $o, $d) => paymentData(5_000, [$d->id => 5_000]), 'allocations', 'Elige estados de cuenta emitidos de esta cuenta.'],
    'a statement that does not exist' => [fn () => paymentData(5_000, ['11111111-1111-4111-8111-111111111111' => 5_000]), 'allocations', 'Elige estados de cuenta emitidos de esta cuenta.'],
]);

it('lets the exact balance be applied, and no more', function (): void {
    [$account, [$statement]] = paymentAccount([100_000]);
    resolve(RecordPayment::class)->handle($account, paymentData(60_000, [$statement->id => 60_000], 'A'), User::factory()->create());

    expect(fn () => resolve(RecordPayment::class)->handle($account, paymentData(40_001, [$statement->id => 40_001], 'B'), User::factory()->create()))->toThrow(InvalidPayment::class);

    resolve(RecordPayment::class)->handle($account, paymentData(40_000, [$statement->id => 40_000], 'B'), User::factory()->create());

    expect(resolve(StatementBalances::class)->outstanding($account))->toBe(0);
});

it('reverses a wrong payment with a reason, and what it paid is owed again', function (): void {
    Log::spy();
    [$account, [$statement]] = paymentAccount([100_000]);
    $payment = resolve(RecordPayment::class)->handle($account, paymentData(100_000, [$statement->id => 100_000]), User::factory()->create());
    $by = User::factory()->create();
    $this->freezeTime();

    $reversed = resolve(ReversePayment::class)->handle($payment, 'Se capturó en la cuenta equivocada', $by);

    expect($reversed->isReversed())->toBeTrue()
        ->and($reversed->reversal_reason)->toBe('Se capturó en la cuenta equivocada')
        ->and($reversed->reversed_by)->toBe($by->id)
        ->and($reversed->reversed_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and(Payment::query()->count())->toBe(1)
        ->and(PaymentAllocation::query()->count())->toBe(1)
        ->and(resolve(StatementBalances::class)->paid([$statement->id]))->toBe([])
        ->and(resolve(StatementBalances::class)->outstanding($account))->toBe(100_000);
    Log::shouldHaveReceived('info')->with('billing.payment_reversed', ['billing_account_id' => $account->id, 'amount_cents' => 100_000]);
    expect(AuditEntry::query()->where('event', 'billing.payment_reversed')->sole()->subject_id)->toBe($payment->id);
});

it('lets the reference of a reversed payment be recorded again, and the statement be paid again', function (): void {
    [$account, [$statement]] = paymentAccount([100_000]);
    $payment = resolve(RecordPayment::class)->handle($account, paymentData(100_000, [$statement->id => 100_000]), User::factory()->create());
    resolve(ReversePayment::class)->handle($payment, 'Error', User::factory()->create());

    resolve(RecordPayment::class)->handle($account, paymentData(100_000, [$statement->id => 100_000]), User::factory()->create());

    expect(resolve(StatementBalances::class)->outstanding($account))->toBe(0);
});

it('refuses to reverse a payment twice or without a reason', function (): void {
    [$account] = paymentAccount([100_000]);
    $payment = resolve(RecordPayment::class)->handle($account, paymentData(100_000), User::factory()->create());

    expect(fn () => resolve(ReversePayment::class)->handle($payment, '  ', User::factory()->create()))->toThrow(InvalidPayment::class, 'Di por qué se revierte el pago.');
    resolve(ReversePayment::class)->handle($payment, 'Error', User::factory()->create());

    expect(fn () => resolve(ReversePayment::class)->handle($payment->refresh(), 'Otra vez', User::factory()->create()))->toThrow(InvalidPayment::class, 'Ese pago ya se revirtió.')
        ->and(AuditEntry::query()->where('event', 'billing.payment_reversed')->count())->toBe(1);
});

it('counts only issued statements when it adds up what an account owes, and none of another account', function (): void {
    [$account, [$statement]] = paymentAccount([100_000]);
    Statement::factory()->create(['billing_account_id' => $account->id]);
    paymentAccount([999_000]);

    expect(resolve(StatementBalances::class)->outstanding($account))->toBe(100_000)
        ->and(resolve(StatementBalances::class)->paid([]))->toBe([])
        ->and($statement->exists)->toBeTrue();
});

it('does not change any company: recording and reversing a payment touch no access, date or restriction', function (): void {
    [$account, [$statement]] = paymentAccount([100_000]);
    $tenant = App\Modules\Tenancy\Models\Tenant::query()->findOrFail($statement->tenant_id);
    $before = $tenant->getAttributes();

    $payment = resolve(RecordPayment::class)->handle($account, paymentData(100_000, [$statement->id => 100_000]), User::factory()->create());
    resolve(ReversePayment::class)->handle($payment, 'Error', User::factory()->create());

    expect($tenant->refresh()->getAttributes())->toBe($before);
});
