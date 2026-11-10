<?php

declare(strict_types=1);

use App\Modules\Attendance\Data\Position;
use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Exceptions\MarkRejected;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Support\MarkPolicyCheck;

/** Meters per degree of latitude on the Geofence's sphere. */
const MOF_METERS_PER_DEGREE = 6371000 * M_PI / 180;

/**
 * The policy page of RH shows «Así queda una marca» (resources/js/lib/attendance/policy-preview.ts) from the same table
 * this test checks against MarkPolicyCheck: tests/Fixtures/attendance/mark-outcomes.json. If the rules change, this fails
 * and the table, and with it the preview's test, must change too.
 */
dataset('mark outcomes of each policy', fn () => json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/attendance/mark-outcomes.json'), true, flags: JSON_THROW_ON_ERROR));

/** What the center's one zone of 50 m sees, for each case of the table: [trusted, QR valid, meters north of its center, accuracy]. */
const MOF_CASES = [
    'qr_and_inside' => [false, true, 10, 10.0],
    'inside_no_qr' => [false, null, 10, 10.0],
    'qr_inaccurate' => [false, true, 10, 500.0],
    'qr_outside' => [false, true, 5000, 10.0],
    'trusted_browser' => [true, null, 10, 10.0],
];

it('marks every case of the table as the policy page says', function (array $policy, array $outcomes): void {
    $check = (new AttendancePolicy)->forceFill([
        'out_of_zone' => OutOfZoneRule::from($policy['outOfZone']), 'max_accuracy_m' => $policy['maxAccuracyM'],
        'require_qr_onsite' => $policy['requireQrOnsite'], 'trusted_may_use_browser' => $policy['trustedMayUseBrowser'],
    ]);
    $zone = (new AttendanceZone)->forceFill(['id' => 'zone-a', 'latitude' => 19.0, 'longitude' => -99.0, 'radius_m' => 50]);

    foreach (MOF_CASES as $name => [$trusted, $qrValid, $meters, $accuracy]) {
        $position = new Position(19.0 + $meters / MOF_METERS_PER_DEGREE, -99.0, $accuracy);

        try {
            $outcome = MarkPolicyCheck::decide(null, $check, $trusted, $qrValid, [$zone], $position, 'device-1')->method->evidence()->value;
        } catch (MarkRejected) {
            $outcome = 'rejected';
        }

        expect($outcome)->toBe($outcomes[$name], $name.' under '.json_encode($policy));
    }
})->with('mark outcomes of each policy');
