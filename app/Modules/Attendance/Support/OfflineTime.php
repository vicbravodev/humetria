<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\OfflineDecision;
use App\Modules\Attendance\Enums\OfflinePosition;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Enums\TimeBasis;
use Carbon\CarbonImmutable;

/**
 * The one rule of time of a mark made without a connection (criterio 3.3.1): the person declares the time and the server
 * clamps it between what it can prove. The lower bound is the latest of the ticket's issue, the start of the window of a
 * valid QR and a microsecond after the mark before; the upper one is the earliest of the receipt and a microsecond before the
 * mark after. A ticket older than 72 hours proves too little of the declared time: the lower bound counts instead. The
 * neighbours are read by whoever calls, inside the transaction that holds the person's lock.
 */
final class OfflineTime
{
    /**
     * The point around which the neighbouring marks are looked up: the declared time held between the ticket and the receipt.
     */
    public static function reference(CarbonImmutable $declaredAt, CarbonImmutable $ticketIssuedAt, CarbonImmutable $receivedAt): CarbonImmutable
    {
        return $declaredAt->max($ticketIssuedAt)->min($receivedAt);
    }

    /**
     * The time that counts, or null when the bounds do not leave one (the mark conflicts with its neighbours).
     */
    public static function decide(
        CarbonImmutable $declaredAt,
        CarbonImmutable $ticketIssuedAt,
        CarbonImmutable $receivedAt,
        ?CarbonImmutable $windowStart,
        ?CarbonImmutable $previous,
        ?CarbonImmutable $next,
        OfflinePosition $position,
    ): ?OfflineDecision {
        $lower = $ticketIssuedAt;

        foreach ([$windowStart, $previous?->addMicrosecond()] as $bound) {
            $lower = $bound === null ? $lower : $lower->max($bound);
        }

        $upper = $next === null ? $receivedAt : $receivedAt->min($next->subMicrosecond());

        if ($lower->greaterThan($upper)) {
            return null;
        }

        $trusted = $receivedAt->lessThanOrEqualTo($ticketIssuedAt->addHours(config()->integer('attendance.offline.count_declared_max_hours')));
        $countedAt = $trusted ? $declaredAt->max($lower)->min($upper) : $lower;
        $oldWindow = $windowStart !== null && $declaredAt->greaterThan($windowStart->addMinutes(config()->integer('attendance.offline.qr_old_window_minutes')));

        return new OfflineDecision(
            $countedAt,
            $windowStart !== null && ! $oldWindow ? TimeBasis::QrWindow : TimeBasis::DeclaredInterval,
            array_values(array_filter([
                $receivedAt->greaterThan($ticketIssuedAt->addMinutes(config()->integer('attendance.offline.wide_interval_minutes'))) ? ReviewFlag::WideInterval : null,
                $oldWindow ? ReviewFlag::OldWindow : null,
                $position === OfflinePosition::Unknown ? ReviewFlag::NoPosition : null,
                $position === OfflinePosition::Outside ? ReviewFlag::OutsideZone : null,
                $countedAt->notEqualTo($declaredAt) ? ReviewFlag::DeclaredAdjusted : null,
            ])),
        );
    }
}
