<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

use App\Modules\Organization\Enums\SpanFlag;

/**
 * A person in the org chart: who they are, where, the size of their team and whether they are away today. Never contact
 * data.
 *
 * @phpstan-type OrgNodeShape array{id: string, name: string, job_title: string, area: string, work_center: string, active: bool, direct_reports: int, total_reports: int, flag: array{value: string, label: string}|null, matches: bool, manager_inactive: bool, absent_today: bool}
 */
final readonly class OrgNode
{
    /**
     * @param  int  $directReports  Active people who report to them.
     * @param  int  $totalReports  Active people below them, through active managers.
     * @param  bool  $matches  Whether the person is within the filters, or only on the way to someone who is.
     * @param  bool  $managerInactive  Their manager left and nobody took their place.
     * @param  bool  $absentToday  Away today with approved vacations or a leave; never which, nor until when.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $jobTitle,
        public string $area,
        public string $workCenter,
        public bool $active,
        public int $directReports,
        public int $totalReports,
        public ?SpanFlag $flag,
        public bool $matches,
        public bool $managerInactive,
        public bool $absentToday = false,
    ) {}

    /**
     * @return OrgNodeShape
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'job_title' => $this->jobTitle,
            'area' => $this->area,
            'work_center' => $this->workCenter,
            'active' => $this->active,
            'direct_reports' => $this->directReports,
            'total_reports' => $this->totalReports,
            'flag' => $this->flag === null ? null : ['value' => $this->flag->value, 'label' => $this->flag->label()],
            'matches' => $this->matches,
            'manager_inactive' => $this->managerInactive,
            'absent_today' => $this->absentToday,
        ];
    }
}
