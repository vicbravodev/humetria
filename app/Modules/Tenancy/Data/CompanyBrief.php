<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * A company as the console of another module lists it, outside any company: how to name it, where it stands and how many work
 * centers it uses. `status` is the value of Tenancy's own status. `withdrawn` is set when a person suspended or archived it.
 */
final readonly class CompanyBrief
{
    public function __construct(
        public string $id,
        public string $tradeName,
        public string $legalName,
        public string $rfc,
        public string $slug,
        public string $status,
        public string $statusLabel,
        public bool $withdrawn,
        public int $workCenters,
    ) {}
}
