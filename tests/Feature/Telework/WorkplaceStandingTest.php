<?php

declare(strict_types=1);

use App\Modules\Telework\Enums\WorkplaceStatus;
use App\Modules\Telework\Models\TeleworkSettings;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\WorkplaceStanding;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;

mutates(WorkplaceStanding::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id])->id]);
});

/** A review of the test's workplace. */
function review(string $state = 'remote', string $at = '2026-01-15 18:00:00'): void
{
    $factory = WorkplaceReviewFactory::new()->decidedAt($at);
    $factory = match ($state) {
        'not_valid' => $factory->notValid(),
        'plan' => $factory->withPlan(),
        default => $factory,
    };
    $factory->create(['tenant_id' => test()->tenant->id, 'workplace_id' => test()->workplace->id]);
}

function standing(): WorkplaceStatus
{
    return resolve(WorkplaceStanding::class)->of(test()->workplace->refresh());
}

function setPeriod(int $months): void
{
    resolve(App\Modules\Telework\Support\Settings::class)->record();
    TeleworkSettings::query()->update(['review_period_months' => $months]);
}

it('derives pending, valid, valid with plan, not valid and retired from the latest review', function (string $state, ?string $retired, WorkplaceStatus $expected): void {
    $this->travelTo('2026-03-01 12:00:00');

    if ($state !== 'none') {
        review($state);
    }

    if ($retired !== null) {
        $this->workplace->update(['retired_at' => $retired]);
    }

    expect(standing())->toBe($expected);
})->with([
    'no review' => ['none', null, WorkplaceStatus::Pending],
    'valid' => ['remote', null, WorkplaceStatus::Valid],
    'valid with plan' => ['plan', null, WorkplaceStatus::ValidWithPlan],
    'not valid' => ['not_valid', null, WorkplaceStatus::NotValid],
    'retired wins over a valid review' => ['remote', '2026-02-01 00:00:00', WorkplaceStatus::Retired],
    'retired with no review' => ['none', '2026-02-01 00:00:00', WorkplaceStatus::Retired],
]);

it('takes the latest review, not the first', function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    review('remote', '2026-01-15 18:00:00');
    review('not_valid', '2026-02-15 18:00:00');

    expect(standing())->toBe(WorkplaceStatus::NotValid)->and(resolve(WorkplaceStanding::class)->expiresOn($this->workplace))->toBeNull();

    review('plan', '2026-02-20 18:00:00');

    expect(standing())->toBe(WorkplaceStatus::ValidWithPlan);
});

it('expires exactly at the review date plus the current period', function (): void {
    setPeriod(12);
    review(at: '2026-01-15 18:00:00');

    $this->travelTo('2027-01-15 05:59:00', fn () => expect(standing())->toBe(WorkplaceStatus::Valid));
    $this->travelTo('2027-01-15 06:01:00', fn () => expect(standing())->toBe(WorkplaceStatus::Pending));
    expect(resolve(WorkplaceStanding::class)->expiresOn($this->workplace)?->toDateString())->toBe('2027-01-15');
});

it('applies a changed period at once to a review already made', function (): void {
    setPeriod(12);
    review(at: '2026-01-15 18:00:00');

    $this->travelTo('2026-07-14 12:00:00', fn () => expect(standing())->toBe(WorkplaceStatus::Valid));
    setPeriod(6);
    $this->travelTo('2026-07-14 12:00:00', fn () => expect(standing())->toBe(WorkplaceStatus::Valid));
    $this->travelTo('2026-07-15 12:00:00', fn () => expect(standing())->toBe(WorkplaceStatus::Pending));
    expect(resolve(WorkplaceStanding::class)->expiresOn($this->workplace)?->toDateString())->toBe('2026-07-15');
});

it('counts the review date in Mexico City, not in UTC', function (): void {
    setPeriod(12);
    // 03:00 UTC on the 15th is still the 14th at 21:00 in Mexico City.
    review(at: '2026-01-15 03:00:00');

    expect(resolve(WorkplaceStanding::class)->expiresOn($this->workplace)?->toDateString())->toBe('2027-01-14');

    // The 14th begins at 06:00 UTC: 05:30 UTC is still the 13th in Mexico City, 06:30 UTC is the 14th.
    $this->travelTo(CarbonImmutable::parse('2027-01-14 06:30:00', 'UTC'), fn () => expect(standing())->toBe(WorkplaceStatus::Pending));
    $this->travelTo(CarbonImmutable::parse('2027-01-14 05:30:00', 'UTC'), fn () => expect(standing())->toBe(WorkplaceStatus::Valid));
});

it('does not overflow a month end', function (): void {
    setPeriod(1);
    review(at: '2026-01-31 18:00:00');

    expect(resolve(WorkplaceStanding::class)->expiresOn($this->workplace)?->toDateString())->toBe('2026-02-28');
});

it('has no expiry without a valid review', function (): void {
    expect(resolve(WorkplaceStanding::class)->expiresOn($this->workplace))->toBeNull();
});

it('does not read the reviews of another company', function (): void {
    review();
    $other = Tenant::factory()->create();
    workIn($other);

    expect(resolve(WorkplaceStanding::class)->of(Workplace::query()->withoutGlobalScopes()->findOrFail($this->workplace->id)))->toBe(WorkplaceStatus::Pending);
});
