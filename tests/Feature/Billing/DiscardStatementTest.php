<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\DiscardStatement;
use App\Modules\Billing\Actions\GenerateStatement;
use App\Modules\Billing\Actions\IssueStatement;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\StatementLine;
use App\Modules\Billing\Models\SubscriptionCharge;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(DiscardStatement::class);

it('discards a draft with its lines and gives back the one-time charges it billed', function (): void {
    Log::spy();
    [$tenant, $subscription] = statementCompany();
    $charge = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $tenant->id, 'charge_on' => '2026-12-10']);
    $draft = resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create());
    expect($charge->refresh()->billed_at)->not->toBeNull();

    resolve(DiscardStatement::class)->handle($draft);

    expect(Statement::query()->count())->toBe(0)
        ->and(StatementLine::query()->count())->toBe(0)
        ->and($charge->refresh()->billed_at)->toBeNull();
    Log::shouldHaveReceived('info')->with('billing.statement_discarded', ['tenant_id' => $tenant->id, 'period' => '2026-12', 'total_cents' => $draft->total_cents]);
    $entry = AuditEntry::query()->where('event', 'billing.statement_discarded')->sole();
    expect($entry->metadata)->toEqualCanonicalizing(['tenant_id' => $tenant->id, 'billing_account_id' => $draft->billing_account_id, 'period' => '2026-12', 'total_cents' => $draft->total_cents, 'released_charges' => 1]);

    // The charge can be billed again by the next draft of that month.
    $again = resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create());
    expect(StatementLine::query()->where('statement_id', $again->id)->where('code', 'charge_one_time')->sole()->charge_id)->toBe($charge->id);
});

it('never discards an issued statement', function (): void {
    [$tenant] = statementCompany();
    $issued = resolve(IssueStatement::class)->handle(resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create()), User::factory()->create());

    expect(fn () => resolve(DiscardStatement::class)->handle($issued))
        ->toThrow(InvalidStatement::class, 'Solo se descarta un borrador: uno emitido se corrige con un ajuste.')
        ->and(Statement::query()->count())->toBe(1)
        ->and(AuditEntry::query()->where('event', 'billing.statement_discarded')->count())->toBe(0);
});

it('discards from the console and goes back to the account', function (): void {
    [$tenant] = statementCompany();
    $draft = resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create());

    $this->actingAs(superadmin())->delete(route('billing.statements.destroy', $draft))
        ->assertRedirect(route('billing.accounts.show', $draft->billing_account_id))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Borrador descartado.']]);

    expect(Statement::query()->count())->toBe(0);
});

it('tells the person when the statement was already issued', function (): void {
    [$tenant] = statementCompany();
    $issued = resolve(IssueStatement::class)->handle(resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create()), User::factory()->create());

    $this->actingAs(superadmin())->delete(route('billing.statements.destroy', $issued))->assertSessionHasErrors('statement');

    expect(Statement::query()->count())->toBe(1);
});

it('is the superadmin\'s alone and 404s for a statement that does not exist', function (): void {
    [$tenant] = statementCompany();
    $draft = resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create());

    $this->actingAs(support())->delete(route('billing.statements.destroy', $draft))->assertNotFound();
    $this->actingAs(ownerOf($tenant))->delete(route('billing.statements.destroy', $draft))->assertNotFound();
    $this->actingAs(superadmin())->delete(route('billing.statements.destroy', '11111111-1111-4111-8111-111111111111'))->assertNotFound();
    auth()->logout();
    $this->delete(route('billing.statements.destroy', $draft))->assertRedirect(route('login'));

    expect(Statement::query()->count())->toBe(1);
});
