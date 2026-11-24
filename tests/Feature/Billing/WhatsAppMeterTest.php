<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\ReleaseBulkHolds;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Contracts\WhatsAppQuota;
use App\Modules\Billing\Exceptions\WhatsAppQuotaExceeded;
use App\Modules\Billing\Listeners\CountWhatsApp;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Support\LedgerWhatsAppQuota;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Events\WhatsAppMessageAccepted;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

mutates(LedgerWhatsAppQuota::class, CountWhatsApp::class, ReleaseBulkHolds::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
});

/** A company on a plan that includes exactly `$quota` WhatsApp messages a month, entered as the current one. */
function whatsAppCompany(int $quota): Tenant
{
    config(['billing.versions.v1.plans.cumplimiento.quotas.whatsapp' => $quota]);
    [, $account] = paidCompany();
    $tenant = planFor($account);
    resolve(Companies::class)->enter($tenant->id);

    return $tenant;
}

function whatsAppCounter(Tenant $tenant, string $period = '2026-11-01'): UsageCounter
{
    return UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', 'whatsapp')->whereDate('period_start', $period)->sole();
}

function failedEvent(Tenant $tenant, string $deliveryId, ?CarbonImmutable $createdAt = null): WhatsAppMessageFailed
{
    return new WhatsAppMessageFailed($deliveryId, $tenant->id, $createdAt ?? CarbonImmutable::now(), new InvitationReference('campaign_participant', (string) Str::uuid(), 'campaign', (string) Str::uuid()), FailureCause::NoWhatsappAccount);
}

