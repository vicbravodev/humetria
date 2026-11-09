<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use Carbon\CarbonImmutable;

/**
 * How far the company is from the first mark, for RH's «Puesta en marcha»: a person marks once their center has a zone
 * (and, when the policy asks for the QR on site, a screen that shows the code), they have a schedule and they linked their
 * phone, and the policy was reviewed. The registro is mandatory from 1 January 2027 (LFT art. 132-XXXIV). Only counts and
 * a few names: never a position, a device or an email. Six queries, whatever the number of centers or people.
 */
final readonly class SetupProgress
{
    /** The day the registro de jornada becomes mandatory. */
    public const string DEADLINE = '2027-01-01';

    /** What a card names of what is pending: the rest is a count. */
    private const int SAMPLE = 3;

    private const int LISTED = 5;

    public function __construct(private Roster $roster) {}

    /**
     * @param  array<string, string>  $names  the centers in use, by id, by name
     * @param  array<string, list<string>>  $byCenter  the active people of each center in use, by center id
     * @return array<string, mixed>
     */
    public function of(array $names, array $byCenter): array
    {
        $policy = CompanyPolicy::current();
        $zones = $this->smallestZones();
        $screens = $this->activeScreens();
        $people = array_merge([], ...array_values($byCenter));
        $scheduled = array_flip(AttendanceSchedule::query()->among('employee_id', $people)->get(['employee_id'])->map(fn (AttendanceSchedule $schedule): string => $schedule->employee_id)->all());
        $linked = array_keys(DevicePages::states($people), 'linked', true);

        $pending = [];
        $findings = [];
        foreach ($names as $id => $name) {
            $needs = array_values(array_filter([
                isset($zones[$id]) ? null : 'zone',
                $policy->require_qr_onsite && ! isset($screens[$id]) ? 'screen' : null,
            ]));

            if ($needs !== []) {
                $pending[] = ['id' => $id, 'name' => $name, 'needs' => $needs];
            }

            if (isset($zones[$id]) && $zones[$id]['radius'] < $policy->max_accuracy_m) {
                $findings[] = ['center' => ['id' => $id, 'name' => $name], 'zone' => $zones[$id]['name'], 'radius' => $zones[$id]['radius'], 'tolerance' => $policy->max_accuracy_m];
            }
        }

        $unscheduled = array_values(array_filter($people, fn (string $id): bool => ! isset($scheduled[$id])));

        return [
            'deadline' => self::DEADLINE,
            'daysLeft' => $this->daysLeft(),
            'requiresQr' => $policy->require_qr_onsite,
            'centers' => ['done' => count($names) - count($pending), 'total' => count($names), 'pending' => array_slice($pending, 0, self::LISTED)],
            'schedules' => ['done' => count($people) - count($unscheduled), 'total' => count($people), 'pending' => $this->names($unscheduled)],
            'devices' => ['done' => count($linked), 'total' => count($people)],
            'policy' => ['done' => $policy->exists ? 1 : 0, 'total' => 1],
            'findings' => array_slice($findings, 0, self::LISTED),
        ];
    }

    /** Whole days from today (in the company's time zone) to the deadline; none once it has come. */
    private function daysLeft(): int
    {
        $zone = config()->string('app.display_timezone');

        return max(0, (int) CarbonImmutable::now($zone)->startOfDay()->diffInDays(CarbonImmutable::parse(self::DEADLINE, $zone)));
    }

    /**
     * Each center's smallest zone, with its name, for the centers that have one.
     *
     * @return array<string, array{name: string, radius: int}>
     */
    private function smallestZones(): array
    {
        $smallest = [];
        $rows = AttendanceZone::query()->toBase()->selectRaw('distinct on (work_center_id) work_center_id, name, radius_m')
            ->orderBy('work_center_id')->orderBy('radius_m')->orderBy('name')->get();

        foreach ($rows as $row) {
            if (is_string($row->work_center_id) && is_string($row->name) && is_int($row->radius_m)) {
                $smallest[$row->work_center_id] = ['name' => $row->name, 'radius' => $row->radius_m];
            }
        }

        return $smallest;
    }

    /**
     * The centers with a screen that shows the code (seen at least once, not revoked), as the center page counts them.
     *
     * @return array<string, true>
     */
    private function activeScreens(): array
    {
        $screens = [];
        foreach (KioskDevice::query()->toBase()->whereNotNull('last_seen_at')->whereNull('revoked_at')->distinct()->pluck('work_center_id') as $center) {
            if (is_string($center)) {
                $screens[$center] = true;
            }
        }

        return $screens;
    }

    /**
     * A few of the people given, by name.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_values(array_map(fn (RosterEntry $person): string => $person->name, $this->roster->page(new RosterQuery(employeeIds: $ids), self::SAMPLE)->items()));
    }
}
