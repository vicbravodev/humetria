<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Providers;

use App\Modules\Campaigns\Events\CampaignClosed;
use App\Modules\Interventions\Listeners\DraftProgramOnClose;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * NOM-035 Programa de intervención. Its panel routes live in routes/interventions.php, required from the company group in
 * routes/company.php.
 */
final class InterventionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CampaignClosed::class, DraftProgramOnClose::class);
    }
}
