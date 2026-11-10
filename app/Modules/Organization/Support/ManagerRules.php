<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Models\Employee;
use Illuminate\Support\Str;

/**
 * Who may be a person's direct manager: someone active of the company who is not the person and does not report to
 * them. The database rejects the same on its own; this is what tells the person why.
 */
final readonly class ManagerRules
{
    public function __construct(private ReportingLines $lines) {}

    /**
     * @param  string|null  $employeeId  null for a person who does not exist yet.
     * @return string|null what is wrong with that manager, or null when they may be it.
     */
    public function violation(?string $employeeId, string $managerId): ?string
    {
        $manager = Str::isUuid($managerId) ? Employee::query()->find($managerId, ['id', 'deactivated_at']) : null;

        return match (true) {
            $manager === null => 'Elige a una persona de la lista.',
            $manager->deactivated_at !== null => 'Esa persona está dada de baja.',
            $employeeId === null => null,
            $employeeId === $managerId => 'Una persona no puede ser su propio jefe.',
            in_array($managerId, $this->lines->subtreeIds($employeeId), true) => 'Esa persona le reporta, directa o indirectamente. Elige a alguien más.',
            default => null,
        };
    }
}
