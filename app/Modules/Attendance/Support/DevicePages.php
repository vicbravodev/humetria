<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AttendanceDevice;
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

        return [
            'center' => $center === null ? null : ['id' => $center->id, 'name' => $center->name],
            'filters' => ['q' => $search, 'status' => $view],
            'counts' => [
                'all' => count($states),
                ...array_combine(array_slice(self::VIEWS, 1), array_map(fn (string $state): int => count(array_keys($states, $state, true)), array_slice(self::VIEWS, 1))),
            ],
            'people' => $page->through(fn (RosterEntry $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'jobTitle' => $person->jobTitle,
                'center' => $scope->names[$person->workCenterId] ?? '—',
                'hasEmail' => $person->email !== null,
                'status' => $states[$person->id] ?? 'unlinked',
                'devices' => $devices[$person->id] ?? [],
            ]),
            'canManage' => Gate::allows('manage-attendance'),
        ];
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
