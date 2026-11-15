<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\ExpireUsageReservations;
use App\Modules\Billing\Actions\ReleaseUsage;
use App\Modules\Billing\Actions\ReserveUsage;
use App\Modules\Billing\Actions\SettleUsage;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Models\UsageGrant;
use App\Modules\Billing\Models\UsageReservation;
use App\Modules\Billing\Support\UsageQuota;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

mutates(ReserveUsage::class, SettleUsage::class, ReleaseUsage::class, ExpireUsageReservations::class, UsageQuota::class);

const TOKENS = 'assistant_tokens';

/** A company whose quota for the month is exactly `$quota` tokens (a grant on top of nothing is not needed: a plan-less default is replaced). */
function quotaOf(int $quota): Tenant
{
    config(['billing.unplanned_quotas.assistant_tokens' => $quota]);

    return Tenant::factory()->create();
}

function counterOf(Tenant $tenant): UsageCounter
{
    return UsageCounter::query()->where('tenant_id', $tenant->id)->sole();
}

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
});

it('reserves up to the quota of the month and not one token more', function (int $quota, int $ask, bool $granted): void {
    $tenant = quotaOf($quota);

    expect(resolve(UsageMeter::class)->reserve($tenant->id, TOKENS, $ask, 'k1'))->toBe($granted);

    if ($granted) {
        expect(counterOf($tenant)->reserved)->toBe($ask);
    } else {
        expect(UsageReservation::query()->count())->toBe(0);
    }
})->with([
    'exactly the quota' => [9000, 9000, true],
    'one over' => [9000, 9001, false],
    'one under' => [9000, 8999, true],
]);

it('counts what is used and what is held against the quota', function (): void {
    $tenant = quotaOf(20_000);
    $meter = resolve(UsageMeter::class);

    expect($meter->reserve($tenant->id, TOKENS, 9000, 'a'))->toBeTrue()
        ->and($meter->reserve($tenant->id, TOKENS, 9000, 'b'))->toBeTrue()
        ->and($meter->reserve($tenant->id, TOKENS, 9000, 'c'))->toBeFalse();

    $meter->settle($tenant->id, 'a', 1000);

    // 1,000 used + 9,000 held leaves 10,000: the third fits now.
    expect($meter->reserve($tenant->id, TOKENS, 9000, 'c'))->toBeTrue()
        ->and(counterOf($tenant)->only(['used', 'reserved']))->toBe(['used' => 1000, 'reserved' => 18_000]);
});

it('reserves with one conditional statement on the counter: of two requests for the last tokens the second is refused', function (): void {
    $tenant = quotaOf(9000);
    $meter = resolve(UsageMeter::class);
    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $first = $meter->reserve($tenant->id, TOKENS, 9000, 'one');
    $second = $meter->reserve($tenant->id, TOKENS, 9000, 'two');

    expect([$first, $second])->toBe([true, false])
        ->and(array_values(array_filter($statements, fn (string $sql): bool => str_contains($sql, '"used" + "reserved" + ? <= ?'))))->toHaveCount(2)
        ->and(counterOf($tenant)->reserved)->toBe(9000);
});

it('settles with the real figure, releasing the difference, and records one event', function (): void {
    $tenant = quotaOf(50_000);
    $meter = resolve(UsageMeter::class);
    $meter->reserve($tenant->id, TOKENS, 9000, 'm1');

    $meter->settle($tenant->id, 'm1', 2500);

    expect(counterOf($tenant)->only(['used', 'reserved']))->toBe(['used' => 2500, 'reserved' => 0])
        ->and(UsageReservation::query()->sole()->state)->toBe('settled')
        ->and(UsageEvent::query()->sole()->only(['tenant_id', 'meter', 'quantity', 'idempotency_key']))->toBe(['tenant_id' => $tenant->id, 'meter' => TOKENS, 'quantity' => 2500, 'idempotency_key' => 'm1']);
});

it('records the real figure even when it is above what was reserved', function (): void {
    $tenant = quotaOf(10_000);
    $meter = resolve(UsageMeter::class);
    $meter->reserve($tenant->id, TOKENS, 9000, 'big');

    $meter->settle($tenant->id, 'big', 11_000);

    expect(counterOf($tenant)->only(['used', 'reserved']))->toBe(['used' => 11_000, 'reserved' => 0])
        ->and($meter->reserve($tenant->id, TOKENS, 1, 'next'))->toBeFalse();
});

