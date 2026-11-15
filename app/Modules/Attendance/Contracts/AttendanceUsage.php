<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

/**
 * Whether companies record working time with Attendance, for whoever restricts a company's access and must warn that its
 * people have to record the day some other way.
 */
interface AttendanceUsage
{
    /**
     * Which of the companies use Attendance: they have a policy, a kiosk, a linked phone or a mark. Works from anywhere, in
     * no company, and enters none.
     *
     * @param  list<string>  $tenantIds
     * @return list<string> the ids among them that do, in the order given.
     */
    public function among(array $tenantIds): array;
}
