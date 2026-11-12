<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\ReleaseMailHolds;
use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Billing\Exceptions\MailQuotaExceeded;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Support\LedgerMailQuota;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Context;

mutates(LedgerMailQuota::class, ReleaseMailHolds::class);

final class MailQuotaCampaignNote extends Illuminate\Notifications\Notification implements App\Modules\Billing\Contracts\MailBag
{
    public function mailBag(): string
    {
        return App\Modules\Billing\Contracts\MailBag::CAMPAIGN;
    }
}

/** A company on a plan whose campaign email quota is exactly `$quota` for the month (500 is the floor, so the floor is set). */
function mailQuotaCompany(int $quota): Tenant
{
    config(['billing.versions.v1.plans.cumplimiento.quotas.email_minimum' => $quota, 'billing.versions.v1.plans.cumplimiento.quotas.email_per_seat' => 0]);
    [, $account] = paidCompany();
    $tenant = planFor($account);
    resolve(Companies::class)->enter($tenant->id);

    return $tenant;
}

function mailCounter(Tenant $tenant): UsageCounter
{
    return UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', 'email_campaign')->sole();
}

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
});

it('holds the mails of a bulk send up to the quota and not one more: quota-1, exact and +1', function (int $count, bool $fits): void {
    $tenant = mailQuotaCompany(100);

    if ($fits) {
        resolve(MailQuota::class)->reserve($count);
        expect(mailCounter($tenant)->reserved)->toBe($count);
    } else {
        expect(fn () => resolve(MailQuota::class)->reserve($count))->toThrow(MailQuotaExceeded::class);
        expect(UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', 'email_campaign')->sum('reserved'))->toEqual(0);
    }
})->with([[99, true], [100, true], [101, false]]);

it('counts what was used and what is held together, and says how many are missing', function (): void {
    $tenant = mailQuotaCompany(100);
    UsageCounter::factory()->create(['tenant_id' => $tenant->id, 'meter' => 'email_campaign', 'period_start' => '2026-11-01', 'used' => 60, 'reserved' => 20]);

    expect(fn () => resolve(MailQuota::class)->reserve(30))->toThrow(MailQuotaExceeded::class, 'Tu plan incluye 100 correos de campaña al mes');
    expect(fn () => resolve(MailQuota::class)->reserve(30))->toThrow(MailQuotaExceeded::class, 'esto manda 30');
    expect(fn () => resolve(MailQuota::class)->reserve(30))->toThrow(MailQuotaExceeded::class, 'faltan 10');
    resolve(MailQuota::class)->reserve(20);

    expect(mailCounter($tenant)->reserved)->toBe(40);
});

it('does nothing for zero mails', function (): void {
    $tenant = mailQuotaCompany(100);

    resolve(MailQuota::class)->reserve(0);

    expect(UsageCounter::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('does not limit a company without a plan', function (): void {
    $tenant = Tenant::factory()->create();
    resolve(Companies::class)->enter($tenant->id);

    resolve(MailQuota::class)->reserve(100_000);

    expect(UsageCounter::query()->count())->toBe(0);
});

it('hands each mail that goes out the place it held: used grows and the hold shrinks, never below zero', function (): void {
    $tenant = mailQuotaCompany(100);
    resolve(MailQuota::class)->reserve(2);
    Context::add('tenant_id', $tenant->id);

    foreach (['a', 'b', 'c'] as $id) {
        $note = new MailQuotaCampaignNote;
        $note->id = $id;
        event(new NotificationSent(new stdClass, $note, 'mail'));
    }

    expect(mailCounter($tenant)->used)->toBe(3)->and(mailCounter($tenant)->reserved)->toBe(0);
});

it('lets the next send use what the last one released', function (): void {
    $tenant = mailQuotaCompany(100);
    resolve(MailQuota::class)->reserve(100);

    expect(fn () => resolve(MailQuota::class)->reserve(1))->toThrow(MailQuotaExceeded::class);

    resolve(ReleaseMailHolds::class)->handle();
    resolve(MailQuota::class)->reserve(1);

    expect(mailCounter($tenant)->reserved)->toBe(1);
});

it('releases the holds nobody used, of every company, in a command that logs it', function (): void {
    Illuminate\Support\Facades\Log::spy();
    $a = mailQuotaCompany(100);
    resolve(MailQuota::class)->reserve(10);
    UsageCounter::factory()->create(['meter' => 'assistant_tokens', 'period_start' => '2026-11-01', 'reserved' => 50]);

    $this->artisan('billing:release-mail-holds')->expectsOutputToContain('Correos liberados: 10')->assertSuccessful();

    expect(mailCounter($a)->reserved)->toBe(0)->and(UsageCounter::query()->where('meter', 'assistant_tokens')->sole()->reserved)->toBe(50);
    Illuminate\Support\Facades\Log::shouldHaveReceived('info')->with('usage.mail_holds_released', ['released' => 10])->once();
});

it('keeps each company to its own quota', function (): void {
    $a = mailQuotaCompany(10);
    resolve(MailQuota::class)->reserve(10);
    [, $account] = paidCompany();
    $b = planFor($account);
    resolve(Companies::class)->enter($b->id);

    resolve(MailQuota::class)->reserve(10);

    expect(mailCounter($a)->reserved)->toBe(10)->and(mailCounter($b)->reserved)->toBe(10);
});

it('answers a form with a toast and the same page, and a JSON client with a 422', function (): void {
    mailQuotaCompany(5);
    Illuminate\Support\Facades\Route::post('probe-mail', fn () => resolve(MailQuota::class)->reserve(6))->middleware('web');

    $this->from('/previous')->post('probe-mail')->assertRedirect('/previous');
    $this->postJson('probe-mail')->assertStatus(422)->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'correos de campaña'));
});
