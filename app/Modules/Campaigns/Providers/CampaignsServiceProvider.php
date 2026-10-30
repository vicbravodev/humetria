<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Providers;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Contracts\EvaluationSchedule;
use App\Modules\Campaigns\Contracts\OpenCampaigns;
use App\Modules\Campaigns\Listeners\CancelOnDeactivation;
use App\Modules\Campaigns\Listeners\CancelOnMove;
use App\Modules\Campaigns\Support\CompanyCampaignRecords;
use App\Modules\Campaigns\Support\CompanyEvaluationSchedule;
use App\Modules\Campaigns\Support\CompanyOpenCampaigns;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Events\EmployeeMoved;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class CampaignsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OpenCampaigns::class, CompanyOpenCampaigns::class);
        $this->app->bind(CampaignRecords::class, CompanyCampaignRecords::class);
        $this->app->bind(EvaluationSchedule::class, CompanyEvaluationSchedule::class);
    }

    public function boot(): void
    {
        // Per link, so a room of people on one Wi-Fi (a group session, a carrier's shared address) can answer at once;
        // a generous cap per address keeps anyone from trying links in bulk. The link is hashed: a key never holds it.
        $link = function (Request $request): string {
            $token = $request->route('token');

            return 'survey:'.hash('sha256', is_string($token) ? $token : '');
        };
        RateLimiter::for('survey-views', fn (Request $request): array => [
            Limit::perMinute(30)->by($link($request)),
            Limit::perMinute(600)->by('survey-address:'.$request->ip()),
        ]);
        RateLimiter::for('survey-answers', fn (Request $request): array => [
            Limit::perMinute(10)->by($link($request)),
            Limit::perMinute(300)->by('survey-answers-address:'.$request->ip()),
        ]);

        Event::listen(EmployeeDeactivated::class, CancelOnDeactivation::class);
        Event::listen(EmployeeMoved::class, CancelOnMove::class);
    }
}
