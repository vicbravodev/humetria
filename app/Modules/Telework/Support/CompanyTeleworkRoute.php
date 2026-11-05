<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Contracts\TeleworkRoute;
use App\Modules\Telework\Data\UnreviewedAccidents;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Tenancy\Contracts\WorkCenters;

final readonly class CompanyTeleworkRoute implements TeleworkRoute
{
    public function __construct(private ComplianceRows $rows, private WorkCenters $workCenters, private Roster $roster) {}

    public function progress(array $workCenterIds): array
    {
        $own = array_values(array_intersect($workCenterIds, array_column($this->workCenters->all(), 'id')));
        $profiles = TeleworkProfile::query()->whereIn('work_center_id', $own)->get(['work_center_id', 'has_teleworkers', 'declared_on'])->keyBy('work_center_id');
        $progress = [];

        foreach ($this->rows->forMany($own) as $id => $rows) {
            $profile = $profiles->get($id);
            $progress[$id] = RouteStages::from($rows, $profile?->has_teleworkers, $profile === null ? null : str_replace('.', '', $profile->declared_on->isoFormat('D MMM YYYY')));
        }

        return $progress;
    }

    public function accidents(): array
    {
        $notices = WorkerNotice::query()->where('kind', NoticeKind::WorkRisk)->whereNull('reviewed_at')->orderBy('submitted_at')->orderBy('id')->get(['id', 'teleworker_id', 'submitted_at']);

        if ($notices->isEmpty()) {
            return [];
        }

        $people = Teleworker::query()->whereIn('id', $notices->pluck('teleworker_id')->unique()->values()->all())->get(['id', 'employee_id', 'work_center_id'])->keyBy('id');
        $zone = config()->string('app.display_timezone');
        $centers = [];

        foreach ($notices as $notice) {
            $person = $people->get($notice->teleworker_id);

            if ($person !== null) {
                $centers[$person->work_center_id] ??= ['count' => 0, 'employee' => $person->employee_id, 'since' => $notice->submitted_at->setTimezone($zone)->toDateString(), 'notice' => $notice->id];
                $centers[$person->work_center_id]['count']++;
            }
        }

        $lone = array_filter($centers, fn (array $center): bool => $center['count'] === 1);
        $names = $this->roster->findMany(array_values(array_map(fn (array $center): string => $center['employee'], $lone)));
        $accidents = [];

        foreach ($centers as $centerId => $center) {
            $lone = $center['count'] === 1;
            $accidents[] = new UnreviewedAccidents($centerId, $center['count'], $lone ? ($names[$center['employee']]->name ?? null) : null, $center['since'], $lone ? $center['notice'] : null);
        }

        return $accidents;
    }
}
