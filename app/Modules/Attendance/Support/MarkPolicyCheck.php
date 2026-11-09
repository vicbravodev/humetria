<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\MarkContext;
use App\Modules\Attendance\Data\Position;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Exceptions\MarkRejected;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;

/**
 * How a mark is taken under the company's policy (criterios 4.2 to 4.4 and 5.3), or why not. Pure: the QR is verified and
 * the zones are read by whoever calls.
 *
 * - Remote work (a schedule in `remote`; no schedule is on site): `Remote`, no geofence, and no position is kept.
 * - Trusted staff, when the policy lets them use the browser, with no QR: `TrustedBrowser`, no position kept. With a QR
 *   (they are at the center) the on-site rules give stronger evidence.
 * - On site, in this order: a QR that does not verify is `qr_invalid`; no QR where it is required (or where the center has
 *   no zones, as then nothing else proves where) is `qr_required`; a center without zones marks with the QR alone. With
 *   zones: no location is `no_location` (unless the policy allows out of zone and the QR is valid: `Qr`, no zone); under
 *   `Block` a reading less accurate than the policy's maximum is `accuracy_too_low` (checked first: an inaccurate reading
 *   cannot tell inside from out) and one outside every zone is `outside_zone`; under `AllowAndFlag` either is taken with
 *   no zone (flagged). Inside and accurate is `QrAndGeofence`, or `Geofence` without a QR.
 */
final class MarkPolicyCheck
{
    /**
     * @param  ?bool  $qrValid  null when no QR was scanned
     * @param  array<array-key, AttendanceZone>  $zones  the zones of the person's current work center
     * @param  ?Position  $position  null when the device gave no location
     *
     * @throws MarkRejected
     */
    public static function decide(?AttendanceSchedule $schedule, AttendancePolicy $policy, bool $trusted, ?bool $qrValid, array $zones, ?Position $position, string $deviceId): MarkContext
    {
        if ($schedule?->work_mode === WorkMode::Remote) {
            return new MarkContext(MarkMethod::Remote, null, null, null, null, null, $deviceId);
        }

        if ($trusted && $policy->trusted_may_use_browser && $qrValid === null) {
            return new MarkContext(MarkMethod::TrustedBrowser, null, null, null, null, null, $deviceId);
        }

        if ($qrValid === false) {
            throw new MarkRejected('qr_invalid');
        }

        if ($qrValid === null && ($policy->require_qr_onsite || $zones === [])) {
            throw new MarkRejected('qr_required');
        }

        $allowOut = $policy->out_of_zone === OutOfZoneRule::AllowAndFlag;

        if ($position === null) {
            if ($zones !== [] && ! ($allowOut && $qrValid)) {
                throw new MarkRejected('no_location');
            }

            return new MarkContext(MarkMethod::Qr, null, null, null, null, null, $deviceId);
        }

        if ($zones === []) {
            return new MarkContext(MarkMethod::Qr, null, $position->latitude, $position->longitude, $position->accuracyM, null, $deviceId);
        }

        $reading = Geofence::evaluate($zones, $position->latitude, $position->longitude, $position->accuracyM, $policy->max_accuracy_m);
        $inZone = $reading->inside && $reading->accuracyOk;

        if (! $inZone && ! $allowOut) {
            throw new MarkRejected($reading->accuracyOk ? 'outside_zone' : 'accuracy_too_low');
        }

        $method = match (true) {
            $inZone && $qrValid === true => MarkMethod::QrAndGeofence,
            $qrValid === true => MarkMethod::Qr,
            default => MarkMethod::Geofence,
        };

        return new MarkContext($method, $inZone ? $reading->zoneId : null, $position->latitude, $position->longitude, $position->accuracyM, $reading->distanceM, $deviceId);
    }
}
