<?php

declare(strict_types=1);

use App\Modules\Attendance\Data\MarkContext;
use App\Modules\Attendance\Data\Position;
use App\Modules\Attendance\Enums\EvidenceLevel;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Exceptions\MarkRejected;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Support\MarkPolicyCheck;

mutates(MarkPolicyCheck::class);

/** Meters per degree of latitude on the Geofence's sphere. */
const MPC_METERS_PER_DEGREE = 6371000 * M_PI / 180;

/** The default policy, or one changed. */
function markPolicy(OutOfZoneRule $outOfZone = OutOfZoneRule::Block, bool $requireQr = true, bool $trustedBrowser = true, bool $requireQrBreaks = false): AttendancePolicy
{
    return (new AttendancePolicy)->forceFill(['out_of_zone' => $outOfZone, 'max_accuracy_m' => 100, 'require_qr_onsite' => $requireQr, 'require_qr_breaks' => $requireQrBreaks, 'trusted_may_use_browser' => $trustedBrowser]);
}

function markSchedule(WorkMode $mode): AttendanceSchedule
{
    return (new AttendanceSchedule)->forceFill(['work_mode' => $mode]);
}

/** A position `$meters` north of the center of the plant's 50 m zone. */
function northOf(float $meters, float $accuracy = 10.0): Position
{
    return new Position(19.0 + $meters / MPC_METERS_PER_DEGREE, -99.0, $accuracy);
}

/**
 * The decision for a person on site with no schedule, a valid QR and a good reading 10 m inside the plant's zone, unless
 * told otherwise.
 *
 * @param  ?list<AttendanceZone>  $zones  null for the plant's one zone
 */
function decideMark(?AttendanceSchedule $schedule = null, ?AttendancePolicy $policy = null, bool $trusted = false, ?bool $qrValid = true, ?array $zones = null, ?Position $position = new Position(19.0 + 10 / MPC_METERS_PER_DEGREE, -99.0, 10.0), int $screens = 1, bool $field = false, MarkType $type = MarkType::Start): MarkContext
{
    $plant = [(new AttendanceZone)->forceFill(['id' => 'zone-a', 'latitude' => 19.0, 'longitude' => -99.0, 'radius_m' => 50])];

    return MarkPolicyCheck::decide($schedule, $policy ?? markPolicy(), $trusted, $qrValid, $zones ?? $plant, $position, 'device-1', $screens, $field, $type);
}

/**
 * The reason `$attempt` was rejected with.
 *
 * @param  Closure(): MarkContext  $attempt
 */
function markRejection(Closure $attempt): string
{
    try {
        $attempt();
    } catch (MarkRejected $rejected) {
        return $rejected->reason;
    }

    throw new LogicException('The mark was not rejected.');
}

