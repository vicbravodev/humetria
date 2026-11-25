<?php

declare(strict_types=1);

use App\Modules\Attendance\Data\OfflineDecision;
use App\Modules\Attendance\Enums\OfflinePosition;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Enums\TimeBasis;
use App\Modules\Attendance\Support\OfflineTime;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

mutates(OfflineTime::class);

/*
 * The one rule of time of a mark made without a connection (criterio 3.3.1): the declared time clamped between what the
 * server can prove, with the flags that tell Recursos Humanos how far to trust it.
 */
function offlineAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2026-11-09 {$time}", 'UTC');
}

/**
 * @param  array{window?: ?string, previous?: ?string, next?: ?string, position?: OfflinePosition}  $around
 */
function decideOffline(string $declared, string $issued, string $received, array $around = []): ?OfflineDecision
{
    return OfflineTime::decide(
        offlineAt($declared),
        offlineAt($issued),
        offlineAt($received),
        isset($around['window']) ? offlineAt($around['window']) : null,
        isset($around['previous']) ? offlineAt($around['previous']) : null,
        isset($around['next']) ? offlineAt($around['next']) : null,
        $around['position'] ?? OfflinePosition::Inside,
    );
}

/** @return list<string> */
function flagValues(?OfflineDecision $decision): array
{
    return array_map(fn (ReviewFlag $flag): string => $flag->value, $decision?->flags ?? []);
}

describe('the examples of the design', function (): void {
    it('counts the declared 17:00 of an exit delivered at 19:00 with the wide interval flag', function (): void {
        $decision = decideOffline('17:00:00', '08:00:00', '19:00:00', ['previous' => '08:00:00']);

        expect($decision?->countedAt->equalTo(offlineAt('17:00:00')))->toBeTrue()
            ->and($decision?->basis)->toBe(TimeBasis::DeclaredInterval)
            ->and(flagValues($decision))->toBe(['wide_interval']);
    });

    it('counts a time declared before the ticket just after the mark before it, as adjusted', function (): void {
        $decision = decideOffline('07:30:00', '08:00:00', '08:30:00', ['previous' => '08:00:00']);

        expect($decision?->countedAt->format('H:i:s.u'))->toBe('08:00:00.000001')
            ->and(flagValues($decision))->toBe(['declared_adjusted']);
    });

    it('counts the lower bound of a ticket older than 72 hours, flagged wide and adjusted', function (): void {
        $decision = OfflineTime::decide(offlineAt('12:00:00')->addDays(3), offlineAt('08:00:00'), offlineAt('08:00:00')->addHours(80), null, null, null, OfflinePosition::Inside);

        expect($decision?->countedAt->equalTo(offlineAt('08:00:00')))->toBeTrue()
            ->and(flagValues($decision))->toBe(['wide_interval', 'declared_adjusted']);
    });
});

describe('the reference', function (): void {
    it('is the ticket for a time declared before it, the receipt for one after it and the declared time between both', function (): void {
        expect(OfflineTime::reference(offlineAt('07:00:00'), offlineAt('08:00:00'), offlineAt('10:00:00'))->equalTo(offlineAt('08:00:00')))->toBeTrue()
            ->and(OfflineTime::reference(offlineAt('11:00:00'), offlineAt('08:00:00'), offlineAt('10:00:00'))->equalTo(offlineAt('10:00:00')))->toBeTrue()
            ->and(OfflineTime::reference(offlineAt('09:00:00'), offlineAt('08:00:00'), offlineAt('10:00:00'))->equalTo(offlineAt('09:00:00')))->toBeTrue();
    });
});

describe('the bounds', function (): void {
    it('counts the declared time when it falls inside them, with no flag', function (): void {
        $decision = decideOffline('08:30:00', '08:00:00', '09:00:00');

        expect($decision?->countedAt->equalTo(offlineAt('08:30:00')))->toBeTrue()->and(flagValues($decision))->toBe([]);
    });

    it('moves a time declared before the lower bound to it and one after the upper bound to it', function (): void {
        $before = decideOffline('07:00:00', '08:00:00', '09:00:00');
        $after = decideOffline('10:00:00', '08:00:00', '09:00:00');

        expect($before?->countedAt->equalTo(offlineAt('08:00:00')))->toBeTrue()
            ->and($after?->countedAt->equalTo(offlineAt('09:00:00')))->toBeTrue()
            ->and(flagValues($before))->toBe(['declared_adjusted'])
            ->and(flagValues($after))->toBe(['declared_adjusted']);
    });

    it('raises the lower bound to the start of the QR window', function (): void {
        $decision = decideOffline('08:10:00', '08:00:00', '09:00:00', ['window' => '08:20:00']);

        expect($decision?->countedAt->equalTo(offlineAt('08:20:00')))->toBeTrue();
    });

    it('counts a microsecond after the mark before and a microsecond before the one after', function (): void {
        $after = decideOffline('08:10:00', '08:00:00', '09:00:00', ['previous' => '08:30:00']);
        $before = decideOffline('08:50:00', '08:00:00', '09:00:00', ['next' => '08:40:00']);

        expect($after?->countedAt->format('H:i:s.u'))->toBe('08:30:00.000001')
            ->and($before?->countedAt->format('H:i:s.u'))->toBe('08:39:59.999999');
    });

    it('answers null when the lower bound passes the upper one and the hour itself when they meet', function (): void {
        $meet = OfflineTime::decide(offlineAt('08:30:00'), offlineAt('08:00:00'), offlineAt('09:00:00'), null, offlineAt('08:59:59.999999'), null, OfflinePosition::Inside);
        $afterReceipt = OfflineTime::decide(offlineAt('08:30:00'), offlineAt('08:00:00'), offlineAt('09:00:00'), null, offlineAt('09:00:00'), null, OfflinePosition::Inside);
        $between = OfflineTime::decide(offlineAt('08:30:00'), offlineAt('08:00:00'), offlineAt('09:00:00'), null, offlineAt('08:00:00'), offlineAt('08:00:00')->addMicrosecond(), OfflinePosition::Inside);

        expect($meet?->countedAt->format('H:i:s.u'))->toBe('09:00:00.000000')
            ->and($afterReceipt)->toBeNull()
            ->and($between)->toBeNull();
    });
});

