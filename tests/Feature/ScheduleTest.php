<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;

it('closes expired evaluations hourly, sends the Monday digests, refreshes the city database and runs the queue housekeeping', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn (Event $event): array => [Str::after($event->command ?? '', "'artisan' ") => $event->expression])
        ->all();

    expect($events)->toBe([
        'campaigns:close-expired' => '0 * * * *',
        'campaigns:send-digests' => '0 9 * * 1',
        'horizon:snapshot' => '*/5 * * * *',
        'geoip:update' => '0 6 5 * *',
        'queue:prune-batches' => '0 0 * * *',
        'queue:prune-failed' => '0 0 * * *',
    ]);
});
