<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OfflineRejection;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceOfflineMark;
use App\Modules\Organization\Models\Employee;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\AttendanceOfflineMarkFactory;

/**
 * The deliveries of marks made without a connection that the tests of Recursos Humanos' screens need, with the times written
 * as local time of Mexico City (the zone the factories mark in): the ticket was issued an hour before the declared time and
 * the delivery came an hour after it, unless the call says when.
 */
final class OfflineDeliveries
{
    /**
     * An accepted delivery of `$person` with the mark it created (declared as its time, unless `$mark` is given).
     *
     * @param  list<ReviewFlag>  $flags
     * @param  array<string, mixed>  $with  other attributes of the delivery (the kind of key that signed it, for one)
     */
    public static function accepted(Employee $person, string $declared, MarkType $type = MarkType::Start, bool $qr = false, array $flags = [], ?CarbonImmutable $receivedAt = null, ?AttendanceMark $mark = null, ?string $justification = null, array $with = []): AttendanceOfflineMark
    {
        $at = CarbonImmutable::parse($declared, 'America/Mexico_City')->utc();
        $factory = AttendanceOfflineMarkFactory::new()->accepted($mark)->flagged(...$flags);

        // The rule may have counted a time other than the declared one: the ticket then goes back to before both.
        $counted = $mark?->marked_at;

        return ($qr ? $factory->qrWindow() : $factory)->createOne([
            ...self::of($person, $counted === null ? $at : $at->min($counted)),
            'declared_at' => $at,
            ...($counted === null ? [] : ['counted_at' => $counted]),
            'mark_type' => $type,
            'justification' => $justification,
            'received_at' => $receivedAt ?? $at->addHour(),
            ...($qr ? ['window_start' => $at->subMinutes(5)] : []),
            ...$with,
        ]);
    }

    /** A delivery that was kept as rejected, `$declared` local. */
    public static function rejected(Employee $person, string $declared, OfflineRejection $reason, ?CarbonImmutable $receivedAt = null): AttendanceOfflineMark
    {
        $at = CarbonImmutable::parse($declared, 'America/Mexico_City')->utc();

        return AttendanceOfflineMarkFactory::new()->rejected($reason)->createOne([
            ...self::of($person, $at),
            'received_at' => $receivedAt ?? $at->addHour(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function of(Employee $person, CarbonImmutable $at): array
    {
        return [
            'tenant_id' => $person->tenant_id,
            'employee_id' => $person->id,
            'work_center_id' => $person->work_center_id,
            'declared_at' => $at,
            'ticket_issued_at' => $at->subHour(),
        ];
    }
}
