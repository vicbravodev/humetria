<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * What RH's page of linked devices sends to the browser: the people active in the centers in use (or in one), how many
 * have a device, never had one or only revoked ones; then the people of one tab, 25 at a time, searched by name, each with
 * their devices (the active first): their name, when linked and last used, and whether the key is synced. Never a
 * credential id, a public key or the person's email (only whether they have one: the link goes there).
 */
final readonly class DevicePages
{
    private const int PER_PAGE = 25;

    /** linked: an active device; revoked: only revoked ones; unlinked: none ever. */
    private const array VIEWS = ['all', 'linked', 'unlinked', 'revoked'];

    public function __construct(private Roster $roster, private WorkCenters $workCenters) {}

    /**
     * @return array<string, mixed>
     */
    public function index(?WorkCenterSummary $center, string $view, string $search): array
    {
        $scope = PanelScope::of($this->workCenters, $this->roster, $center);
        $states = self::states($scope->people);
        $view = in_array($view, self::VIEWS, true) ? $view : 'all';
        $page = $this->roster->page(new RosterQuery(
            employeeIds: $view === 'all' ? $scope->people : array_keys($states, $view, true),
            search: $search,
        ), self::PER_PAGE)->withQueryString();
        $devices = $this->devicesOf(array_values(array_map(fn (RosterEntry $person): string => $person->id, $page->items())));
        $links = $this->linksOf($scope->people);

        return [
            'center' => $center === null ? null : ['id' => $center->id, 'name' => $center->name],
            'filters' => ['q' => $search, 'status' => $view],
            'counts' => [
                'all' => count($states),
                ...array_combine(array_slice(self::VIEWS, 1), array_map(fn (string $state): int => count(array_keys($states, $state, true)), array_slice(self::VIEWS, 1))),
            ],
            'pipeline' => $this->pipeline($states, $links),
            'people' => $page->through(fn (RosterEntry $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'jobTitle' => $person->jobTitle,
                'center' => $scope->names[$person->workCenterId] ?? '—',
                'hasEmail' => $person->email !== null,
                'status' => $states[$person->id] ?? 'unlinked',
                'link' => $links[$person->id] ?? null,
                'devices' => $devices[$person->id] ?? [],
            ]),
            'canManage' => Gate::allows('manage-attendance'),
            'linkHours' => config()->integer('attendance.enrollment.link_hours'),
        ];
    }

    /**
     * The link each person has waiting, unused and not replaced: when it was sent, and whether it still opens (`sent`, with the
     * hours it has left) or expired. One query.
     *
     * @param  list<string>  $people
     * @return array<string, array{state: string, sentAt: string, hoursLeft: int|null}>
     */
    private function linksOf(array $people): array
    {
        $zone = config()->string('app.display_timezone');
        $now = CarbonImmutable::now();
        $links = [];

        $rows = EnrollmentLink::query()->among('employee_id', $people)->whereNull('used_at')->whereNull('revoked_at')
            ->get(['employee_id', 'created_at', 'expires_at']);

        foreach ($rows as $link) {
            $open = $link->expires_at->greaterThan($now);
            $links[$link->employee_id] = [
                'state' => $open ? 'sent' : 'expired',
                'sentAt' => $link->created_at->setTimezone($zone)->format('Y-m-d H:i'),
                'hoursLeft' => $open ? (int) $now->diffInHours($link->expires_at) : null,
            ];
        }

        return $links;
    }

    /**
     * The road to the phone over everyone in scope: with a device, with a link still open, and the rest (no link, an expired
     * one, or only revoked devices). Each stage: how many, and the names of the first three.
     *
     * @param  array<string, string>  $states
     * @param  array<string, array{state: string, sentAt: string, hoursLeft: int|null}>  $links
     * @return array<string, array{count: int, names: list<string>}>
     */
    private function pipeline(array $states, array $links): array
    {
        $stages = ['none' => [], 'sent' => [], 'linked' => []];
        foreach ($states as $id => $state) {
            $stages[match (true) {
                $state === 'linked' => 'linked',
                ($links[$id]['state'] ?? null) === 'sent' => 'sent',
                default => 'none',
            }][] = $id;
        }

        $people = $this->roster->findMany(array_merge(...array_map(fn (array $ids): array => array_slice($ids, 0, 3), array_values($stages))));

        return array_map(function (array $ids) use ($people): array {
            $names = array_map(fn (string $id): string => $people[$id]->name ?? '—', array_slice($ids, 0, 3));
            sort($names);

            return ['count' => count($ids), 'names' => $names];
        }, $stages);
    }

    /**
     * Where each person stands: «linked» with an active device, «revoked» with only revoked ones, «unlinked» with none. One
     * query.
     *
     * @param  list<string>  $people
     * @return array<string, string>
     */
    public static function states(array $people): array
    {
        $active = AttendanceDevice::query()->among('employee_id', $people)->toBase()
            ->selectRaw('employee_id, bool_or(revoked_at is null) as active')->groupBy('employee_id')
            ->pluck('active', 'employee_id')->all();
        $states = [];
        foreach ($people as $id) {
            $states[$id] = match ($active[$id] ?? null) {
                null => 'unlinked',
                true => 'linked',
                default => 'revoked',
            };
        }

        return $states;
    }

    /**
     * The devices of the people of a page, the active first and then the newest; local times. One query.
     *
     * @param  list<string>  $people
     * @return array<string, list<array{id: string, label: string, synced: bool, enrolledAt: string, lastUsedAt: string|null, revokedAt: string|null}>>
     */
    private function devicesOf(array $people): array
    {
        $zone = config()->string('app.display_timezone');
        $local = fn (?CarbonImmutable $at): ?string => $at?->setTimezone($zone)->format('Y-m-d H:i');
        $devices = [];

        $rows = AttendanceDevice::query()->among('employee_id', $people)
            ->orderByRaw('revoked_at is not null')->orderByDesc('enrolled_at')->orderBy('id')
            ->get(['id', 'employee_id', 'label', 'backup_eligible', 'backup_state', 'enrolled_at', 'last_used_at', 'revoked_at']);

        foreach ($rows as $device) {
            $devices[$device->employee_id][] = [
                'id' => $device->id,
                'label' => $device->label,
                // A key that may be backed up lives in the person's passkey account, not provably on one phone.
                'synced' => $device->backup_eligible || $device->backup_state,
                'enrolledAt' => $device->enrolled_at->setTimezone($zone)->format('Y-m-d H:i'),
                'lastUsedAt' => $local($device->last_used_at),
                'revokedAt' => $local($device->revoked_at),
            ];
        }

        return $devices;
    }
}
