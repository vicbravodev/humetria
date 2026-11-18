<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Attendance\Contracts\AttendanceStanding;

/**
 * Asistencia by what RH's panel asks of it: the policy reviewed, a schedule for each person and the corrections that wait.
 * Pending while any is missing, up to date otherwise. Counts only: never whose mark, hours or reason.
 */
final readonly class AttendanceReader implements ComplianceReader
{
    public function __construct(private AttendanceStanding $attendance, private PanelLinks $links) {}

    public function status(AssistantScope $scope): ComplianceStatus
    {
        $setup = $this->attendance->current();
        $summary = implode(' ', array_filter([
            $setup->policyReviewed ? null : 'Aún no revisas la política del registro de jornada.',
            $setup->unscheduled === 0 ? null : trans_choice('{1} :count persona no tiene horario|[2,*] :count personas no tienen horario', $setup->unscheduled).'.',
            $setup->pendingCorrections === 0 ? null : 'Hay '.trans_choice('{1} :count solicitud|[2,*] :count solicitudes', $setup->pendingCorrections).' de corrección por atender.',
        ]));

        return match (true) {
            $setup->pendingCorrections > 0 => new ComplianceStatus('attendance', 'Asistencia', 'pending', 'Pendiente', $summary, $this->links->attendanceCorrections()),
            $summary !== '' => new ComplianceStatus('attendance', 'Asistencia', 'pending', 'Pendiente', $summary, $this->links->attendanceSetup()),
            default => new ComplianceStatus('attendance', 'Asistencia', 'up_to_date', 'Al día', 'Tu política está revisada, todas las personas tienen horario y no hay correcciones por atender.', $this->links->attendance()),
        };
    }
}