describe('on site', function (): void {
    it('is QR and geofence, the strong method, with the zone, the position and the distance', function (): void {
        $context = decideMark();

        expect($context->method)->toBe(MarkMethod::QrAndGeofence)
            ->and($context->zoneId)->toBe('zone-a')
            ->and($context->credentialId)->toBe('device-1')
            ->and($context->latitude)->toBe(19.0 + 10 / MPC_METERS_PER_DEGREE)
            ->and($context->longitude)->toBe(-99.0)
            ->and($context->accuracy)->toBe(10.0)
            ->and($context->distance)->toEqualWithDelta(10.0, 0.01);
    });

    it('treats a person with no schedule, or an on-site one, as on site', function (): void {
        expect(markRejection(fn () => decideMark(qrValid: null)))->toBe('qr_required')
            ->and(markRejection(fn () => decideMark(schedule: markSchedule(WorkMode::Onsite), qrValid: null)))->toBe('qr_required');
    });

    it('rejects a QR that does not verify, whatever else', function (): void {
        expect(markRejection(fn () => decideMark(qrValid: false)))->toBe('qr_invalid')
            ->and(markRejection(fn () => decideMark(qrValid: false, policy: markPolicy(OutOfZoneRule::AllowAndFlag, requireQr: false))))->toBe('qr_invalid')
            ->and(markRejection(fn () => decideMark(qrValid: false, zones: [])))->toBe('qr_invalid');
    });

    it('asks for the QR when the policy requires it', function (): void {
        expect(markRejection(fn () => decideMark(qrValid: null)))->toBe('qr_required');
    });

    it('is geofence only without a QR when the policy does not require it', function (): void {
        $context = decideMark(qrValid: null, policy: markPolicy(requireQr: false));

        expect($context->method)->toBe(MarkMethod::Geofence)->and($context->zoneId)->toBe('zone-a');
    });

    it('asks for the location when there are zones and none was given', function (): void {
        expect(markRejection(fn () => decideMark(position: null)))->toBe('no_location')
            ->and(markRejection(fn () => decideMark(position: null, qrValid: null, policy: markPolicy(OutOfZoneRule::AllowAndFlag, requireQr: false))))->toBe('no_location');
    });

    it('records the QR alone, with no zone and no position, when the location is missing and the policy allows out of zone', function (): void {
        expect(decideMark(position: null, policy: markPolicy(OutOfZoneRule::AllowAndFlag)))
            ->toEqual(new MarkContext(MarkMethod::Qr, null, null, null, null, null, 'device-1'));
    });

    it('blocks a mark outside every zone by default', function (): void {
        expect(markRejection(fn () => decideMark(position: northOf(50.5))))->toBe('outside_zone');
    });

    it('is inside at 49.9 m of a 50 m zone', function (): void {
        expect(decideMark(position: northOf(49.9))->zoneId)->toBe('zone-a');
    });

    it('rejects a reading less accurate than the policy allows, before telling whether it is inside', function (): void {
        expect(markRejection(fn () => decideMark(position: northOf(10, 101))))->toBe('accuracy_too_low')
            ->and(markRejection(fn () => decideMark(position: northOf(500, 101))))->toBe('accuracy_too_low')
            ->and(decideMark(position: northOf(10, 100))->method)->toBe(MarkMethod::QrAndGeofence);
    });

    it('records out of zone with no zone, keeping the position, when the policy allows it', function (): void {
        $context = decideMark(position: northOf(80), policy: markPolicy(OutOfZoneRule::AllowAndFlag));

        expect($context->method)->toBe(MarkMethod::Qr)
            ->and($context->zoneId)->toBeNull()
            ->and($context->accuracy)->toBe(10.0)
            ->and($context->latitude)->toBe(19.0 + 80 / MPC_METERS_PER_DEGREE)
            ->and($context->distance)->toEqualWithDelta(80.0, 0.01);
    });

    it('records a bad reading inside the zone as out of zone when the policy allows it', function (): void {
        $context = decideMark(position: northOf(10, 500), policy: markPolicy(OutOfZoneRule::AllowAndFlag));

        expect($context->method)->toBe(MarkMethod::Qr)->and($context->zoneId)->toBeNull();
    });

    it('records the position alone, out of zone and with no QR, as a flagged geofence mark when the policy allows both', function (): void {
        $context = decideMark(qrValid: null, position: northOf(80), policy: markPolicy(OutOfZoneRule::AllowAndFlag, requireQr: false));

        expect($context->method)->toBe(MarkMethod::Geofence)->and($context->zoneId)->toBeNull();
    });

    describe('in a work center without zones', function (): void {
        it('marks with the QR alone, keeping the position given, with no zone nor distance', function (): void {
            $position = northOf(10);

            expect(decideMark(zones: [], position: $position))
                ->toEqual(new MarkContext(MarkMethod::Qr, null, $position->latitude, -99.0, 10.0, null, 'device-1'));
        });

        it('needs no location', function (): void {
            expect(decideMark(zones: [], position: null))->toEqual(new MarkContext(MarkMethod::Qr, null, null, null, null, null, 'device-1'));
        });

        it('ignores the accuracy, as there is no geofence to trust it for', function (): void {
            expect(decideMark(zones: [], position: northOf(10, 900))->method)->toBe(MarkMethod::Qr);
        });

        it('says the center is not ready, not «scan the screen», when it has no zone and no active screen either', function (): void {
            expect(markRejection(fn () => decideMark(zones: [], qrValid: null, screens: 0)))->toBe('center_not_ready')
                ->and(markRejection(fn () => decideMark(zones: [], qrValid: null, screens: 0, policy: markPolicy(requireQr: false))))->toBe('center_not_ready');
        });

        it('still asks for the QR when there is a screen, even with no zone', function (): void {
            expect(markRejection(fn () => decideMark(zones: [], qrValid: null, screens: 1)))->toBe('qr_required');
        });

        it('still needs the QR, even when the policy does not require it, as nothing else proves where', function (): void {
            expect(markRejection(fn () => decideMark(zones: [], qrValid: null, policy: markPolicy(requireQr: false))))->toBe('qr_required');
        });
    });
});

it('keeps asking for the QR of a center with a zone and no screen when the policy requires it, and for a bad code first', function (): void {
    expect(markRejection(fn () => decideMark(qrValid: null, screens: 0)))->toBe('qr_required')
        ->and(markRejection(fn () => decideMark(zones: [], qrValid: false, screens: 0)))->toBe('qr_invalid');
});

