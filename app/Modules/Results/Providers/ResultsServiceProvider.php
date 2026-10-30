<?php

declare(strict_types=1);

namespace App\Modules\Results\Providers;

use App\Modules\Campaigns\Events\CampaignClosed;
use App\Modules\Results\Listeners\SendClosingSummary;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class ResultsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CampaignClosed::class, SendClosingSummary::class);

        // Per link, so a board room can open one at once; a cap per address keeps anyone from trying links in bulk.
        // The link is hashed: a key never holds it.
        RateLimiter::for('report-links', function (Request $request): array {
            $token = $request->route('token');

            return [
                Limit::perMinute(30)->by('report-link:'.hash('sha256', is_string($token) ? $token : '')),
                Limit::perMinute(120)->by('report-link-address:'.$request->ip()),
            ];
        });
    }
}
