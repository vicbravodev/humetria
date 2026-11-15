<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\DescribeStatement;
use App\Modules\Billing\Actions\GenerateStatement;
use App\Modules\Billing\Actions\IssueStatement;
use App\Modules\Billing\Actions\ListAccountStatements;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Http\Requests\AdjustStatementRequest;
use App\Modules\Billing\Http\Requests\StatementPeriodRequest;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCharge;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DescribeStatement::class, ListAccountStatements::class, StatementPeriodRequest::class, AdjustStatementRequest::class);

/** @return array{0: Tenant, 1: App\Modules\Billing\Models\BillingAccount} a company with a December plan. */
function consoleCompany(): array
{
    [$tenant] = statementCompany();

    return [$tenant, App\Modules\Billing\Models\AccountCompany::query()->where('tenant_id', $tenant->id)->firstOrFail()->account];
}

function issuedStatement(?Tenant $tenant = null): Statement
{
    $tenant ??= consoleCompany()[0];

    return resolve(IssueStatement::class)->handle(resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create()), User::factory()->create());
}

it('lives under /admin/billing', function (): void {
    expect(route('billing.statements.store', 'c', absolute: false))->toBe('/admin/billing/companies/c/statements')
        ->and(route('billing.statements.preview', 'c', absolute: false))->toBe('/admin/billing/companies/c/statements/preview')
        ->and(route('billing.statements.show', 's', absolute: false))->toBe('/admin/billing/statements/s')
        ->and(route('billing.statements.print', 's', absolute: false))->toBe('/admin/billing/statements/s/print')
        ->and(route('billing.statements.issue', 's', absolute: false))->toBe('/admin/billing/statements/s/issue')
        ->and(route('billing.statements.adjustments.store', 's', absolute: false))->toBe('/admin/billing/statements/s/adjustments')
        ->and(route('billing.payments.store', 'a', absolute: false))->toBe('/admin/billing/accounts/a/payments')
        ->and(route('billing.payments.reverse', 'p', absolute: false))->toBe('/admin/billing/payments/p/reversal')
        ->and(route('billing.accounts.open-statements', 'a', absolute: false))->toBe('/admin/billing/accounts/a/open-statements');
});

it('keeps every statement and payment route out of reach while impersonating', function (): void {
    foreach (['billing.statements.store', 'billing.statements.preview', 'billing.statements.show', 'billing.statements.print', 'billing.statements.issue', 'billing.statements.adjustments.store', 'billing.payments.store', 'billing.payments.reverse', 'billing.accounts.open-statements'] as $name) {
        expect(App\Http\Middleware\ForbidWhileImpersonating::guards($name))->toBeTrue();
    }
});

