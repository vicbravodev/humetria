<?php

declare(strict_types=1);

use App\Modules\Assistant\Providers\AssistantServiceProvider;
use App\Modules\Attendance\Providers\AttendanceServiceProvider;
use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Campaigns\Providers\CampaignsServiceProvider;
use App\Modules\Compensation\Providers\CompensationServiceProvider;
use App\Modules\Complaints\Providers\ComplaintsServiceProvider;
use App\Modules\Demos\Providers\DemosServiceProvider;
use App\Modules\Disclosure\Providers\DisclosureServiceProvider;
use App\Modules\Engagement\Providers\EngagementServiceProvider;
use App\Modules\Instruments\Providers\InstrumentsServiceProvider;
use App\Modules\Interventions\Providers\InterventionsServiceProvider;
use App\Modules\Leave\Providers\LeaveServiceProvider;
use App\Modules\Organization\Providers\OrganizationServiceProvider;
use App\Modules\Responses\Providers\ResponsesServiceProvider;
use App\Modules\Results\Providers\ResultsServiceProvider;
use App\Modules\Seating\Providers\SeatingServiceProvider;
use App\Modules\Telework\Providers\TeleworkServiceProvider;
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
    BillingServiceProvider::class,
    OrganizationServiceProvider::class,
    InstrumentsServiceProvider::class,
    CampaignsServiceProvider::class,
    ResponsesServiceProvider::class,
    ResultsServiceProvider::class,
    InterventionsServiceProvider::class,
    TeleworkServiceProvider::class,
    SeatingServiceProvider::class,
    AttendanceServiceProvider::class,
    LeaveServiceProvider::class,
    CompensationServiceProvider::class,
    ComplaintsServiceProvider::class,
    DisclosureServiceProvider::class,
    EngagementServiceProvider::class,
    AssistantServiceProvider::class,
];
