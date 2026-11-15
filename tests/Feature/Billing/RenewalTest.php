<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Billing\Actions\RenewSubscriptions;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;

mutates(RenewSubscriptions::class);

beforeEach(function (): void {
    // 4:00 on 1 December in Mexico City.
    $this->travelTo('2026-12-01 10:00:00');
});

it('drafts the month of every company that pays, never issuing, with no author and the next renewal moved a month on', function (): void {
    [$tenant, $subscription] = statementCompany();

    $summary = resolve(RenewSubscriptions::class)->handle();

    $statement = Statement::query()->where('tenant_id', $tenant->id)->sole();
    expect($statement->status)->toBe(StatementStatus::Draft)
        ->and($statement->period_start->toDateString())->toBe('2026-12-01')
        ->and($statement->created_by)->toBeNull()
        ->and($subscription->refresh()->renews_on->toDateString())->toBe('2027-01-01')
        ->and($summary->drafted)->toBe(1)->and($summary->alreadyDrafted)->toBe(0);
});

it('changes nothing the second time it runs', function (): void {
    [$tenant, $subscription] = statementCompany();
    resolve(RenewSubscriptions::class)->handle();
    $first = Statement::query()->where('tenant_id', $tenant->id)->sole();

    $again = resolve(RenewSubscriptions::class)->handle();

    expect(Statement::query()->where('tenant_id', $tenant->id)->sole()->id)->toBe($first->id)
        ->and(Statement::query()->count())->toBe(1)
        ->and($subscription->refresh()->renews_on->toDateString())->toBe('2027-01-01')
        ->and($again->drafted)->toBe(0)->and($again->alreadyDrafted)->toBe(1);
});

it('leaves alone a month that was already issued', function (): void {
    [$tenant] = statementCompany();
    $statement = resolve(App\Modules\Billing\Actions\GenerateStatement::class)->handle($tenant->id, statementMonth(), App\Models\User::factory()->create());
    $statement->forceFill(['status' => StatementStatus::Issued, 'issued_at' => now(), 'issued_by' => $statement->created_by])->save();

    resolve(RenewSubscriptions::class)->handle();

    expect(Statement::query()->count())->toBe(1)->and($statement->refresh()->status)->toBe(StatementStatus::Issued);
});

it('takes the month from the calendar of Mexico City, not from UTC', function (): void {
    $this->travelTo('2026-12-01 03:00:00'); // still the evening of 30 November there
    [$tenant] = statementCompany(['starts_on' => '2026-10-01', 'renews_on' => '2026-11-01']);
    App\Modules\Billing\Models\AccountCompany::query()->update(['starts_on' => '2026-10-01']);

    resolve(RenewSubscriptions::class)->handle();

    expect(Statement::query()->where('tenant_id', $tenant->id)->sole()->period_start->toDateString())->toBe('2026-11-01');
});

it('does not bill a trial, an ended plan, or a plan that has not started', function (SubscriptionStatus $status, string $startsOn): void {
    [$tenant] = statementCompany(['status' => $status, 'starts_on' => $startsOn, 'renews_on' => '2027-02-01']);

    $summary = resolve(RenewSubscriptions::class)->handle();

    expect(Statement::query()->where('tenant_id', $tenant->id)->count())->toBe(0)->and($summary->skipped)->toBe([]);
})->with([
    'trial' => [SubscriptionStatus::Trial, '2026-11-01'],
    'ended' => [SubscriptionStatus::Ended, '2026-11-01'],
    'starts next month' => [SubscriptionStatus::Active, '2027-01-01'],
]);

