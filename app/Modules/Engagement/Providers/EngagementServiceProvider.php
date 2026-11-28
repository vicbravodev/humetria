<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Providers;

use App\Modules\Engagement\Contracts\EngagementActivity;
use App\Modules\Engagement\Contracts\EngagementStanding;
use App\Modules\Engagement\Events\SurveyClosed;
use App\Modules\Engagement\Listeners\CancelOnDeactivation;
use App\Modules\Engagement\Listeners\FallBackToMail;
use App\Modules\Engagement\Listeners\NotifyManagers;
use App\Modules\Engagement\Listeners\QueueCommentSummaries;
use App\Modules\Engagement\Support\ClimateWorkerSection;
use App\Modules\Engagement\Support\CompanyEngagementActivity;
use App\Modules\Engagement\Support\CompanyEngagementStanding;
use App\Modules\Engagement\Support\EngagementInvitationLinks;
use App\Modules\Engagement\Support\FeedbackWorkerSection;
use App\Modules\Engagement\Support\SurveyWorkerSection;
use App\Modules\Messaging\Contracts\InvitationLinks;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Organization\Events\EmployeeDeactivated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Clima y pulso. Its panel routes live in routes/engagement.php, required from the company group in routes/company.php;
 * the link of the survey in routes/engagement-survey.php and the portal in routes/engagement-portal.php, both required
 * from routes/web.php. Contracts, listeners and rate limiters are registered here as they appear.
 */
final class EngagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EngagementStanding::class, CompanyEngagementStanding::class);
        $this->app->bind(EngagementActivity::class, CompanyEngagementActivity::class);
        $this->app->tag([SurveyWorkerSection::class, ClimateWorkerSection::class, FeedbackWorkerSection::class], 'worker.sections');
        $this->app->tag([EngagementInvitationLinks::class], InvitationLinks::TAG);
    }

    public function boot(): void
    {
        // Per link, so a plant of people on one Wi-Fi can answer at once; a generous cap per address keeps anyone from
        // trying links in bulk. The link is hashed: a key never holds it.
        $link = function (Request $request): string {
            $token = $request->route('token');

            return 'engagement:'.hash('sha256', is_string($token) ? $token : '');
        };
        RateLimiter::for('engagement-views', fn (Request $request): array => [
            Limit::perMinute(30)->by($link($request)),
            Limit::perMinute(600)->by('engagement-address:'.$request->ip()),
        ]);
        RateLimiter::for('engagement-answers', fn (Request $request): array => [
            Limit::perMinute(10)->by($link($request)),
            Limit::perMinute(300)->by('engagement-answers-address:'.$request->ip()),
        ]);

        Event::listen(EmployeeDeactivated::class, CancelOnDeactivation::class);
        Event::listen(SurveyClosed::class, QueueCommentSummaries::class);
        Event::listen(SurveyClosed::class, NotifyManagers::class);
        Event::listen(WhatsAppMessageFailed::class, FallBackToMail::class);
    }
}