it('previews the lines of a month without writing, and asks for a quotation instead of an amount', function (): void {
    [$tenant] = consoleCompany();
    $this->actingAs(superadmin())
        ->postJson(route('billing.statements.preview', $tenant->id), ['period' => '2026-12'])
        ->assertOk()
        ->assertJsonPath('period', 'dic 2026')
        ->assertJsonPath('lines.0.label', 'Cumplimiento')
        ->assertJsonPath('lines.0.amount', '$4,900')
        ->assertJsonPath('lines.0.explanation', '100 personas × $49 = $4,900')
        ->assertJsonPath('lines.1.label', 'IVA 16%')
        ->assertJsonPath('subtotal', '$4,900')
        ->assertJsonPath('vat', '$784')
        ->assertJsonPath('total', '$5,684');

    expect(Statement::query()->count())->toBe(0);

    Subscription::query()->where('tenant_id', $tenant->id)->update(['seats' => 150]);
    $this->postJson(route('billing.statements.preview', $tenant->id), ['period' => '2026-12'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['period' => 'Esta suscripción requiere cotización: fija un precio negociado antes de hacer el estado de cuenta.']);
});

it('refuses a period that is not a month', function (string $period): void {
    [$tenant] = consoleCompany();

    $this->actingAs(superadmin())->post(route('billing.statements.store', $tenant->id), ['period' => $period])->assertSessionHasErrors('period');

    expect(Statement::query()->count())->toBe(0);
})->with(['', 'diciembre', '2026-13', '2026-12-01', '26-12']);

it('generates the draft and lands on it; asking again lands on the same one', function (): void {
    [$tenant, $account] = consoleCompany();
    $admin = superadmin();

    $this->actingAs($admin)->post(route('billing.statements.store', $tenant->id), ['period' => '2026-12'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('billing.statements.show', Statement::query()->sole()))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Estado de cuenta generado.']]);

    expect(Statement::query()->sole())->status->toBe(StatementStatus::Draft)->created_by->toBe($admin->id)->billing_account_id->toBe($account->id);

    $this->post(route('billing.statements.store', $tenant->id), ['period' => '2026-12'])->assertRedirect(route('billing.statements.show', Statement::query()->sole()));
    expect(Statement::query()->count())->toBe(1);
});

it('says what is wrong when a company cannot be billed', function (): void {
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())->post(route('billing.statements.store', $tenant->id), ['period' => '2026-12'])
        ->assertSessionHasErrors(['period' => 'Asigna un plan a la empresa antes de hacerle un estado de cuenta.']);
});

it('answers 404 for a company that does not exist', function (): void {
    $this->actingAs(superadmin())->post(route('billing.statements.store', '11111111-1111-4111-8111-111111111111'), ['period' => '2026-12'])->assertNotFound();
    $this->postJson(route('billing.statements.preview', '11111111-1111-4111-8111-111111111111'), ['period' => '2026-12'])->assertNotFound();
});

it('issues a draft, and refuses to issue it twice', function (): void {
    [$tenant] = consoleCompany();
    $draft = resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create());

    $this->actingAs(superadmin())->post(route('billing.statements.issue', $draft))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('billing.statements.show', $draft))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Estado de cuenta emitido.']]);

    expect($draft->refresh()->status)->toBe(StatementStatus::Issued);

    $this->post(route('billing.statements.issue', $draft))->assertSessionHasErrors(['statement' => 'Este estado de cuenta ya está emitido.']);
});

it('adjusts an issued statement, in pesos and signed', function (): void {
    $statement = issuedStatement();

    $this->actingAs(superadmin())->post(route('billing.statements.adjustments.store', $statement), ['description' => 'Centro adicional', 'amount' => '-149', 'reason' => 'Se cobró un centro de más'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('billing.statements.show', $statement))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Ajuste emitido.']]);

    $adjustment = Statement::query()->whereNotNull('adjusts_statement_id')->sole();
    expect($adjustment->subtotal_cents)->toBe(-14_900)->and($adjustment->adjusts_statement_id)->toBe($statement->id);

    $this->post(route('billing.statements.adjustments.store', $statement), ['description' => 'Cargo olvidado', 'amount' => '10.50', 'reason' => 'Faltaba'])->assertSessionHasNoErrors();
    expect(Statement::query()->orderBy('created_at')->get()->pluck('subtotal_cents')->all())->toContain(1_050);
});

it('refuses an adjustment the rules refuse, with a message', function (array $input, string $field): void {
    $statement = issuedStatement();

    $this->actingAs(superadmin())->post(route('billing.statements.adjustments.store', $statement), ['description' => 'Ajuste', 'amount' => '10', 'reason' => 'Motivo', ...$input])->assertSessionHasErrors($field);

    expect(Statement::query()->count())->toBe(1);
})->with([
    'no description' => [['description' => ''], 'description'],
    'two lines of description' => [['description' => "a\nb"], 'description'],
    'no reason' => [['reason' => ''], 'reason'],
    'no amount' => [['amount' => ''], 'amount'],
    'an amount of zero' => [['amount' => '0'], 'amount'],
    'an amount with a comma' => [['amount' => '1,5'], 'amount'],
    'a plus sign' => [['amount' => '+5'], 'amount'],
    'a credit bigger than the statement' => [['amount' => '-9999'], 'amount'],
]);

it('is the superadmin\'s alone to write: support, owners and guests get a 404 or the login, and nothing is written', function (): void {
    [$tenant] = consoleCompany();
    $statement = issuedStatement($tenant);
    $draft = resolve(GenerateStatement::class)->handle(consoleCompany()[0]->id, statementMonth(), User::factory()->create());
    $writes = [
        ['post', route('billing.statements.store', $tenant->id), ['period' => '2027-01']],
        ['postJson', route('billing.statements.preview', $tenant->id), ['period' => '2027-01']],
        ['post', route('billing.statements.issue', $draft), []],
        ['post', route('billing.statements.adjustments.store', $statement), ['description' => 'x', 'amount' => '1', 'reason' => 'y']],
    ];

    foreach ([support(), ownerOf($tenant)] as $user) {
        foreach ($writes as [$method, $url, $body]) {
            /** @var TestResponse $response */
            $response = $this->actingAs($user)->{$method}($url, $body);
            $response->assertNotFound();
        }
    }

    auth()->logout();
    $this->post($writes[0][1], $writes[0][2])->assertRedirect(route('login'));

    expect(Statement::query()->count())->toBe(2)->and($draft->refresh()->status)->toBe(StatementStatus::Draft);
});

it('shows a statement with its lines, who made and issued it, what was paid of it and its adjustments', function (): void {
    [$tenant, $account] = consoleCompany();
    $tenant->update(['trade_name' => 'Cliente Uno']);
    $statement = issuedStatement($tenant);
    $payment = Payment::factory()->create(['billing_account_id' => $account->id, 'reference' => 'SPEI-77', 'amount_cents' => 300_000, 'paid_on' => '2026-12-09']);
    App\Modules\Billing\Models\PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $statement->id, 'amount_cents' => 300_000]);

    $this->actingAs(superadmin())->get(route('billing.statements.show', $statement))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/statement')
            ->where('statement.id', $statement->id)
            ->where('statement.account_id', $account->id)
            ->where('statement.account_name', $account->legal_name)
            ->where('statement.trade_name', 'Cliente Uno')
            ->where('statement.period', 'dic 2026')
            ->where('statement.state', 'partially_paid')
            ->where('statement.state_label', 'Pago parcial')
            ->where('statement.is_adjustment', false)
            ->where('statement.subtotal', '$4,900')
            ->where('statement.vat', '$784')
            ->where('statement.total', '$5,684')
            ->where('statement.paid', '$3,000')
            ->where('statement.balance', '$2,684')
            ->where('statement.catalog_version', 'v1')
            ->where('statement.vat_percent', 16)
            ->has('lines', 2)
            ->where('lines.0.label', 'Cumplimiento')
            ->where('lines.0.amount', '$4,900')
            ->where('lines.1.label', 'IVA 16%')
            ->has('payments', 1)
            ->where('payments.0.reference', 'SPEI-77')
            ->where('payments.0.amount', '$3,000')
            ->where('payments.0.paid_on', '9 dic 2026')
            ->where('payments.0.reversed', false)
            ->has('adjustments', 0)
            ->where('can_manage', true));
});

