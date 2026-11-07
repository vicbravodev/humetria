<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;

it('closes expired evaluations hourly, sends the Monday digests, refreshes the city database and runs the queue housekeeping, and reviews NOM-037 at 07:00 and digests it and reminds of unanswered accidents and of channelings to follow up at 08:00 display time', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn (Event $event): array => [Str::after($event->command ?? '', "'artisan' ") => $event->expression])
        ->all();

    expect($events)->toBe([
        'campaigns:close-expired' => '0 * * * *',
        'campaigns:send-digests' => '0 9 * * 1',
        'campaigns:remind-next-evaluations' => '15 8 * * *',
        'interventions:send-digests' => '30 9 * * 1',
        'disclosure:send-digests' => '45 9 * * 1',
        'telework:schedule-reviews' => '0 7 * * *',
        'telework:send-digests' => '0 8 * * *',
        'results:remind-referrals' => '0 8 * * *',
        'telework:remind-accidents' => '0 8 * * *',
        'seating:schedule-checks' => '30 7 * * *',
        'seating:remind-questions' => '0 9 * * *',
        'seating:send-digests' => '30 8 * * *',
        'complaints:send-digests' => '0 * * * *',
        'complaints:purge' => '15 3 * * *',
        'attendance:close-days' => '15 0 * * *',
        'horizon:snapshot' => '*/5 * * * *',
        'geoip:update' => '0 6 5 * *',
        'queue:prune-batches' => '0 0 * * *',
        'queue:prune-failed' => '0 0 * * *',
    ]);

    $telework = collect(app(Schedule::class)->events())->filter(fn (Event $event): bool => str_contains($event->command ?? '', 'telework:'));
    $referrals = collect(app(Schedule::class)->events())->first(fn (Event $event): bool => str_contains($event->command ?? '', 'results:remind-referrals'));
    expect($referrals?->timezone)->toBe('America/Mexico_City')
        ->and($referrals?->onOneServer)->toBeTrue()
        ->and($referrals?->withoutOverlapping)->toBeTrue();
    expect($telework)->toHaveCount(3)
        ->and($telework->every(fn (Event $event): bool => $event->timezone === 'America/Mexico_City' && $event->onOneServer && $event->withoutOverlapping))->toBeTrue();
});

it('closes yesterday\'s working days at 00:15 display time, once and without overlapping', function (): void {
    $event = collect(app(Schedule::class)->events())->sole(fn (Event $event): bool => str_contains($event->command ?? '', 'attendance:close-days'));

    expect($event->timezone)->toBe('America/Mexico_City')
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('runs the Ley Silla checks at 07:30, digests them at 08:30 and reminds the 2 questions at 09:00 display time, once and without overlapping', function (): void {
    $seating = collect(app(Schedule::class)->events())->filter(fn (Event $event): bool => str_contains($event->command ?? '', 'seating:'));

    expect($seating)->toHaveCount(3)
        ->and($seating->every(fn (Event $event): bool => $event->timezone === 'America/Mexico_City' && $event->onOneServer && $event->withoutOverlapping))->toBeTrue();
});

it('sends the Programa de intervención digest on Mondays at 09:30 display time, once and without overlapping', function (): void {
    $digest = collect(app(Schedule::class)->events())->filter(fn (Event $event): bool => str_contains($event->command ?? '', 'interventions:'));

    expect($digest)->toHaveCount(1)
        ->and($digest->every(fn (Event $event): bool => $event->timezone === 'America/Mexico_City' && $event->onOneServer && $event->withoutOverlapping))->toBeTrue();
});

it('runs the Programa digest command and reports what it did', function (): void {
    $this->artisan('interventions:send-digests')->expectsOutput('Resúmenes enviados: 0')->assertSuccessful();
});

it('runs the Ley Silla commands and reports what they did', function (): void {
    $this->artisan('seating:schedule-checks')->expectsOutput('Evaluaciones marcadas por reevaluar: 0')->assertSuccessful();
    $this->artisan('seating:send-digests')->expectsOutput('Resúmenes enviados: 0')->assertSuccessful();
});

it('runs the NOM-037 commands and reports what they did', function (): void {
    $this->artisan('telework:schedule-reviews')->expectsOutput('Listas de la Sección II pedidas: 0')->assertSuccessful();
    $this->artisan('telework:send-digests')->expectsOutput('Resúmenes enviados: 0')->assertSuccessful();
});

it('runs the NOM-037 commands in the display time zone, whatever it is set to', function (): void {
    config(['app.display_timezone' => 'America/Tijuana']);
    require base_path('routes/console.php');

    $telework = collect(app(Schedule::class)->events())->filter(fn (Event $event): bool => str_contains($event->command ?? '', 'telework:'))->slice(-2);

    expect($telework)->toHaveCount(2)
        ->and($telework->every(fn (Event $event): bool => $event->timezone === 'America/Tijuana'))->toBeTrue();
});

it('reminds of the next NOM-035 evaluation every day at 08:15 display time, once and without overlapping', function (): void {
    $event = collect(app(Schedule::class)->events())->sole(fn (Event $event): bool => str_contains($event->command ?? '', 'campaigns:remind-next-evaluations'));

    expect($event->timezone)->toBe('America/Mexico_City')
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
});
