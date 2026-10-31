<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\CloseExpiredCampaigns;
use App\Modules\Campaigns\Actions\SendProgressDigests;
use App\Modules\Telework\Actions\ScheduleReviews;
use App\Modules\Telework\Actions\SendTeleworkDigests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('campaigns:close-expired', function (CloseExpiredCampaigns $closeExpired): void {
    $this->info('Evaluaciones cerradas: '.$closeExpired->handle());
})->purpose('Close the evaluations whose last day is over');

Artisan::command('campaigns:send-digests', function (SendProgressDigests $sendDigests): void {
    $this->info('Resúmenes enviados: '.$sendDigests->handle());
})->purpose('Email each owner how far its open evaluations got');

Artisan::command('telework:schedule-reviews', function (ScheduleReviews $schedule): void {
    $this->info('Listas de la Sección II pedidas: '.$schedule->handle());
})->purpose('Ask for the periodic Sección II of the workplaces whose validation lapses within 30 days');

Artisan::command('telework:send-digests', function (SendTeleworkDigests $sendDigests): void {
    $this->info('Resúmenes enviados: '.$sendDigests->handle());
})->purpose('Email the owner and each Comisión member what waits for them in NOM-037');

Schedule::command('campaigns:close-expired')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('campaigns:send-digests')->weeklyOn(1, '9:00')->timezone(config()->string('app.display_timezone'))->onOneServer();
Schedule::command('telework:schedule-reviews')->dailyAt('07:00')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('telework:send-digests')->dailyAt('08:00')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
// DB-IP publishes at the start of each month. On every server: each reads its own copy.
Schedule::command('geoip:update')->monthlyOn(5, '06:00');
Schedule::command('queue:prune-batches')->daily();
Schedule::command('queue:prune-failed')->daily();
