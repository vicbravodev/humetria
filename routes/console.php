<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\CloseExpiredCampaigns;
use App\Modules\Campaigns\Actions\SendProgressDigests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('campaigns:close-expired', function (CloseExpiredCampaigns $closeExpired): void {
    $this->info('Evaluaciones cerradas: '.$closeExpired->handle());
})->purpose('Close the evaluations whose last day is over');

Artisan::command('campaigns:send-digests', function (SendProgressDigests $sendDigests): void {
    $this->info('Resúmenes enviados: '.$sendDigests->handle());
})->purpose('Email each owner how far its open evaluations got');

Schedule::command('campaigns:close-expired')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('campaigns:send-digests')->weeklyOn(1, '9:00')->timezone('America/Mexico_City')->onOneServer();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
// DB-IP publishes at the start of each month. On every server: each reads its own copy.
Schedule::command('geoip:update')->monthlyOn(5, '06:00');
Schedule::command('queue:prune-batches')->daily();
Schedule::command('queue:prune-failed')->daily();
