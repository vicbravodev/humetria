<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Nos dijeron / Haremos» in the person's start, once the company published what it will do after the climate survey.
 */
final readonly class ClimateWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies, private PortalClimatePages $pages) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $company = $this->companies->current();

        if ($company->stopped('climate') || $this->pages->survey() === null) {
            return null;
        }

        return new WorkerSectionLink('climate', 'Nos dijeron / Haremos', 'Lo que su empresa escuchó en la encuesta de clima y lo que hará.', route('portal.climate.show', $company->slug));
    }
}
