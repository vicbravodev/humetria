<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Support\Pulse\PulsePlanner;
use Carbon\CarbonImmutable;

mutates(PulsePlanner::class, PulseFrequency::class);

it('knows how many months each frequency spans', function (): void {
    expect(PulseFrequency::Monthly->months())->toBe(1)
        ->and(PulseFrequency::Quarterly->months())->toBe(3)
        ->and(PulseFrequency::Monthly->label())->toBe('Mensual')
        ->and(PulseFrequency::Quarterly->label())->toBe('Trimestral');
});

it('rotates a monthly pulse through follow-up, tour and manager', function (): void {
    $modes = array_map(fn (int $position): PulseMode => PulsePlanner::modeFor(PulseFrequency::Monthly, $position, true), range(0, 5));

    expect($modes)->toBe([PulseMode::FollowUp, PulseMode::Tour, PulseMode::Manager, PulseMode::FollowUp, PulseMode::Tour, PulseMode::Manager]);
});

it('rotates a quarterly pulse through follow-up, tour, manager and tour', function (): void {
    $modes = array_map(fn (int $position): PulseMode => PulsePlanner::modeFor(PulseFrequency::Quarterly, $position, true), range(0, 4));

    expect($modes)->toBe([PulseMode::FollowUp, PulseMode::Tour, PulseMode::Manager, PulseMode::Tour, PulseMode::FollowUp]);
});

it('falls back to a tour wherever a follow-up would be due and there are no priorities', function (): void {
    expect(PulsePlanner::modeFor(PulseFrequency::Monthly, 0, false))->toBe(PulseMode::Tour)
        ->and(PulsePlanner::modeFor(PulseFrequency::Monthly, 3, false))->toBe(PulseMode::Tour)
        ->and(PulsePlanner::modeFor(PulseFrequency::Monthly, 2, false))->toBe(PulseMode::Manager)
        ->and(PulsePlanner::modeFor(PulseFrequency::Quarterly, 0, false))->toBe(PulseMode::Tour);
});

it('offers by hand what the company can use', function (): void {
    expect(PulsePlanner::available(true))->toBe([PulseMode::FollowUp, PulseMode::Tour, PulseMode::Manager])
        ->and(PulsePlanner::available(false))->toBe([PulseMode::Tour, PulseMode::Manager]);
});

it('finds the next monthly run strictly after the date', function (string $after, string $next): void {
    expect(PulsePlanner::nextRun(PulseFrequency::Monthly, 15, CarbonImmutable::parse($after))->toDateString())->toBe($next);
})->with([
    'the day itself' => ['2026-11-15', '2026-12-15'],
    'the day before' => ['2026-11-14', '2026-11-15'],
    'later in the month' => ['2026-11-20', '2026-12-15'],
    'crossing the year' => ['2026-12-16', '2027-01-15'],
]);

it('runs a quarterly pulse at the close of each calendar quarter, the first day chosen', function (): void {
    $first = PulsePlanner::nextRun(PulseFrequency::Quarterly, 1, CarbonImmutable::parse('2026-11-09'));
    $second = PulsePlanner::nextRun(PulseFrequency::Quarterly, 1, $first);

    expect($first->toDateString())->toBe('2026-12-01')
        ->and($second->toDateString())->toBe('2027-03-01')
        ->and(PulsePlanner::nextRun(PulseFrequency::Quarterly, 1, $second)->toDateString())->toBe('2027-06-01');
});

it('keeps the time of the run at the start of the day', function (): void {
    $next = PulsePlanner::nextRun(PulseFrequency::Monthly, 1, CarbonImmutable::parse('2026-11-09 18:30:00'));

    expect($next->format('Y-m-d H:i:s'))->toBe('2026-12-01 00:00:00');
});

it('refuses a day that does not exist in every month', function (int $day): void {
    PulsePlanner::nextRun(PulseFrequency::Monthly, $day, CarbonImmutable::parse('2026-11-09'));
})->with([0, 29, 31])->throws(InvalidArgumentException::class);

it('accepts the first and the last day of the allowed range', function (): void {
    expect(PulsePlanner::nextRun(PulseFrequency::Monthly, 1, CarbonImmutable::parse('2026-01-31'))->toDateString())->toBe('2026-02-01')
        ->and(PulsePlanner::nextRun(PulseFrequency::Monthly, 28, CarbonImmutable::parse('2026-02-10'))->toDateString())->toBe('2026-02-28');
});
