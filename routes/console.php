<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\CloseExpiredCampaigns;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('campaigns:close-expired', function (CloseExpiredCampaigns $closeExpired): void {
    $this->info('Evaluaciones cerradas: '.$closeExpired->handle());
})->purpose('Close the evaluations whose last day is over');

Schedule::command('campaigns:close-expired')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
// DB-IP publishes at the start of each month. On every server: each reads its own copy.
Schedule::command('geoip:update')->monthlyOn(5, '06:00');
Schedule::command('queue:prune-batches')->daily();
Schedule::command('queue:prune-failed')->daily();
