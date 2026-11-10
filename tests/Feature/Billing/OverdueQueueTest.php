<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Actions\GenerateStatement;
use App\Modules\Billing\Actions\IssueStatement;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/** An issued statement of November for the company, issued on that day, whatever today is. */
function queueIssued(string $tenantId, string $issuedAt): void
{
    $back = now();
    test()->travelTo($issuedAt);
    resolve(IssueStatement::class)->handle(resolve(GenerateStatement::class)->handle($tenantId, CarbonImmutable::parse('2026-11-01'), null), User::factory()->create());
    test()->travelTo($back);
}

function queueAccountOf(App\Modules\Tenancy\Models\Tenant $tenant): BillingAccount
{
    return BillingAccount::query()->findOrFail(AccountCompany::query()->where('tenant_id', $tenant->id)->value('billing_account_id'));
}

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
});

it('lists as a queue the accounts with a statement still open 15 days after it was issued, with what they owe past due', function (): void {
    [$late] = statementCompany(['starts_on' => '2026-09-01']);
    [$recent] = statementCompany(['starts_on' => '2026-09-01']);
    [$clean] = statementCompany(['starts_on' => '2026-09-01']);
    AccountCompany::query()->update(['starts_on' => '2026-09-01']);
    queueIssued($late->id, '2026-11-11 12:00:00');
    queueIssued($recent->id, '2026-11-12 16:00:00');

    $this->actingAs(superadmin())->get(route('billing.accounts.index', ['kind' => 'overdue']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters.kind', 'overdue')
        ->where('counts.overdue', 1)
        ->where('counts.all', 3)
        ->has('accounts.data', 1)
        ->where('accounts.data.0.id', queueAccountOf($late)->id)
        ->where('accounts.data.0.overdue', '$5,684'));
    expect($clean->id)->not->toBe($late->id);
});

it('shows no overdue amount on the rest of the list', function (): void {
    [$tenant] = statementCompany(['starts_on' => '2026-09-01']);
    AccountCompany::query()->update(['starts_on' => '2026-09-01']);

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('accounts.data.0.overdue', null)->where('counts.overdue', 0));
    expect($tenant->id)->not->toBeEmpty();
});

it('lets support read the queue too', function (): void {
    $this->actingAs(support())->get(route('billing.accounts.index', ['kind' => 'overdue']))->assertOk();
});
