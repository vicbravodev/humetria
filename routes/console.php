<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\CloseDays;
use App\Modules\Campaigns\Actions\CloseExpiredCampaigns;
use App\Modules\Campaigns\Actions\RemindNextEvaluations;
use App\Modules\Campaigns\Actions\SendProgressDigests;
use App\Modules\Complaints\Actions\PurgeComplaints;
use App\Modules\Complaints\Actions\SendComplaintDigests;
use App\Modules\Disclosure\Actions\SendDisclosureDigests;
use App\Modules\Interventions\Actions\SendProgramDigests;
use App\Modules\Results\Actions\RemindReferrals;
use App\Modules\Seating\Actions\RemindStandingQuestions;
use App\Modules\Seating\Actions\ScheduleSeatingChecks;
use App\Modules\Seating\Actions\SendSeatingDigests;
use App\Modules\Telework\Actions\RemindUnansweredAccidents;
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

Artisan::command('campaigns:remind-next-evaluations', function (RemindNextEvaluations $remind): void {
    $this->info('Avisos de la próxima evaluación: '.$remind->handle());
})->purpose('Tell each owner of the centers whose NOM-035 evaluation turns two years old in 60 days');

Artisan::command('interventions:send-digests', function (SendProgramDigests $sendDigests): void {
    $this->info('Resúmenes enviados: '.$sendDigests->handle());
})->purpose('Email each owner and each responsible what waits for them in the Programas de intervención of NOM-035');

Artisan::command('disclosure:send-digests', function (SendDisclosureDigests $sendDigests): void {
    $this->info('Resúmenes enviados: '.$sendDigests->handle());
})->purpose('Email each owner what waits in the policy of prevention and its disclosure: centers without policy, results not published, a link about to expire (counts only)');

Artisan::command('telework:schedule-reviews', function (ScheduleReviews $schedule): void {
    $this->info('Listas de la Sección II pedidas: '.$schedule->handle());
})->purpose('Ask for the periodic Sección II of the workplaces whose validation lapses within 30 days');

Artisan::command('telework:send-digests', function (SendTeleworkDigests $sendDigests): void {
    $this->info('Resúmenes enviados: '.$sendDigests->handle());
})->purpose('Email the owner and each Comisión member what waits for them in NOM-037');

Artisan::command('results:remind-referrals', function (RemindReferrals $remind): void {
    $this->info('Recordatorios de canalización enviados: '.$remind->handle());
})->purpose('Remind whoever channeled a person, once, to ask how they are on the day they asked for');

Artisan::command('telework:remind-accidents', function (RemindUnansweredAccidents $remind): void {
    $this->info('Recordatorios de accidentes enviados: '.$remind->handle());
})->purpose('Remind RH, once, of each accident notice nobody answered in three days');

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

Artisan::command('seating:remind-questions', function (RemindStandingQuestions $remind): void {
    $this->info('Recordatorios de las 2 preguntas: '.$remind->handle());
})->purpose('Remind the people whose Ley Silla assessment started a week or two ago of their 2 questions, while it waits');

Artisan::command('attendance:close-days', function (CloseDays $closeDays): void {
    $this->info('Días cerrados: '.$closeDays->handle());
})->purpose('Close the day before yesterday for every active person with a mark that day or a working day by their schedule (the registro de jornada)');

Schedule::command('campaigns:close-expired')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('campaigns:send-digests')->weeklyOn(1, '9:00')->timezone(config()->string('app.display_timezone'))->onOneServer();
Schedule::command('campaigns:remind-next-evaluations')->dailyAt('08:15')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('interventions:send-digests')->weeklyOn(1, '9:30')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('disclosure:send-digests')->weeklyOn(1, '9:45')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('telework:schedule-reviews')->dailyAt('07:00')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('telework:send-digests')->dailyAt('08:00')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('results:remind-referrals')->dailyAt('08:00')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('telework:remind-accidents')->dailyAt('08:00')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('seating:schedule-checks')->dailyAt('07:30')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('seating:remind-questions')->dailyAt('09:00')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('seating:send-digests')->dailyAt('08:30')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('complaints:send-digests')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('complaints:purge')->dailyAt('03:15')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
// Shortly after local midnight; it closes the day before yesterday, a whole day after it ended. Once is enough: closing a
// day again gives the same day.
Schedule::command('attendance:close-days')->dailyAt('00:15')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
// A restore, a manual fix or a bug can leave a counter of people or centers off: recount every night, after the day is closed.
Schedule::command('humetria:reconcile-limits')->dailyAt('03:30')->timezone(config()->string('app.display_timezone'))->onOneServer()->withoutOverlapping();
// A call that died mid-answer leaves its hold on the quota: give it back every ten minutes.
Schedule::command('billing:expire-usage-reservations')->everyTenMinutes()->onOneServer()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
// DB-IP publishes at the start of each month. On every server: each reads its own copy.
Schedule::command('geoip:update')->monthlyOn(5, '06:00');
Schedule::command('queue:prune-batches')->daily();
Schedule::command('queue:prune-failed')->daily();
