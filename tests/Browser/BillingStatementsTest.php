<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Actions\GenerateStatement;
use App\Modules\Billing\Actions\IssueStatement;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/**
 * An account that pays "Cliente Uno" (Cumplimiento, 100 people), with its December statement issued.
 *
 * @return array{0: BillingAccount, 1: Statement}
 */
function browserStatement(bool $issue = true): array
{
    $account = BillingAccount::factory()->create(['legal_name' => 'Grupo Directo S.A.']);
    $tenant = Tenant::factory()->create(['trade_name' => 'Cliente Uno']);
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $tenant->id]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    $statement = resolve(GenerateStatement::class)->handle($tenant->id, CarbonImmutable::parse('2026-12-01'), User::factory()->create());

    return [$account, $issue ? resolve(IssueStatement::class)->handle($statement, User::factory()->create()) : $statement];
}

it('shows a statement with its lines, explanations and totals', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    [, $statement] = browserStatement();

    visitPage('billing/statement', "/admin/billing/statements/{$statement->id}", $dark, $mobile)
        ->assertSee('Cliente Uno · dic 2026')
        ->assertSee('Emitido')
        ->assertSee('Conceptos')
        ->assertSee('100 personas × $49 = $4,900')
        ->assertSee('$5,684')
        ->assertSee('Saldo')
        ->assertSee('Aún no se aplica ningún pago')
        ->assertSee('Ver para imprimir')
        ->assertSee('Ajustar');
})->with($looks);

it('shows a draft with the way to issue it, and support without any button that changes it', function (): void {
    $this->actingAs(support());
    [, $draft] = browserStatement(false);

    visitPage('billing/statement', "/admin/billing/statements/{$draft->id}")
        ->assertSee('Borrador')
        ->assertDontSee('Emitir')
        ->assertDontSee('Ajustar')
        ->assertSee('Ver para imprimir');
});

it('issues a draft after asking', function (): void {
    signInAsSuperadmin();
    [, $draft] = browserStatement(false);

    visitPage('billing/statement', "/admin/billing/statements/{$draft->id}")
        ->click('Emitir')
        ->assertSee('Una vez emitido no se puede editar ni borrar')
        ->press('Confirmar emisión')
        ->assertSee('Estado de cuenta emitido.')
        ->assertSee('Ajustar');

    expect($draft->refresh()->status->value)->toBe('issued');
});

it('adjusts an issued statement with a credit', function (): void {
    signInAsSuperadmin();
    [, $statement] = browserStatement();

    visitPage('billing/statement', "/admin/billing/statements/{$statement->id}")
        ->click('Ajustar')
        ->fill('description', 'Centro adicional')
        ->fill('amount', '-149')
        ->fill('reason', 'Se cobró un centro de más')
        ->press('Emitir ajuste')
        ->assertSee('Ajuste emitido.')
        ->assertSee('Ajuste: Se cobró un centro de más');

    expect(Statement::query()->whereNotNull('adjusts_statement_id')->sole()->subtotal_cents)->toBe(-14_900);
});

it('lists the statements and payments of an account with what it owes', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    [$account, $statement] = browserStatement();
    $payment = Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 300_000, 'reference' => 'SPEI-77', 'paid_on' => '2026-12-09']);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $statement->id, 'amount_cents' => 300_000]);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}", $dark, $mobile)
        ->assertSee('Estados de cuenta')
        ->assertSee('Saldo por cobrar')
        ->assertSee('$2,684')
        ->assertSee('Pago parcial')
        ->assertSee('dic 2026')
        ->assertSee('Registrar pago')
        ->assertSee('Pagos')
        ->assertSee('SPEI-77')
        ->assertSee('Revertir');
})->with($looks);

it('shows support the statements and payments without the buttons that change them', function (): void {
    $this->actingAs(support());
    [$account] = browserStatement();
    Payment::factory()->create(['billing_account_id' => $account->id, 'reference' => 'SPEI-88']);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->assertSee('SPEI-88')
        ->assertDontSee('Registrar pago')
        ->assertDontSee('Revertir')
        ->assertDontSee('Generar estado de cuenta');
});

it('previews the lines of a month and generates its draft', function (): void {
    signInAsSuperadmin();
    $account = BillingAccount::factory()->create();
    $tenant = Tenant::factory()->create(['trade_name' => 'Cliente Nuevo']);
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $tenant->id]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->click('Generar estado de cuenta')
        ->fill('period', '2026-12')
        ->assertSee('100 personas × $49 = $4,900')
        ->assertSee('IVA')
        ->assertSee('$5,684')
        ->press('Generar borrador')
        ->assertSee('Estado de cuenta generado.')
        ->assertSee('Borrador')
        ->assertSee('Cliente Nuevo · dic 2026');

    expect(Statement::query()->sole()->total_cents)->toBe(568_400);
});

it('records a payment applied to a statement, and reverses it', function (): void {
    signInAsSuperadmin();
    [$account] = browserStatement();

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->click('Registrar pago')
        ->assertSee('Cliente Uno · dic 2026')
        ->fill('amount', '3000')
        ->fill('reference', 'SPEI-500')
        ->fill('[name^="allocations["]', '3000')
        ->press('Guardar pago')
        ->assertSee('Pago registrado.')
        ->assertSee('SPEI-500')
        ->assertSee('$2,684')
        ->click('Revertir')
        ->fill('reason', 'Cuenta equivocada')
        ->press('Revertir pago')
        ->assertSee('Pago revertido.')
        ->assertSee('Cuenta equivocada')
        ->assertSee('$5,684');

    expect(Payment::query()->sole()->isReversed())->toBeTrue();
});
