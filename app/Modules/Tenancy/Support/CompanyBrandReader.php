<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CompanyBranding;
use App\Modules\Tenancy\Data\CompanyBrand;
use Illuminate\Support\Facades\Context;

/** Reads the brand TenantContext kept beside the company when it became the current one. */
final readonly class CompanyBrandReader implements CompanyBranding
{
    public function current(): ?CompanyBrand
    {
        $kept = Context::getHidden(TenantContext::BRAND);

        return $kept instanceof CompanyBrand && Context::getHidden(TenantContext::BRAND_REQUEST) === spl_object_id(request()) ? $kept : null;
    }
}
