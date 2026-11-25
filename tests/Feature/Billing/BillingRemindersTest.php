<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Notifications\OverdueBalanceNotice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/** An account with one issued statement of $1,000, issued that many days ago. */
function remindable(int $days): BillingAccount
{
    $account = BillingAccount::factory()->create();
    Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'subtotal_cents' => 100_000, 'vat_cents' => 0, 'total_cents' => 100_000, 'issued_at' => now()->subDays($days)]);

    return $account;
}

beforeEach(function (): void {
    Notification::fake();
    $this->travelTo('2026-11-26 15:00:00');
});

it('reminds the contact of each chosen account what it owes past due, and nobody else', function (): void {
    $late = remindable(30);
    $other = remindable(40);
    remindable(50);

    $this->actingAs(superadmin())->post(route('billing.reminders.store'), ['account_ids' => [$late->id, $other->id]])
        ->assertRedirect()->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Recordatorio enviado a 2 cuentas.']);

    Notification::assertSentOnDemandTimes(OverdueBalanceNotice::class, 2);
    Notification::assertSentOnDemand(OverdueBalanceNotice::class, fn (OverdueBalanceNotice $notice, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === $late->contact_email);
});

it('skips a chosen account with nothing past due', function (): void {
    $current = remindable(5);
    $clean = BillingAccount::factory()->create();

    $this->actingAs(superadmin())->post(route('billing.reminders.store'), ['account_ids' => [$current->id, $clean->id]])
        ->assertInertiaFlash('toast', ['type' => 'info', 'message' => 'Ninguna de las cuentas elegidas tiene saldo vencido sin recordatorio de hoy.']);

    Notification::assertNothingSent();
});

it('reminds an account once a day, however many times it is asked', function (): void {
    $late = remindable(30);

    $this->actingAs(superadmin())->post(route('billing.reminders.store'), ['account_ids' => [$late->id]]);
    $this->post(route('billing.reminders.store'), ['account_ids' => [$late->id]]);
    Notification::assertSentOnDemandTimes(OverdueBalanceNotice::class, 1);

    $this->travelTo('2026-11-27 15:00:00');
    $this->post(route('billing.reminders.store'), ['account_ids' => [$late->id]]);
    Notification::assertSentOnDemandTimes(OverdueBalanceNotice::class, 2);
});

it('still reminds an account the overdue notice already told', function (): void {
    $late = remindable(30);
    resolve(App\Modules\Billing\Actions\SendBillingNotices::class)->handle();

    $this->actingAs(superadmin())->post(route('billing.reminders.store'), ['account_ids' => [$late->id]]);

    Notification::assertSentOnDemandTimes(OverdueBalanceNotice::class, 2);
});

it('logs each reminder and audits who sent them', function (): void {
    Log::spy();
    $late = remindable(30);
    $user = superadmin();

    $this->actingAs($user)->post(route('billing.reminders.store'), ['account_ids' => [$late->id]]);

    Log::shouldHaveReceived('info')->with('billing.notice_sent', ['account_id' => $late->id, 'kind' => 'reminder'])->once();
    $entry = AuditEntry::query()->where('event', 'billing.reminders_sent')->sole();
    expect($entry->actor_id)->toBe($user->id)->and($entry->metadata)->toBe(['account_ids' => [$late->id]]);
});

it('asks for accounts of the list, at most 25 and none twice', function (array $body): void {
    $this->actingAs(superadmin())->post(route('billing.reminders.store'), $body)->assertSessionHasErrors();

    Notification::assertNothingSent();
})->with([
    'none' => [['account_ids' => []]],
    'not a list' => [['account_ids' => 'x']],
    'not an id' => [['account_ids' => ['nope']]],
    'unknown' => [['account_ids' => ['5b1c7f39-7c8e-4c43-9a29-6c3f2a1b0d11']]],
    'twice' => [fn (): array => ['account_ids' => array_fill(0, 2, BillingAccount::factory()->create()->id)]],
    '26' => [fn (): array => ['account_ids' => BillingAccount::factory()->count(26)->create()->pluck('id')->all()]],
]);

it('answers 404 to support and to everyone who is not the superadmin, and sends nothing', function (Closure $who): void {
    $late = remindable(30);

    $this->actingAs($who())->post(route('billing.reminders.store'), ['account_ids' => [$late->id]])->assertNotFound();

    Notification::assertNothingSent();
})->with([
    'support' => [fn (): User => support()],
    'a user' => [fn (): User => User::factory()->create()],
]);

it('sends guests to the login', function (): void {
    $this->post(route('billing.reminders.store'), ['account_ids' => []])->assertRedirect(route('login'));
});
