<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Clima y pulso. Its panel routes live in routes/engagement.php, required from the company group in routes/company.php;
 * the link of the survey in routes/engagement-survey.php and the portal in routes/engagement-portal.php, both required
 * from routes/web.php. Contracts, listeners and rate limiters are registered here as they appear.
 */
final class EngagementServiceProvider extends ServiceProvider {}
