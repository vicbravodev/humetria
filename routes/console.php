<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\CloseExpiredCampaigns;
use App\Modules\Campaigns\Actions\SendProgressDigests;
use App\Modules\Complaints\Actions\PurgeComplaints;
use App\Modules\Complaints\Actions\SendComplaintDigests;
use App\Modules\Seating\Actions\ScheduleSeatingChecks;
use App\Modules\Seating\Actions\SendSeatingDigests;
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

Artisan::command('seating:schedule-checks', function (ScheduleSeatingChecks $schedule): void {
    $this->info('Evaluaciones marcadas por reevaluar: '.$schedule->handle());
})->purpose('Mark for reassessment the current assessments that turn a year old');

Artisan::command('complaints:send-digests', function (SendComplaintDigests $sendDigests): void {
    $this->info('Resúmenes enviados: '.$sendDigests->handle());
})->purpose('Email each handler of the complaints channel how many cases are new, answered or past their deadline (counts only)');

Artisan::command('complaints:purge', function (PurgeComplaints $purge): void {
    $this->info('Casos purgados: '.$purge->handle());
})->purpose('Delete the content of the complaints closed longer ago than their company keeps them, leaving a skeleton with no personal data');

Artisan::command('seating:send-digests', function (SendSeatingDigests $sendDigests): void {
    $this->info('Resúmenes enviados: '.$sendDigests->handle());
})->purpose('Email the owner and each Comisión member what waits for them in Ley Silla');

Schedule::command('campaigns:close-expired')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('campaigns:send-digests')->weeklyOn(1, '9:00')->timezone(config()->string('app.display_timezone'))->onOneServer();
Schedule::command('telework:schedule-reviews')->dailyAt('07:00')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('telework:send-digests')->dailyAt('08:00')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('seating:schedule-checks')->dailyAt('07:30')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('seating:send-digests')->dailyAt('08:30')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('complaints:send-digests')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('complaints:purge')->dailyAt('03:15')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
// DB-IP publishes at the start of each month. On every server: each reads its own copy.
Schedule::command('geoip:update')->monthlyOn(5, '06:00');
Schedule::command('queue:prune-batches')->daily();
Schedule::command('queue:prune-failed')->daily();
