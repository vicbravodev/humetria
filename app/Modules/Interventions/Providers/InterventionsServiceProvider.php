<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Providers;

use App\Modules\Campaigns\Events\CampaignClosed;
use App\Modules\Interventions\Contracts\ProgramStandings;
use App\Modules\Interventions\Listeners\DraftProgramOnClose;
use App\Modules\Interventions\Support\CompanyProgramOutlines;
use App\Modules\Interventions\Support\CompanyProgramStandings;
use App\Modules\Results\Contracts\ProgramOutlines;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * NOM-035 Programa de intervención. Its panel routes live in routes/interventions.php, required from the company group in
 * routes/company.php.
 */
final class InterventionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProgramOutlines::class, CompanyProgramOutlines::class);
        $this->app->bind(ProgramStandings::class, CompanyProgramStandings::class);
    }

    public function boot(): void
    {
        Event::listen(CampaignClosed::class, DraftProgramOnClose::class);
    }
}
