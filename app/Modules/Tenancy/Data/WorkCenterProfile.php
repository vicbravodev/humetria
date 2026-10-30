<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * A work center as a NOM-035 report names it (num. 7.7 a): the company's legal name and RFC, the center's name, its
 * address in one line and its main activity.
 */
final readonly class WorkCenterProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $legalName,
        public string $rfc,
        public string $address,
        public string $mainActivity,
    ) {}
}