describe('remote', function (): void {
    it('is remote, with no geofence and no position kept', function (): void {
        expect(decideMark(schedule: markSchedule(WorkMode::Remote), qrValid: null, position: northOf(5000)))
            ->toEqual(new MarkContext(MarkMethod::Remote, null, null, null, null, null, 'device-1'));
    });

    it('needs no location nor QR, and ignores a QR given', function (): void {
        expect(decideMark(schedule: markSchedule(WorkMode::Remote), position: null, qrValid: null)->method)->toBe(MarkMethod::Remote)
            ->and(decideMark(schedule: markSchedule(WorkMode::Remote), qrValid: false)->method)->toBe(MarkMethod::Remote);
    });
});

describe('trusted staff', function (): void {
    it('marks from the browser with no QR when the policy allows it, keeping no position', function (): void {
        expect(decideMark(trusted: true, qrValid: null, position: northOf(5000)))
            ->toEqual(new MarkContext(MarkMethod::TrustedBrowser, null, null, null, null, null, 'device-1'));
    });

    it('follows the on-site rules when the policy does not allow the browser', function (): void {
        expect(markRejection(fn () => decideMark(trusted: true, qrValid: null, policy: markPolicy(trustedBrowser: false))))->toBe('qr_required');
    });

    it('follows the on-site rules when they scanned a QR (stronger evidence)', function (): void {
        expect(decideMark(trusted: true)->method)->toBe(MarkMethod::QrAndGeofence)
            ->and(markRejection(fn () => decideMark(trusted: true, position: northOf(500))))->toBe('outside_zone')
            ->and(markRejection(fn () => decideMark(trusted: true, qrValid: false)))->toBe('qr_invalid');
    });

    it('is remote when they work remotely', function (): void {
        expect(decideMark(trusted: true, schedule: markSchedule(WorkMode::Remote), qrValid: null)->method)->toBe(MarkMethod::Remote);
    });

    it('is on site when they are not trusted, even with the browser allowed', function (): void {
        expect(markRejection(fn () => decideMark(trusted: false, qrValid: null)))->toBe('qr_required');
    });
});

describe('in a field center', function (): void {
    it('is field, the strong method of its mode, keeping the position given anywhere, with no zone nor distance', function (): void {
        $context = decideMark(qrValid: null, position: northOf(5000), field: true);

        expect($context->method)->toBe(MarkMethod::Field)
            ->and($context->method->evidence())->toBe(EvidenceLevel::Strong)
            ->and($context->zoneId)->toBeNull()
            ->and($context->distance)->toBeNull()
            ->and($context->latitude)->toBe(19.0 + 5000 / MPC_METERS_PER_DEGREE)
            ->and($context->longitude)->toBe(-99.0)
            ->and($context->accuracy)->toBe(10.0)
            ->and($context->credentialId)->toBe('device-1');
    });

    it('needs neither a zone nor a screen: never center_not_ready nor qr_required', function (): void {
        expect(decideMark(qrValid: null, zones: [], position: northOf(10), screens: 0, field: true)->method)->toBe(MarkMethod::Field);
    });

    it('is field at the maximum accuracy, and field without a usable location (medium) past it, keeping the position', function (): void {
        expect(decideMark(qrValid: null, position: northOf(10, 100.0), field: true)->method)->toBe(MarkMethod::Field);

        $context = decideMark(qrValid: null, position: northOf(10, 100.1), field: true);

        expect($context->method)->toBe(MarkMethod::FieldUnlocated)
            ->and($context->method->evidence())->toBe(EvidenceLevel::Medium)
            ->and($context->accuracy)->toBe(100.1)
            ->and($context->zoneId)->toBeNull();
    });

    it('takes a mark with no location, as field without a usable location', function (): void {
        $context = decideMark(qrValid: null, position: null, field: true);

        expect($context->method)->toBe(MarkMethod::FieldUnlocated)
            ->and($context->latitude)->toBeNull();
    });

    it('takes a valid QR, optional: with a good reading it is field, without one it is the QR alone', function (): void {
        expect(decideMark(qrValid: true, position: northOf(10), field: true)->method)->toBe(MarkMethod::Field)
            ->and(decideMark(qrValid: true, position: null, field: true)->method)->toBe(MarkMethod::Qr);
    });

    it('still rejects a QR that does not verify', function (): void {
        expect(markRejection(fn () => decideMark(qrValid: false, field: true)))->toBe('qr_invalid');
    });

    it('is remote for a person whose schedule is remote, the exception that stays per person', function (): void {
        expect(decideMark(markSchedule(WorkMode::Remote), field: true)->method)->toBe(MarkMethod::Remote);
    });

    it('records trusted staff by their position too, not as the browser with nothing kept', function (): void {
        expect(decideMark(trusted: true, qrValid: null, position: northOf(10), field: true)->method)->toBe(MarkMethod::Field);
    });
});

