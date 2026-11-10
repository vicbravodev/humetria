<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\AdjustStatement;
use App\Modules\Billing\Actions\GenerateStatement;
use App\Modules\Billing\Actions\IssueStatement;
use App\Modules\Billing\Data\AdjustmentData;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\StatementLine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(IssueStatement::class, AdjustStatement::class);

/** A draft of December for a direct Cumplimiento company of 100 people and 2 centers: $4,900 + $784 of VAT. */
function statementDraft(): Statement
{
    [$tenant] = statementCompany();

    return resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create());
}

it('issues a draft: who and when, with a log and an audit entry', function (): void {
    Log::spy();
    $this->freezeTime();
    $statement = statementDraft();
    $issuer = User::factory()->create();

    $issued = resolve(IssueStatement::class)->handle($statement, $issuer);

    expect($issued->status)->toBe(StatementStatus::Issued)
        ->and($issued->issued_by)->toBe($issuer->id)
        ->and($issued->issued_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($statement->refresh()->status)->toBe(StatementStatus::Issued);
    Log::shouldHaveReceived('info')->with('billing.statement_issued', ['tenant_id' => $statement->tenant_id, 'period' => '2026-12', 'total_cents' => 568_400]);
    $entry = AuditEntry::query()->where('event', 'billing.statement_issued')->sole();
    expect($entry->subject_id)->toBe($statement->id)
        ->and($entry->metadata)->toEqualCanonicalizing(['tenant_id' => $statement->tenant_id, 'billing_account_id' => $statement->billing_account_id, 'period' => '2026-12', 'total_cents' => 568_400]);
});

it('does not issue what is already issued, and from then on the database refuses any change', function (): void {
    $statement = statementDraft();
    resolve(IssueStatement::class)->handle($statement, User::factory()->create());

    expect(fn () => resolve(IssueStatement::class)->handle($statement->refresh(), User::factory()->create()))
        ->toThrow(InvalidStatement::class, 'Este estado de cuenta ya está emitido.')
        ->and(AuditEntry::query()->where('event', 'billing.statement_issued')->count())->toBe(1)
        ->and(fn () => DB::transaction(fn () => Statement::query()->findOrFail($statement->id)->update(['subtotal_cents' => 1, 'total_cents' => 1 + 78_400])))->toThrow(QueryException::class, 'an issued statement is immutable')
        ->and(fn () => DB::transaction(fn () => StatementLine::query()->where('statement_id', $statement->id)->delete()))->toThrow(QueryException::class, 'the lines of an issued statement are immutable');
});

/** @return array{0: Statement, 1: Statement} the issued statement and an adjustment to it */
function statementAdjusted(int $cents, string $reason = 'Se cobró un centro de más'): array
{
    $original = resolve(IssueStatement::class)->handle(statementDraft(), User::factory()->create());

    return [$original, resolve(AdjustStatement::class)->handle($original, new AdjustmentData('Centro adicional', $cents, $reason), User::factory()->create())];
}

it('corrects an issued statement with another that references it, never by editing it', function (): void {
    Log::spy();
    $original = resolve(IssueStatement::class)->handle(statementDraft(), User::factory()->create());
    $author = User::factory()->create();
    $before = $original->refresh()->getAttributes();

    $adjustment = resolve(AdjustStatement::class)->handle($original, new AdjustmentData('Centro adicional', -14_900, 'Se cobró un centro de más'), $author);

    expect($adjustment->adjusts_statement_id)->toBe($original->id)
        ->and($adjustment->status)->toBe(StatementStatus::Issued)
        ->and($adjustment->issued_by)->toBe($author->id)
        ->and([$adjustment->tenant_id, $adjustment->billing_account_id])->toBe([$original->tenant_id, $original->billing_account_id])
        ->and([$adjustment->period_start->toDateString(), $adjustment->period_end->toDateString()])->toBe(['2026-12-01', '2027-01-01'])
        ->and([$adjustment->subtotal_cents, $adjustment->vat_cents, $adjustment->total_cents])->toBe([-14_900, -2_384, -17_284])
        ->and($adjustment->reason)->toBe('Se cobró un centro de más')
        ->and($adjustment->catalog_version)->toBe('v1')
        ->and($adjustment->vat_percent)->toBe(16)
        ->and($original->refresh()->getAttributes())->toBe($before);

    $lines = StatementLine::query()->where('statement_id', $adjustment->id)->orderBy('position')->get();
    expect($lines->pluck('code')->all())->toBe(['adjustment', 'vat'])
        ->and($lines[0]->label)->toBe('Centro adicional')
        ->and($lines[0]->explanation)->toBe('Ajuste al estado de cuenta de dic 2026: Se cobró un centro de más')
        ->and($lines->pluck('amount_cents')->all())->toBe([-14_900, -2_384]);
    Log::shouldHaveReceived('info')->with('billing.statement_adjusted', ['tenant_id' => $original->tenant_id, 'adjusts_statement_id' => $original->id, 'total_cents' => -17_284]);
    $entry = AuditEntry::query()->where('event', 'billing.statement_adjusted')->sole();
    expect($entry->subject_id)->toBe($adjustment->id)
        ->and($entry->metadata['adjusts_statement_id'])->toBe($original->id)
        ->and($entry->metadata['total_cents'])->toBe(-17_284);
});

it('rounds the VAT of an adjustment half a cent up, away from zero, on either sign', function (int $amount, int $vat): void {
    [, $adjustment] = statementAdjusted($amount);

    expect($adjustment->vat_cents)->toBe($vat);
})->with([
    'a charge of $10.03' => [1_003, 160],
    'a credit of $10.03' => [-1_003, -160],
    'a charge of 3 cents (0.48 rounds to 0)' => [3, 0],
    'a charge of 4 cents (0.64 rounds to 1)' => [4, 1],
    'a credit of 4 cents' => [-4, -1],
]);

it('adjusts with a charge too', function (): void {
    [, $adjustment] = statementAdjusted(100_000);

    expect([$adjustment->subtotal_cents, $adjustment->vat_cents, $adjustment->total_cents])->toBe([100_000, 16_000, 116_000]);
});

it('refuses to adjust a draft, an adjustment, an amount of nothing, or a credit bigger than what the statement adds up to', function (): void {
    $draft = statementDraft();
    [$original, $adjustment] = statementAdjusted(-100_000);
    $adjust = fn (Statement $statement, int $cents, string $reason = 'Motivo') => fn () => resolve(AdjustStatement::class)->handle($statement, new AdjustmentData('Ajuste', $cents, $reason), User::factory()->create());

    expect($adjust($draft, 100))->toThrow(InvalidStatement::class, 'Solo se ajusta un estado de cuenta emitido.')
        ->and($adjust($adjustment, 100))->toThrow(InvalidStatement::class, 'Ajusta el estado de cuenta original, no un ajuste.')
        ->and($adjust($original, 0))->toThrow(InvalidStatement::class, 'El ajuste no puede ser de cero.')
        ->and($adjust($original, -400_000))->toThrow(InvalidStatement::class, 'El crédito es mayor que lo que suma ese estado de cuenta.')
        ->and($adjust($original, -390_001))->toThrow(InvalidStatement::class, 'El crédito es mayor que lo que suma ese estado de cuenta.')
        ->and($adjust($original, -390_000))->not->toThrow(InvalidStatement::class)
        ->and(Statement::query()->count())->toBe(4);
});

it('does not change the company', function (): void {
    [$tenant] = statementCompany();
    $before = $tenant->refresh()->getAttributes();
    $statement = resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create());
    resolve(AdjustStatement::class)->handle(resolve(IssueStatement::class)->handle($statement, User::factory()->create()), new AdjustmentData('Ajuste', 100, 'Motivo'), User::factory()->create());

    expect($tenant->refresh()->getAttributes())->toBe($before);
});
