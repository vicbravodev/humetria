<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\CompanyBrand;

/** The brand of the company the code works in, for the pages and emails that carry it instead of Humetría's. */
interface CompanyBranding
{
    /** Null outside a company. Costs no query: the company brings it when it becomes the current one. */
    public function current(): ?CompanyBrand;
}