it('shows a draft without paid or balance, and an adjustment pointing at the original', function (): void {
    [$tenant] = consoleCompany();
    $draft = resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create());
    $original = issuedStatement();
    $adjustment = resolve(App\Modules\Billing\Actions\AdjustStatement::class)->handle($original, new App\Modules\Billing\Data\AdjustmentData('Ajuste', -10_000, 'Error'), User::factory()->create());

    $this->actingAs(support())->get(route('billing.statements.show', $draft))
        ->assertInertia(fn (Assert $page) => $page->where('statement.state', 'draft')->where('statement.paid', null)->where('statement.balance', null)->where('can_manage', false));
    $this->get(route('billing.statements.show', $adjustment))
        ->assertInertia(fn (Assert $page) => $page->where('statement.is_adjustment', true)->where('statement.adjusts_id', $original->id)->where('statement.reason', 'Error'));
    $this->get(route('billing.statements.show', $original))
        ->assertInertia(fn (Assert $page) => $page->has('adjustments', 1)->where('adjustments.0.id', $adjustment->id)->where('adjustments.0.total', '-$116'));
});

it('hides a statement from everyone but the staff, and answers 404 for one that does not exist', function (): void {
    $statement = issuedStatement();

    $this->actingAs(ownerOf(Tenant::query()->findOrFail($statement->tenant_id)))->get(route('billing.statements.show', $statement))->assertNotFound();
    $this->actingAs(superadmin())->get(route('billing.statements.show', '11111111-1111-4111-8111-111111111111'))->assertNotFound();
    $this->get('/admin/billing/statements/no-es-uuid')->assertNotFound();
});