describe('the limits', function (): void {
    it('flags an interval of 241 minutes and not one of 240', function (): void {
        expect(flagValues(decideOffline('08:30:00', '08:00:00', '12:00:00')))->toBe([])
            ->and(flagValues(decideOffline('08:30:00', '08:00:00', '12:00:01')))->toBe(['wide_interval']);
    });

    it('counts the declared time up to 72 hours and the lower bound past them', function (): void {
        $declared = offlineAt('12:00:00')->addDays(1);
        $at72 = OfflineTime::decide($declared, offlineAt('08:00:00'), offlineAt('08:00:00')->addHours(72), null, null, null, OfflinePosition::Inside);
        $past72 = OfflineTime::decide($declared, offlineAt('08:00:00'), offlineAt('08:00:00')->addHours(72)->addSecond(), null, null, null, OfflinePosition::Inside);

        expect($at72?->countedAt->equalTo($declared))->toBeTrue()
            ->and($past72?->countedAt->equalTo(offlineAt('08:00:00')))->toBeTrue();
    });

    it('counts an end and a break start of a wide interval at their declared time, bounded', function (): void {
        // The type of the mark does not enter the rule: the same declared time counts for any of them.
        $decision = decideOffline('17:00:00', '08:00:00', '19:00:00');

        expect($decision?->countedAt->equalTo(offlineAt('17:00:00')))->toBeTrue();
    });
});

describe('the age of the QR', function (): void {
    it('is a QR window when the declared time is 30 minutes after its start and a declared interval with an old window one second later', function (): void {
        $fresh = decideOffline('08:30:00', '08:00:00', '09:00:00', ['window' => '08:00:00']);
        $old = decideOffline('08:30:01', '08:00:00', '09:00:00', ['window' => '08:00:00']);

        expect($fresh?->basis)->toBe(TimeBasis::QrWindow)->and(flagValues($fresh))->toBe([])
            ->and($old?->basis)->toBe(TimeBasis::DeclaredInterval)->and(flagValues($old))->toBe(['old_window']);
    });

    it('still raises the lower bound with an old window', function (): void {
        $old = decideOffline('08:50:00', '08:00:00', '09:00:00', ['window' => '08:10:00']);
        $lower = decideOffline('08:05:00', '08:00:00', '09:00:00', ['window' => '08:10:00']);

        expect($old?->basis)->toBe(TimeBasis::DeclaredInterval)
            ->and($lower?->countedAt->equalTo(offlineAt('08:10:00')))->toBeTrue();
    });

    it('is a declared interval with no window', function (): void {
        expect(decideOffline('08:30:00', '08:00:00', '09:00:00')?->basis)->toBe(TimeBasis::DeclaredInterval);
    });
});

describe('the flags', function (): void {
    it('says the position is missing or outside the zone and says nothing inside it', function (): void {
        expect(flagValues(decideOffline('08:30:00', '08:00:00', '09:00:00', ['position' => OfflinePosition::Unknown])))->toBe(['no_position'])
            ->and(flagValues(decideOffline('08:30:00', '08:00:00', '09:00:00', ['position' => OfflinePosition::Outside])))->toBe(['outside_zone'])
            ->and(flagValues(decideOffline('08:30:00', '08:00:00', '09:00:00', ['position' => OfflinePosition::Inside])))->toBe([]);
    });

    it('says the center has no zones, when there is a position but nothing to be inside or outside of (decisión del dueño, 2026-11-24)', function (): void {
        expect(flagValues(decideOffline('08:30:00', '08:00:00', '09:00:00', ['position' => OfflinePosition::NoZones])))->toBe(['zones_not_configured']);
    });

    it('says the time was adjusted only when the counted time differs from the declared one', function (): void {
        expect(flagValues(decideOffline('08:30:00', '08:00:00', '09:00:00')))->not->toContain('declared_adjusted')
            ->and(flagValues(decideOffline('07:30:00', '08:00:00', '09:00:00')))->toContain('declared_adjusted');
    });

    it('lists the flags in the order of the enum', function (): void {
        $decision = decideOffline('08:30:01', '08:00:00', '13:00:00', ['window' => '08:00:00', 'position' => OfflinePosition::Unknown, 'previous' => '08:30:00']);
        $noZones = decideOffline('08:30:01', '08:00:00', '13:00:00', ['window' => '08:00:00', 'position' => OfflinePosition::NoZones, 'previous' => '08:30:00']);

        expect(flagValues($decision))->toBe(['wide_interval', 'old_window', 'no_position'])
            ->and(flagValues($noZones))->toBe(['wide_interval', 'old_window', 'zones_not_configured']);
    });
});
