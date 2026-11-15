<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Actions\BuildStatement;
use App\Modules\Billing\Actions\GenerateStatement;
use App\Modules\Billing\Actions\IssueStatement;
use App\Modules\Billing\Actions\SendBillingNotices;
use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\EligibilityLostNotice;
use App\Modules\Billing\Notifications\NextAmountNotice;
use App\Modules\Billing\Notifications\OverdueBalanceNotice;
use App\Modules\Billing\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(SendBillingNotices::class);

/** What the mail says, as one text. */
function noticeText(object $notification): string
{
    $mail = $notification->toMail(new AnonymousNotifiable);

    return $mail->subject."\n".implode("\n", [...$mail->introLines, ...$mail->outroLines]);
}

function noticeAccount(App\Modules\Tenancy\Models\Tenant $tenant): BillingAccount
{
    return BillingAccount::query()->findOrFail(App\Modules\Billing\Models\AccountCompany::query()->where('tenant_id', $tenant->id)->value('billing_account_id'));
}

function noticeIntegral(BillingAccount $account, array $plan = []): App\Modules\Tenancy\Models\Tenant
{
    return planFor($account, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral, 'seats' => 100, 'centers' => 3, 'starts_on' => '2026-09-01', 'renews_on' => '2026-12-01', ...$plan]);
}

beforeEach(function (): void {
    Notification::fake();
    $this->travelTo('2026-11-26 15:00:00'); // nine in the morning of 26 November in Mexico City: five days before the cut
});

describe('the next amount', function (): void {
    it('goes to the account\'s contact five days before the cut, with each company and what it will be charged', function (): void {
        [$tenant] = statementCompany(['starts_on' => '2026-09-01']);
        $account = noticeAccount($tenant);
        $total = Money::format(resolve(BuildStatement::class)->handle($tenant->id, CarbonImmutable::parse('2026-12-01'))->totalCents);

        resolve(SendBillingNotices::class)->handle();

        Notification::assertSentOnDemand(NextAmountNotice::class, function (NextAmountNotice $notice, array $channels, object $notifiable) use ($account, $tenant, $total): bool {
            $text = noticeText($notice);

            return $notifiable->routes['mail'] === $account->contact_email && $channels === ['mail']
                && str_contains($text, $tenant->trade_name) && str_contains($text, $total) && str_contains($text, 'diciembre de 2026');
        });
    });

    it('waits for its day: the day before the window opens nothing is sent', function (): void {
        $this->travelTo('2026-11-25 15:00:00');
        statementCompany(['starts_on' => '2026-09-01']);

        resolve(SendBillingNotices::class)->handle();

        Notification::assertNothingSent();
    });

    it('is sent once a month, however many times the job runs', function (): void {
        statementCompany(['starts_on' => '2026-09-01']);

        resolve(SendBillingNotices::class)->handle();
        $this->travelTo('2026-11-28 15:00:00');
        resolve(SendBillingNotices::class)->handle();

        Notification::assertSentOnDemandTimes(NextAmountNotice::class, 1);

        $this->travelTo('2026-12-27 15:00:00');
        resolve(SendBillingNotices::class)->handle();

        Notification::assertSentOnDemandTimes(NextAmountNotice::class, 2);
    });

    it('is one mail per account that lists every company it pays', function (): void {
        [, $account] = paidCompany('consultant');
        $first = planFor($account, ['starts_on' => '2026-09-01']);
        $second = planFor($account, ['starts_on' => '2026-09-01']);

        resolve(SendBillingNotices::class)->handle();

        Notification::assertSentOnDemandTimes(NextAmountNotice::class, 1);
        Notification::assertSentOnDemand(NextAmountNotice::class, fn (NextAmountNotice $notice): bool => str_contains(noticeText($notice), $first->trade_name) && str_contains(noticeText($notice), $second->trade_name));
    });

    it('leaves out the company whose plan ends at the cut, and sends nothing when no company is left', function (): void {
        statementCompany(['status' => SubscriptionStatus::CancelScheduled, 'renews_on' => '2026-12-01', 'starts_on' => '2026-09-01']);
        statementCompany(['status' => SubscriptionStatus::Trial]);

        resolve(SendBillingNotices::class)->handle();

        Notification::assertNothingSent();
    });

    it('skips the company that cannot be priced without stopping the others', function (): void {
        [$unpriced] = statementCompany(['seats' => 150, 'starts_on' => '2026-09-01']);
        [$fine] = statementCompany(['starts_on' => '2026-09-01']);

        resolve(SendBillingNotices::class)->handle();

        Notification::assertSentOnDemandTimes(NextAmountNotice::class, 1);
        Notification::assertSentOnDemand(NextAmountNotice::class, fn (NextAmountNotice $notice): bool => str_contains(noticeText($notice), $fine->trade_name) && ! str_contains(noticeText($notice), $unpriced->trade_name));
    });
});

