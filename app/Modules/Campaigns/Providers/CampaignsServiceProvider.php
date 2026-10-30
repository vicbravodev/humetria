<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Providers;

use App\Modules\Campaigns\Contracts\OpenCampaigns;
use App\Modules\Campaigns\Listeners\CancelOnDeactivation;
use App\Modules\Campaigns\Listeners\CancelOnMove;
use App\Modules\Campaigns\Support\CompanyOpenCampaigns;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Events\EmployeeMoved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class CampaignsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OpenCampaigns::class, CompanyOpenCampaigns::class);
    }

    public function boot(): void
    {
        Event::listen(EmployeeDeactivated::class, CancelOnDeactivation::class);
        Event::listen(EmployeeMoved::class, CancelOnMove::class);
    }
}