it('still bills a company with a balance past due', function (): void {
    [$tenant] = statementCompany(['status' => SubscriptionStatus::PastDue]);

    resolve(RenewSubscriptions::class)->handle();

    expect(Statement::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

describe('a cancellation scheduled for the cut', function (): void {
    it('ends the plan at the cut, with no statement for the month it no longer covers, and says so in the log and the audit', function (): void {
        Log::spy();
        [$tenant, $subscription] = statementCompany(['status' => SubscriptionStatus::CancelScheduled, 'renews_on' => '2026-12-01']);

        $summary = resolve(RenewSubscriptions::class)->handle();

        expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Ended)
            ->and(Statement::query()->count())->toBe(0)
            ->and($summary->ended)->toBe(1)
            ->and(AuditEntry::query()->where('event', 'billing.subscription_ended')->where('subject_id', $subscription->id)->count())->toBe(1);
        Log::shouldHaveReceived('info')->with('billing.subscription_ended', ['tenant_id' => $tenant->id, 'period' => '2026-12'])->once();
    });

    it('ends it once: the second run finds it ended', function (): void {
        statementCompany(['status' => SubscriptionStatus::CancelScheduled, 'renews_on' => '2026-12-01']);
        resolve(RenewSubscriptions::class)->handle();

        $again = resolve(RenewSubscriptions::class)->handle();

        expect($again->ended)->toBe(0)->and(AuditEntry::query()->where('event', 'billing.subscription_ended')->count())->toBe(1);
    });

    it('keeps billing a cancellation whose cut has not come', function (): void {
        [$tenant, $subscription] = statementCompany(['status' => SubscriptionStatus::CancelScheduled, 'renews_on' => '2027-01-01']);

        $summary = resolve(RenewSubscriptions::class)->handle();

        expect($subscription->refresh()->status)->toBe(SubscriptionStatus::CancelScheduled)
            ->and(Statement::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
            ->and($summary->ended)->toBe(0);
    });
});

it('skips the company that cannot be drafted, says why, and goes on with the rest', function (): void {
    Log::spy();
    [$needsQuote] = statementCompany(['seats' => 150]);
    [$fine] = statementCompany();
    $unlinked = Tenant::factory()->create();
    Subscription::factory()->create(['tenant_id' => $unlinked->id, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);

    $summary = resolve(RenewSubscriptions::class)->handle();

    expect(Statement::query()->pluck('tenant_id')->all())->toBe([$fine->id])
        ->and(array_column($summary->skipped, 'tenant_id'))->toEqualCanonicalizing([$needsQuote->id, $unlinked->id])
        ->and($summary->drafted)->toBe(1);
    Log::shouldHaveReceived('warning')->with('billing.renewal_skipped', Mockery::on(fn (array $context): bool => $context['tenant_id'] === $unlinked->id && is_string($context['reason'])))->once();
});

it('totals what it drafted by paying account, so a consultant is reviewed once', function (): void {
    [, $account] = paidCompany('consultant');
    $a = planFor($account);
    $b = planFor($account);
    [$other] = statementCompany();

    $summary = resolve(RenewSubscriptions::class)->handle();

    $row = collect($summary->byAccount)->firstWhere('account_id', $account->id);
    expect($summary->byAccount)->toHaveCount(2)
        ->and($row['drafts'])->toBe(2)
        ->and($row['total_cents'])->toBe((int) Statement::query()->whereIn('tenant_id', [$a->id, $b->id])->sum('total_cents'))
        ->and($other->id)->not->toBe($a->id);
});

it('writes one line of log for the run, with counts', function (): void {
    Log::spy();
    statementCompany();

    resolve(RenewSubscriptions::class)->handle();

    Log::shouldHaveReceived('info')->with('billing.renewal_run', ['period' => '2026-12', 'drafted' => 1, 'already_drafted' => 0, 'ended' => 0, 'skipped' => 0])->once();
});

it('runs from the billing:renew command', function (): void {
    [$tenant] = statementCompany();

    $this->artisan('billing:renew')->expectsOutputToContain('Borradores: 1')->assertSuccessful();

    expect(Statement::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('shows a draft the cut made as made by the monthly renewal, not by a person', function (): void {
    [$tenant] = statementCompany();
    resolve(RenewSubscriptions::class)->handle();

    $described = resolve(App\Modules\Billing\Actions\DescribeStatement::class)->handle(Statement::query()->where('tenant_id', $tenant->id)->sole());

    expect($described['statement']['created_by'])->toBe('Renovación mensual');
});