describe('losing the discount of the channel', function (): void {
    it('warns the consultant before the cut that falls below three Integral companies, once', function (): void {
        [, $account] = paidCompany('consultant');
        $account = BillingAccount::query()->findOrFail($account->id);
        noticeIntegral($account);
        noticeIntegral($account);
        $leaving = noticeIntegral($account, ['status' => SubscriptionStatus::CancelScheduled]);

        resolve(SendBillingNotices::class)->handle();
        resolve(SendBillingNotices::class)->handle();

        Notification::assertSentOnDemandTimes(EligibilityLostNotice::class, 1);
        Notification::assertSentOnDemand(EligibilityLostNotice::class, fn (EligibilityLostNotice $notice): bool => str_contains(noticeText($notice), 'tarifa directa') && $leaving->id !== '');
    });

    it('does not warn when three remain, or when the account never had the discount', function (): void {
        [, $keeps] = paidCompany('consultant');
        foreach (range(1, 3) as $ignored) {
            noticeIntegral($keeps);
        }
        noticeIntegral($keeps, ['status' => SubscriptionStatus::CancelScheduled]);
        [, $never] = paidCompany('consultant');
        noticeIntegral($never);
        noticeIntegral($never, ['status' => SubscriptionStatus::CancelScheduled]);

        resolve(SendBillingNotices::class)->handle();

        Notification::assertSentOnDemandTimes(EligibilityLostNotice::class, 0);
    });
});

describe('the balance past due', function (): void {
    function noticeIssued(string $tenantId, string $issuedAt): App\Modules\Billing\Models\Statement
    {
        $back = now();
        test()->travelTo($issuedAt);
        $statement = resolve(GenerateStatement::class)->handle($tenantId, CarbonImmutable::parse('2026-11-01'), null);
        $issued = resolve(IssueStatement::class)->handle($statement, User::factory()->create());
        test()->travelTo($back);

        return $issued;
    }

    it('tells the contact of an issued statement still unpaid 15 days after it was issued, once', function (): void {
        [$tenant] = statementCompany(['starts_on' => '2026-09-01']);
        $account = noticeAccount($tenant);
        App\Modules\Billing\Models\AccountCompany::query()->update(['starts_on' => '2026-09-01']);
        noticeIssued($tenant->id, '2026-11-11 12:00:00');

        resolve(SendBillingNotices::class)->handle();
        resolve(SendBillingNotices::class)->handle();

        Notification::assertSentOnDemandTimes(OverdueBalanceNotice::class, 1);
        Notification::assertSentOnDemand(OverdueBalanceNotice::class, fn (OverdueBalanceNotice $notice, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === $account->contact_email && str_contains(noticeText($notice), $tenant->trade_name));
    });

    it('waits the 15 days: with 14 it is too early', function (): void {
        [$tenant] = statementCompany(['starts_on' => '2026-09-01']);
        App\Modules\Billing\Models\AccountCompany::query()->update(['starts_on' => '2026-09-01']);
        noticeIssued($tenant->id, '2026-11-12 16:00:00');

        resolve(SendBillingNotices::class)->handle();

        Notification::assertSentOnDemandTimes(OverdueBalanceNotice::class, 0);
    });
});

it('sends the billing mail as essential, outside the campaign quota', function (): void {
    expect(new NextAmountNotice('a', [], 'x'))->toBeInstanceOf(MailBag::class)
        ->and((new NextAmountNotice('a', [], 'x'))->mailBag())->toBe(MailBag::ESSENTIAL);
});

it('writes one log line for each notice', function (): void {
    Log::spy();
    [$tenant] = statementCompany(['starts_on' => '2026-09-01']);
    $account = noticeAccount($tenant);

    resolve(SendBillingNotices::class)->handle();

    Log::shouldHaveReceived('info')->with('billing.notice_sent', ['account_id' => $account->id, 'kind' => 'next_amount'])->once();
});

it('runs from the billing:notify command', function (): void {
    statementCompany(['starts_on' => '2026-09-01']);

    $this->artisan('billing:notify')->expectsOutputToContain('Avisos: 1')->assertSuccessful();
});

it('is not sent for a company without a payer\'s plan, and the other accounts\' companies never mix', function (): void {
    [$tenant] = statementCompany(['starts_on' => '2026-09-01']);
    [$other] = statementCompany(['starts_on' => '2026-09-01']);
    $otherAccount = noticeAccount($other);

    resolve(SendBillingNotices::class)->handle();

    Notification::assertSentOnDemandTimes(NextAmountNotice::class, 2);
    Notification::assertSentOnDemand(NextAmountNotice::class, fn (NextAmountNotice $notice, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === $otherAccount->contact_email && ! str_contains(noticeText($notice), $tenant->trade_name) && str_contains(noticeText($notice), $other->trade_name));
    expect(Subscription::query()->count())->toBe(2);
});
