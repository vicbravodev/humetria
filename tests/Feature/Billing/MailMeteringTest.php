<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\RecordUsage;
use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Listeners\CountSentMail;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Support\UsageQuota;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

mutates(RecordUsage::class, CountSentMail::class, UsageQuota::class);

const MAIL_METER = 'email_campaign';

final class MailMeterCampaignNote extends Notification implements MailBag
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('x')->line('y');
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}

final class MailMeterEssentialNote extends Notification implements MailBag
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}

function mailSentFor(Tenant $tenant, Notification $note, string $channel = 'mail'): void
{
    Context::add('tenant_id', $tenant->id);
    $note->id ??= (string) Str::uuid();
    event(new NotificationSent(new stdClass, $note, $channel));
}

function mailUsed(Tenant $tenant): int
{
    return (int) UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', MAIL_METER)->sum('used');
}

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
});

it('counts a campaign email once against the company of the request, with the notification id as its key', function (): void {
    $tenant = Tenant::factory()->create();
    $note = new MailMeterCampaignNote;
    $note->id = 'note-1';

    mailSentFor($tenant, $note);
    mailSentFor($tenant, $note);

    expect(mailUsed($tenant))->toBe(1)
        ->and(UsageEvent::query()->where('tenant_id', $tenant->id)->sole()->idempotency_key)->toBe('note-1');
});

it('counts each notification sent', function (): void {
    $tenant = Tenant::factory()->create();

    mailSentFor($tenant, new MailMeterCampaignNote);
    mailSentFor($tenant, new MailMeterCampaignNote);

    expect(mailUsed($tenant))->toBe(2);
});

it('does not count essential email, nor a channel other than mail', function (): void {
    $tenant = Tenant::factory()->create();

    mailSentFor($tenant, new MailMeterEssentialNote);
    mailSentFor($tenant, new MailMeterCampaignNote, 'database');

    expect(UsageEvent::query()->count())->toBe(0);
});

it('counts through Laravel\'s own sender, in the month of Mexico City', function (): void {
    $this->travelTo('2026-12-01 03:00:00'); // still 30 November in Mexico City
    $tenant = Tenant::factory()->create();
    Context::add('tenant_id', $tenant->id);

    NotificationFacade::route('mail', 'a@example.test')->notify(new MailMeterCampaignNote);

    expect(UsageCounter::query()->where('tenant_id', $tenant->id)->sole()->period_start->toDateString())->toBe('2026-11-01');
});

it('counts nothing and says so when the send has no company', function (): void {
    Log::spy();
    Context::forget('tenant_id');
    $note = new MailMeterCampaignNote;
    $note->id = 'orphan';

    event(new NotificationSent(new stdClass, $note, 'mail'));

    expect(UsageEvent::query()->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once()->with('usage.mail_without_company', ['notification' => MailMeterCampaignNote::class]);
});

describe('the quota of campaign email', function (): void {
    it('is 8 per seat with a minimum of 500: 62 seats is 500, 63 is 504', function (int $seats, int $quota): void {
        [, $account] = paidCompany();
        $tenant = planFor($account, ['seats' => $seats]);

        expect(resolve(UsageQuota::class)->for($tenant->id, MAIL_METER, now()->startOfMonth()->toImmutable()))->toBe($quota);
    })->with([[1, 500], [62, 500], [63, 504], [100, 800], [250, 2000]]);

    it('is the minimum for a company without a plan', function (): void {
        $tenant = Tenant::factory()->create();

        expect(resolve(UsageQuota::class)->for($tenant->id, MAIL_METER, now()->startOfMonth()->toImmutable()))->toBe(500);
    });

    it('only measures: past the quota the email still goes out and the snapshot shows nothing left', function (): void {
        $tenant = Tenant::factory()->create();

        foreach (range(1, 501) as $n) {
            $note = new MailMeterCampaignNote;
            $note->id = "n{$n}";
            mailSentFor($tenant, $note);
        }

        $snapshot = resolve(UsageMeter::class)->snapshot($tenant->id, MAIL_METER);
        expect($snapshot->used)->toBe(501)->and($snapshot->quota)->toBe(500)->and($snapshot->remaining())->toBe(0);
    });
});

it('keeps the mail of one company out of another\'s count', function (): void {
    [$a, $b] = [Tenant::factory()->create(), Tenant::factory()->create()];

    mailSentFor($a, new MailMeterCampaignNote);

    expect(mailUsed($a))->toBe(1)->and(mailUsed($b))->toBe(0);
});

it('counts nothing and says so when the notification has no id to count it once by', function (): void {
    Log::spy();
    Context::add('tenant_id', Tenant::factory()->create()->id);

    event(new NotificationSent(new stdClass, new MailMeterCampaignNote, 'mail'));

    expect(UsageEvent::query()->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once()->with('usage.mail_without_id', ['notification' => MailMeterCampaignNote::class]);
});
