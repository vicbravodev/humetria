<?php

declare(strict_types=1);

namespace App\Modules\Results\Providers;

use App\Modules\Results\Contracts\EvaluationResults;
use App\Modules\Results\Support\CompanyEvaluationResults;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class ResultsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EvaluationResults::class, CompanyEvaluationResults::class);
    }

    public function boot(): void
    {
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
