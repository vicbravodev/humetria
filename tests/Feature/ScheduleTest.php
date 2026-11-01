<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;

it('closes expired evaluations hourly, sends the Monday digests, refreshes the city database and runs the queue housekeeping, and reviews NOM-037 at 07:00 and digests it at 08:00 display time', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn (Event $event): array => [Str::after($event->command ?? '', "'artisan' ") => $event->expression])
        ->all();

    expect($events)->toBe([
        'campaigns:close-expired' => '0 * * * *',
        'campaigns:send-digests' => '0 9 * * 1',
        'telework:schedule-reviews' => '0 7 * * *',
        'telework:send-digests' => '0 8 * * *',
        'horizon:snapshot' => '*/5 * * * *',
        'geoip:update' => '0 6 5 * *',
        'queue:prune-batches' => '0 0 * * *',
        'queue:prune-failed' => '0 0 * * *',
    ]);

    $telework = collect(app(Schedule::class)->events())->filter(fn (Event $event): bool => str_contains($event->command ?? '', 'telework:'));
    expect($telework)->toHaveCount(2)
        ->and($telework->every(fn (Event $event): bool => $event->timezone === 'America/Mexico_City' && $event->onOneServer && $event->withoutOverlapping))->toBeTrue();
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