it('prints a statement as plain HTML with its lines, totals and explanations, under the nonce of the CSP', function (): void {
    $statement = issuedStatement();

    $response = $this->actingAs(support())->get(route('billing.statements.print', $statement))
        ->assertOk()
        ->assertSee('Estado de cuenta')
        ->assertSee('dic 2026')
        ->assertSee('Cumplimiento')
        ->assertSee('100 personas × $49 = $4,900')
        ->assertSee('IVA 16%')
        ->assertSee('$5,684')
        ->assertSee('@media print', false);

    expect($response->getContent())->toMatch('/<style nonce="[^"]+">/');
    $this->actingAs(ownerOf(Tenant::query()->findOrFail($statement->tenant_id)))->get(route('billing.statements.print', $statement))->assertNotFound();
});

it('lists the statements of the account, a page at a time, with the paid, the balance and what the account owes in all', function (): void {
    [$tenant, $account] = consoleCompany();
    $tenant->update(['trade_name' => 'Cliente Uno']);
    $statement = issuedStatement($tenant);
    resolve(GenerateStatement::class)->handle($tenant->id, Carbon\CarbonImmutable::parse('2027-01-01'), User::factory()->create());
    $payment = Payment::factory()->create(['billing_account_id' => $account->id, 'amount_cents' => 568_400]);
    App\Modules\Billing\Models\PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $statement->id, 'amount_cents' => 568_400]);
    issuedStatement(); // another account's

    $this->actingAs(support())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page
            ->has('statements.data', 2)
            ->where('statements.total', 2)
            ->hasAll(['statements.current_page', 'statements.last_page', 'statements.per_page', 'statements.links'])
            ->where('statements.data.0.period', 'ene 2027')
            ->where('statements.data.0.state', 'draft')
            ->where('statements.data.0.paid', null)
            ->where('statements.data.0.balance', null)
            ->where('statements.data.1.id', $statement->id)
            ->where('statements.data.1.trade_name', 'Cliente Uno')
            ->where('statements.data.1.total', '$5,684')
            ->where('statements.data.1.paid', '$5,684')
            ->where('statements.data.1.balance', '$0')
            ->where('statements.data.1.state', 'paid')
            ->where('receivable.outstanding', '$0')
            ->where('receivable.open_statements', 0));
});

it('pages the statements 25 at a time', function (): void {
    [, $account] = consoleCompany();
    Statement::factory()->count(26)->issued()->create(['billing_account_id' => $account->id]);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page->has('statements.data', 25)->where('statements.total', 26));
});

it('adds up what the account owes across its issued statements', function (): void {
    [$tenant, $account] = consoleCompany();
    issuedStatement($tenant);
    $other = Tenant::factory()->create();
    App\Modules\Billing\Models\AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $other->id]);
    Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'tenant_id' => $other->id, 'subtotal_cents' => 100_000, 'vat_cents' => 0, 'total_cents' => 100_000]);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page->where('receivable.outstanding', '$6,684')->where('receivable.open_statements', 2));
});

it('does not touch the company: generating, issuing and adjusting leave its access, dates and restriction alone', function (): void {
    [$tenant] = consoleCompany();
    $tenant->update(['active_until' => '2027-03-01', 'restriction' => 'read_only', 'restriction_reason' => 'Prueba', 'restricted_at' => now()]);
    $before = $tenant->refresh()->getAttributes();
    $admin = superadmin();

    $this->actingAs($admin)->post(route('billing.statements.store', $tenant->id), ['period' => '2026-12'])->assertSessionHasNoErrors();
    $statement = Statement::query()->sole();
    $this->post(route('billing.statements.issue', $statement))->assertSessionHasNoErrors();
    $this->post(route('billing.statements.adjustments.store', $statement), ['description' => 'x', 'amount' => '1', 'reason' => 'y'])->assertSessionHasNoErrors();

    expect($tenant->refresh()->getAttributes())->toBe($before)
        ->and(AuditEntry::query()->pluck('event')->all())->toContain('billing.statement_issued', 'billing.statement_adjusted');
});

it('bills a charge added from the console in the month it is dated', function (): void {
    [$tenant] = consoleCompany();
    $charge = SubscriptionCharge::factory()->for(Subscription::query()->where('tenant_id', $tenant->id)->sole())->create(['tenant_id' => $tenant->id, 'charge_on' => '2026-12-10', 'amount_cents' => 100_000]);

    $this->actingAs(superadmin())->post(route('billing.statements.store', $tenant->id), ['period' => '2026-12']);

    expect($charge->refresh()->billed_at)->not->toBeNull()->and(Statement::query()->sole()->subtotal_cents)->toBe(590_000);
});
