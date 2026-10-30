<?php

declare(strict_types=1);

use App\Modules\Demos\Providers\DemosServiceProvider;
use App\Modules\Instruments\Providers\InstrumentsServiceProvider;
use App\Modules\Organization\Providers\OrganizationServiceProvider;
use App\Modules\Tenancy\Providers\TenancyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    DemosServiceProvider::class,
    TenancyServiceProvider::class,
    OrganizationServiceProvider::class,
    InstrumentsServiceProvider::class,
];