it('holds the messages of a launch up to the quota and not one more: quota-1, exact and +1', function (int $count, bool $fits): void {
    $tenant = whatsAppCompany(100);

    if ($fits) {
        resolve(WhatsAppQuota::class)->reserve($count);
        expect(whatsAppCounter($tenant)->reserved)->toBe($count);

        return;
    }

    expect(fn () => resolve(WhatsAppQuota::class)->reserve($count))->toThrow(WhatsAppQuotaExceeded::class);
    expect(UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', 'whatsapp')->sum('reserved'))->toBe(0);
})->with([[99, true], [100, true], [101, false]]);

it('tells the person how many are left and how many are missing', function (): void {
    whatsAppCompany(100);
    resolve(WhatsAppQuota::class)->reserve(90);

    try {
        resolve(WhatsAppQuota::class)->reserve(25);
        $this->fail('It should not fit.');
    } catch (WhatsAppQuotaExceeded $exception) {
        expect($exception->getMessage())->toContain('Tu cupo de WhatsApp del mes es de 100 mensajes y te quedan 10; esto manda 25. No se envió nada.');
    }
});

it('lets the next launch use what the last one released', function (): void {
    $tenant = whatsAppCompany(100);
    resolve(WhatsAppQuota::class)->reserve(100);

    expect(fn () => resolve(WhatsAppQuota::class)->reserve(1))->toThrow(WhatsAppQuotaExceeded::class);

    resolve(ReleaseBulkHolds::class)->handle();
    resolve(WhatsAppQuota::class)->reserve(1);

    expect(whatsAppCounter($tenant)->reserved)->toBe(1);
});

it('cuts at the cap the owner set when it is lower than the plan, and at the plan when the cap is higher', function (): void {
    $tenant = whatsAppCompany(100);
    TenantChannelFactory::new()->for($tenant)->whatsAppEnabled()->create(['monthly_cap' => 10]);

    resolve(WhatsAppQuota::class)->reserve(10);
    expect(fn () => resolve(WhatsAppQuota::class)->reserve(1))->toThrow(WhatsAppQuotaExceeded::class);

    $other = whatsAppCompany(20);
    TenantChannelFactory::new()->for($other)->whatsAppEnabled()->create(['monthly_cap' => 5_000]);
    resolve(WhatsAppQuota::class)->reserve(20);

    expect(fn () => resolve(WhatsAppQuota::class)->reserve(1))->toThrow(WhatsAppQuotaExceeded::class);
});

it('gives a company with no plan no WhatsApp at all', function (): void {
    $tenant = Tenant::factory()->create();
    resolve(Companies::class)->enter($tenant->id);

    expect(config('billing.unplanned_quotas.whatsapp'))->toBe(0)
        ->and(fn () => resolve(WhatsAppQuota::class)->reserve(1))->toThrow(WhatsAppQuotaExceeded::class);
});

it('reserves nothing for zero messages', function (): void {
    $tenant = whatsAppCompany(100);

    resolve(WhatsAppQuota::class)->reserve(0);

    expect(UsageCounter::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('keeps each company to its own quota', function (): void {
    $a = whatsAppCompany(10);
    resolve(WhatsAppQuota::class)->reserve(10);
    $b = whatsAppCompany(10);

    resolve(WhatsAppQuota::class)->reserve(10);

    expect(whatsAppCounter($a)->reserved)->toBe(10)->and(whatsAppCounter($b)->reserved)->toBe(10);
});

it('ships a plan quota of 1000 messages in the four plans', function (): void {
    foreach (['jornada', 'cumplimiento', 'integral', 'enterprise'] as $plan) {
        expect(config("billing.versions.v1.plans.{$plan}.quotas.whatsapp"))->toBe(1000);
    }
});

it('settles an accepted message as used, with its cost and price, once', function (): void {
    $tenant = whatsAppCompany(100);
    resolve(WhatsAppQuota::class)->reserve(3);
    $id = (string) Str::uuid();

    event(new WhatsAppMessageAccepted($id, $tenant->id));
    event(new WhatsAppMessageAccepted($id, $tenant->id));

    $row = UsageEvent::query()->where('tenant_id', $tenant->id)->where('meter', 'whatsapp')->sole();
    expect($row->idempotency_key)->toBe($id)
        ->and($row->quantity)->toBe(1)
        ->and(whatsAppCounter($tenant)->used)->toBe(1)
        ->and(whatsAppCounter($tenant)->reserved)->toBe(2)
        ->and(DB::table('usage_events')->where('id', $row->id)->first())->toMatchObject(['unit_cost_micros' => 170_000, 'unit_price_micros' => 221_000, 'cost_currency' => 'MXN']);
});

it('gives back the hold of a message that failed', function (): void {
    $tenant = whatsAppCompany(100);
    resolve(WhatsAppQuota::class)->reserve(3);

    event(failedEvent($tenant, (string) Str::uuid()));

    expect(whatsAppCounter($tenant)->reserved)->toBe(2)->and(whatsAppCounter($tenant)->used)->toBe(0);
});

it('never takes the hold below zero', function (): void {
    $tenant = whatsAppCompany(100);
    resolve(WhatsAppQuota::class)->reserve(1);

    event(failedEvent($tenant, (string) Str::uuid()));
    event(failedEvent($tenant, (string) Str::uuid()));

    expect(whatsAppCounter($tenant)->reserved)->toBe(0);
});

it('does not give back what was already settled: a failure after acceptance is for the reconciliation', function (): void {
    $tenant = whatsAppCompany(100);
    resolve(WhatsAppQuota::class)->reserve(3);
    $id = (string) Str::uuid();

    event(new WhatsAppMessageAccepted($id, $tenant->id));
    event(failedEvent($tenant, $id));

    expect(whatsAppCounter($tenant)->used)->toBe(1)->and(whatsAppCounter($tenant)->reserved)->toBe(2);
});

it('gives back the hold in the month the message was created in', function (): void {
    $tenant = whatsAppCompany(100);
    UsageCounter::factory()->create(['tenant_id' => $tenant->id, 'meter' => 'whatsapp', 'period_start' => '2026-10-01', 'reserved' => 2]);
    resolve(WhatsAppQuota::class)->reserve(1);

    event(failedEvent($tenant, (string) Str::uuid(), CarbonImmutable::parse('2026-10-31 20:00:00')));

    expect(whatsAppCounter($tenant, '2026-10-01')->reserved)->toBe(1)->and(whatsAppCounter($tenant)->reserved)->toBe(1);
});

it('does nothing for a failed message of a month that has no counter', function (): void {
    $tenant = whatsAppCompany(100);

    event(failedEvent($tenant, (string) Str::uuid(), CarbonImmutable::parse('2026-09-15')));

    expect(UsageCounter::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('releases the holds of mail and WhatsApp, and of no other meter, in one pass that logs the total', function (): void {
    Log::spy();
    UsageCounter::factory()->create(['meter' => 'email_campaign', 'period_start' => '2026-11-01', 'reserved' => 10]);
    UsageCounter::factory()->create(['meter' => 'whatsapp', 'period_start' => '2026-11-01', 'reserved' => 5]);
    UsageCounter::factory()->create(['meter' => 'assistant_tokens', 'period_start' => '2026-11-01', 'reserved' => 50]);

    $released = resolve(ReleaseBulkHolds::class)->handle();

    expect($released)->toBe(15)
        ->and(UsageCounter::query()->where('meter', 'assistant_tokens')->sole()->reserved)->toBe(50)
        ->and(UsageCounter::query()->whereIn('meter', ['email_campaign', 'whatsapp'])->sum('reserved'))->toBe(0);
    Log::shouldHaveReceived('info')->with('usage.holds_released', ['released' => 15])->once();
});

it('names the two bulk meters', function (): void {
    expect(UsageMeter::EMAIL_CAMPAIGN)->toBe('email_campaign')->and(UsageMeter::WHATSAPP)->toBe('whatsapp');
});

it('refuses a price below the cost, or a cost without its currency, in a usage line', function (array $cost): void {
    $row = [...UsageEvent::factory()->raw(['meter' => 'whatsapp']), ...$cost];
    $row['period_start'] = '2026-11-01';

    try {
        DB::transaction(fn () => DB::table('usage_events')->insert([...$row, 'id' => (string) Str::uuid(), 'tenant_id' => Tenant::factory()->create()->id]));
        $this->fail('The database should refuse it.');
    } catch (QueryException $refusal) {
        expect($refusal->getMessage())->toContain('usage_events_cost_complete');
    }
})->with([
    'price below the cost' => [['unit_cost_micros' => 100, 'unit_price_micros' => 99, 'cost_currency' => 'MXN']],
    'no currency' => [['unit_cost_micros' => 100, 'unit_price_micros' => 130, 'cost_currency' => null]],
    'only the cost' => [['unit_cost_micros' => 100, 'unit_price_micros' => null, 'cost_currency' => 'MXN']],
]);
