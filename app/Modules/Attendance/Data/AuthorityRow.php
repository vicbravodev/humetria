<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/**
 * One day of one person in the report for the authority (criterio 6.1): local dates and times, the minutes as criterio 2.9
 * splits them, the weakest evidence of its marks, its corrections with their reason and the answer to its week. Never
 * where a mark was taken, nor the device.
 */
final readonly class AuthorityRow
{
    /**
     * @param  string  $localDate  the local date of the day's start, Y-m-d
     * @param  string|null  $start  local H:i of its start; null when its first mark is not one
     * @param  string|null  $end  local H:i of its end; null when the day has none
     * @param  string|null  $endDate  the local date of its end (a night ends the next day), Y-m-d
     * @param  string  $evidence  Fuerte, Media, Básica or Corregida
     * @param  list<string>  $corrections  «hora corregida: <motivo>» or «marca agregada: <motivo>», one per correction
     * @param  string  $confirmation  confirmado, objetada or sin confirmar
     * @param  string  $weekDigest  the version of the week the person confirmed; empty unless confirmed
     * @param  list<string>  $flags  what an inspector should notice, in plain Spanish
     */
    public function __construct(
        public string $employeeId,
        public string $personName,
        public string $jobTitle,
        public string $localDate,
        public ?string $start,
        public ?string $end,
        public ?string $endDate,
        public int $breakMinutes,
        public int $workedMinutes,
        public int $ordinaryMinutes,
        public int $doubleMinutes,
        public int $tripleMinutes,
        public int $restDayMinutes,
        public string $evidence,
        public array $corrections,
        public string $confirmation,
        public string $weekDigest,
        public array $flags,
    ) {}
}