it('counts a key once however many times it is settled or reserved', function (): void {
    $tenant = quotaOf(50_000);
    $meter = resolve(UsageMeter::class);

    expect($meter->reserve($tenant->id, TOKENS, 9000, 'same'))->toBeTrue()
        ->and($meter->reserve($tenant->id, TOKENS, 9000, 'same'))->toBeTrue()
        ->and(counterOf($tenant)->reserved)->toBe(9000);

    $meter->settle($tenant->id, 'same', 1000);
    $meter->settle($tenant->id, 'same', 1000);
    $meter->release($tenant->id, 'same');

    expect(counterOf($tenant)->only(['used', 'reserved']))->toBe(['used' => 1000, 'reserved' => 0])
        ->and(UsageEvent::query()->count())->toBe(1)
        // A key already settled does not reserve again either.
        ->and($meter->reserve($tenant->id, TOKENS, 9000, 'same'))->toBeTrue()
        ->and(counterOf($tenant)->reserved)->toBe(0);
});

it('keeps a key per company: another company may use the same one', function (): void {
    $a = quotaOf(50_000);
    $b = Tenant::factory()->create();
    $meter = resolve(UsageMeter::class);

    $meter->reserve($a->id, TOKENS, 9000, 'shared');
    $meter->reserve($b->id, TOKENS, 9000, 'shared');

    expect(UsageCounter::query()->where('tenant_id', $a->id)->sole()->reserved)->toBe(9000)
        ->and(UsageCounter::query()->where('tenant_id', $b->id)->sole()->reserved)->toBe(9000);
});

it('releases a reservation that failed, giving everything back and recording no event', function (): void {
    $tenant = quotaOf(9000);
    $meter = resolve(UsageMeter::class);
    $meter->reserve($tenant->id, TOKENS, 9000, 'boom');

    $meter->release($tenant->id, 'boom');

    expect(counterOf($tenant)->only(['used', 'reserved']))->toBe(['used' => 0, 'reserved' => 0])
        ->and(UsageReservation::query()->sole()->state)->toBe('released')
        ->and(UsageEvent::query()->count())->toBe(0)
        ->and($meter->reserve($tenant->id, TOKENS, 9000, 'again'))->toBeTrue();
});

it('ignores a key nobody reserved when settling or releasing', function (): void {
    $tenant = quotaOf(9000);

    resolve(UsageMeter::class)->settle($tenant->id, 'ghost', 500);
    resolve(UsageMeter::class)->release($tenant->id, 'ghost');

    expect(UsageEvent::query()->count())->toBe(0)->and(UsageCounter::query()->count())->toBe(0);
});

describe('expiring stale reservations', function (): void {
    it('releases what nobody settled after its time, and only that', function (): void {
        $tenant = quotaOf(50_000);
        $meter = resolve(UsageMeter::class);
        $meter->reserve($tenant->id, TOKENS, 9000, 'stale');
        $this->travel(29)->minutes();
        $meter->reserve($tenant->id, TOKENS, 9000, 'fresh');
        $this->travel(2)->minutes();

        $expired = resolve(ExpireUsageReservations::class)->handle();

        expect($expired)->toBe(1)
            ->and(UsageReservation::query()->where('idempotency_key', 'stale')->sole()->state)->toBe('expired')
            ->and(UsageReservation::query()->where('idempotency_key', 'fresh')->sole()->state)->toBe('reserved')
            ->and(counterOf($tenant)->reserved)->toBe(9000);
    });

    it('does not undo one that was settled in time', function (): void {
        $tenant = quotaOf(50_000);
        $meter = resolve(UsageMeter::class);
        $meter->reserve($tenant->id, TOKENS, 9000, 'done');
        $meter->settle($tenant->id, 'done', 100);
        $this->travel(2)->hours();

        expect(resolve(ExpireUsageReservations::class)->handle())->toBe(0)
            ->and(counterOf($tenant)->used)->toBe(100);
    });

    it('runs from the billing:expire-usage-reservations command', function (): void {
        $tenant = quotaOf(50_000);
        resolve(UsageMeter::class)->reserve($tenant->id, TOKENS, 9000, 'old');
        $this->travel(2)->hours();

        $this->artisan('billing:expire-usage-reservations')->expectsOutput('Reservas vencidas: 1')->assertSuccessful();

        expect(counterOf($tenant)->reserved)->toBe(0);
    });
});

