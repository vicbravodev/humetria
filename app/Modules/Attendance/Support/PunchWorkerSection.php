<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Marcar» in the person's start, for a company with the registro de jornada: the screen of Marcar of the company (`/a/{slug}`),
 * whose address carries no credential (each mark asks for its own signature and the agreement).
 */
final readonly class PunchWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $company = $this->companies->current();

        if (! $company->allows('attendance')) {
            return null;
        }

        return new WorkerSectionLink('attendance', 'Marcar', 'Su entrada y su salida', route('attendance.device.punch.home', $company->slug));
    }
}