describe('a code of another center of the company (a trip; design of time zones §3.9)', function (): void {
    it('is taken where the policy allows and flags, with that center and no zone, keeping the position', function (): void {
        $context = MarkPolicyCheck::away(null, markPolicy(OutOfZoneRule::AllowAndFlag), 'center-b', northOf(5000), 'device-1');

        expect($context->method)->toBe(MarkMethod::Qr)
            ->and($context->awayCenterId)->toBe('center-b')
            ->and($context->zoneId)->toBeNull()
            ->and($context->accuracy)->toBe(10.0)
            ->and($context->credentialId)->toBe('device-1');
    });

    it('is refused where the policy blocks', function (): void {
        expect(markRejection(fn () => MarkPolicyCheck::away(null, markPolicy(), 'center-b', null, 'device-1')))->toBe('qr_other_center');
    });

    it('does not matter to someone working remotely', function (): void {
        $context = MarkPolicyCheck::away(markSchedule(WorkMode::Remote), markPolicy(), 'center-b', northOf(10), 'device-1');

        expect($context->method)->toBe(MarkMethod::Remote)->and($context->awayCenterId)->toBeNull()->and($context->latitude)->toBeNull();
    });
});

describe('the QR by type of mark (criterios 4.2.1)', function (): void {
    it('asks for the QR on the start and the end, and not on a break, by default', function (): void {
        expect(markRejection(fn () => decideMark(qrValid: null, type: MarkType::Start)))->toBe('qr_required')
            ->and(markRejection(fn () => decideMark(qrValid: null, type: MarkType::End)))->toBe('qr_required')
            ->and(decideMark(qrValid: null, type: MarkType::BreakStart)->method)->toBe(MarkMethod::Geofence)
            ->and(decideMark(qrValid: null, type: MarkType::BreakEnd)->method)->toBe(MarkMethod::Geofence);
    });

    it('takes a break by geofence with its zone, position and distance', function (): void {
        $context = decideMark(qrValid: null, type: MarkType::BreakStart);

        expect($context->zoneId)->toBe('zone-a')
            ->and($context->latitude)->toBe(19.0 + 10 / MPC_METERS_PER_DEGREE)
            ->and($context->distance)->toEqualWithDelta(10.0, 0.01);
    });

    it('asks for the QR on a break when the policy requires it there', function (): void {
        $policy = markPolicy(requireQrBreaks: true);

        expect(markRejection(fn () => decideMark(qrValid: null, policy: $policy, type: MarkType::BreakStart)))->toBe('qr_required')
            ->and(markRejection(fn () => decideMark(qrValid: null, policy: $policy, type: MarkType::BreakEnd)))->toBe('qr_required');
    });

    it('lets a break take the QR when it is optional: scanned, it is the strong method', function (): void {
        expect(decideMark(type: MarkType::BreakStart)->method)->toBe(MarkMethod::QrAndGeofence);
    });

    it('rejects a QR that does not verify on a break, even when the QR is optional there', function (): void {
        expect(markRejection(fn () => decideMark(qrValid: false, type: MarkType::BreakStart)))->toBe('qr_invalid');
    });

    it('does not ask for the QR on the start and the end when the policy does not require it there', function (): void {
        $policy = markPolicy(requireQr: false, requireQrBreaks: true);

        expect(decideMark(qrValid: null, policy: $policy, type: MarkType::Start)->method)->toBe(MarkMethod::Geofence)
            ->and(decideMark(qrValid: null, policy: $policy, type: MarkType::End)->method)->toBe(MarkMethod::Geofence)
            ->and(markRejection(fn () => decideMark(qrValid: null, policy: $policy, type: MarkType::BreakStart)))->toBe('qr_required');
    });

    it('still needs the QR on a break in a center with no zones, as nothing else proves where', function (): void {
        expect(markRejection(fn () => decideMark(zones: [], qrValid: null, type: MarkType::BreakStart)))->toBe('qr_required')
            ->and(markRejection(fn () => decideMark(zones: [], qrValid: null, screens: 0, type: MarkType::BreakStart)))->toBe('center_not_ready');
    });

    it('still asks for the location on a break taken without the QR', function (): void {
        expect(markRejection(fn () => decideMark(position: null, qrValid: null, type: MarkType::BreakStart)))->toBe('no_location');
    });

    it('changes nothing for remote work, trusted staff or a field center', function (): void {
        expect(decideMark(schedule: markSchedule(WorkMode::Remote), qrValid: null, type: MarkType::BreakStart)->method)->toBe(MarkMethod::Remote)
            ->and(decideMark(trusted: true, qrValid: null, type: MarkType::BreakStart)->method)->toBe(MarkMethod::TrustedBrowser)
            ->and(decideMark(qrValid: null, field: true, type: MarkType::BreakStart)->method)->toBe(MarkMethod::Field);
    });
});