describe('the month', function (): void {
    it('starts at midnight in Mexico City: 1 Nov 03:00 UTC is still October', function (): void {
        $tenant = quotaOf(50_000);
        $meter = resolve(UsageMeter::class);

        $this->travelTo('2026-11-01 03:00:00');
        $meter->reserve($tenant->id, TOKENS, 100, 'oct');
        $this->travelTo('2026-11-01 06:00:00');
        $meter->reserve($tenant->id, TOKENS, 100, 'nov');

        expect(UsageReservation::query()->where('idempotency_key', 'oct')->sole()->period_start->toDateString())->toBe('2026-10-01')
            ->and(UsageReservation::query()->where('idempotency_key', 'nov')->sole()->period_start->toDateString())->toBe('2026-11-01');
    });

    it('gives each month its own count, and settles into the month it was reserved in', function (): void {
        $tenant = quotaOf(9000);
        $meter = resolve(UsageMeter::class);
        $this->travelTo('2026-11-30 23:59:00');
        $meter->reserve($tenant->id, TOKENS, 9000, 'last');
        $this->travelTo('2026-12-01 06:01:00');

        // December starts clean even though November's answer is still arriving.
        expect($meter->reserve($tenant->id, TOKENS, 9000, 'first'))->toBeTrue();
        $meter->settle($tenant->id, 'last', 4000);

        $rows = UsageCounter::query()->orderBy('period_start')->get();

        expect($rows->map(fn ($row): array => [$row->period_start->toDateString(), $row->used, $row->reserved])->all())->toBe([['2026-11-01', 4000, 0], ['2026-12-01', 0, 9000]]);
    });
});

describe('the quota', function (): void {
    it('is the plan\'s for a company with one, from the catalog', function (int|string $plan, int $quota): void {
        [, $account] = paidCompany();
        $tenant = planFor($account, ['plan' => Plan::from((string) $plan)]);

        expect(resolve(UsageQuota::class)->for($tenant->id, TOKENS, now()->startOfMonth()->toImmutable()))->toBe($quota);
    })->with([['jornada', 250_000], ['cumplimiento', 500_000], ['integral', 2_000_000]]);

    it('is the Integral quota for a company without a plan', function (): void {
        $tenant = Tenant::factory()->create();

        expect(resolve(UsageQuota::class)->for($tenant->id, TOKENS, now()->startOfMonth()->toImmutable()))->toBe(2_000_000);
    });

    it('adds the grants of that month, and no other month\'s or company\'s', function (): void {
        $tenant = quotaOf(1000);
        UsageGrant::factory()->create(['tenant_id' => $tenant->id, 'period_start' => '2026-11-01', 'quantity' => 500]);
        UsageGrant::factory()->create(['tenant_id' => $tenant->id, 'period_start' => '2026-11-01', 'quantity' => 250]);
        UsageGrant::factory()->create(['tenant_id' => $tenant->id, 'period_start' => '2026-10-01', 'quantity' => 9999]);
        UsageGrant::factory()->create(['period_start' => '2026-11-01', 'quantity' => 9999]);

        expect(resolve(UsageQuota::class)->for($tenant->id, TOKENS, Carbon\CarbonImmutable::parse('2026-11-01')))->toBe(1750)
            ->and(resolve(UsageMeter::class)->reserve($tenant->id, TOKENS, 1750, 'all'))->toBeTrue();
    });
});

describe('the database', function (): void {
    it('refuses to change or delete a usage event', function (): void {
        $event = UsageEvent::factory()->create();

        expect(fn () => DB::transaction(fn () => UsageEvent::query()->whereKey($event->id)->update(['quantity' => 1])))->toThrow(QueryException::class, 'usage_events is append-only')
            ->and(fn () => DB::transaction(fn () => UsageEvent::query()->whereKey($event->id)->delete()))->toThrow(QueryException::class, 'usage_events is append-only');
    });

    it('refuses a second event with the same key in a company, and a negative counter', function (): void {
        $tenant = Tenant::factory()->create();
        UsageEvent::factory()->create(['tenant_id' => $tenant->id, 'idempotency_key' => 'x']);

        expect(fn () => DB::transaction(fn () => UsageEvent::factory()->create(['tenant_id' => $tenant->id, 'idempotency_key' => 'x'])))->toThrow(QueryException::class)
            ->and(fn () => DB::transaction(fn () => UsageCounter::factory()->create(['reserved' => 0, 'used' => 0, 'meter' => 'bytes'])))->toThrow(QueryException::class);
    });

    it('keeps a grant honest: a reason and a positive amount', function (): void {
        expect(fn () => DB::transaction(fn () => UsageGrant::factory()->create(['reason' => '  '])))->toThrow(QueryException::class)
            ->and(fn () => DB::transaction(fn () => UsageGrant::factory()->create(['quantity' => 0])))->toThrow(QueryException::class);
    });
});
