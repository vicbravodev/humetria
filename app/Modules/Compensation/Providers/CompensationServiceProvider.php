<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Compensación (collective contract calculator). Its panel routes live in routes/compensation.php, required from the
 * company group in routes/company.php; the public link to a report is in routes/compensation-shared.php.
 */
final class CompensationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Per link, so a negotiating table can open one at once; a cap per address keeps anyone from trying links in bulk.
        // The link is hashed: a key never holds it.
        RateLimiter::for('compensation-report-links', function (Request $request): array {
            $token = $request->route('token');

            return [
                Limit::perMinute(30)->by('compensation-report-link:'.hash('sha256', is_string($token) ? $token : '')),
                Limit::perMinute(120)->by('compensation-report-link-address:'.$request->ip()),
            ];
        });
    }
}
