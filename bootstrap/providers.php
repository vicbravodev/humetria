<?php

declare(strict_types=1);

use App\Modules\Campaigns\Providers\CampaignsServiceProvider;
use App\Modules\Demos\Providers\DemosServiceProvider;
use App\Modules\Instruments\Providers\InstrumentsServiceProvider;
use App\Modules\Organization\Providers\OrganizationServiceProvider;
use App\Modules\Responses\Providers\ResponsesServiceProvider;
use App\Modules\Tenancy\Providers\TenancyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    DemosServiceProvider::class,
    TenancyServiceProvider::class,
    OrganizationServiceProvider::class,
    InstrumentsServiceProvider::class,
    CampaignsServiceProvider::class,
    ResponsesServiceProvider::class,
];
