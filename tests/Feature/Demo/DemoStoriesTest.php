<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoWorkspace;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/*
 * Preparing the demo takes most of a minute inside a test's transaction, so every story is checked on one prepared company:
 * each file of Sections/ returns its story's groups (its former tests), run here in this order. The order matters, because
 * some groups act as a visitor would and leave rows behind:
 * - leave asks for and approves one more vacation, after its own checks;
 * - attendance records one more mark today, after leave checked that nobody marked on a day off;
 * - complaints files one more case, disclosure, seating and telework confirm from a door person's portal: each touches
 *   only its own module, checked before;
 * - compensation goes last: it ends by entering a new company to compare the catalog.
 */
it('tells the whole story on one prepared demo company', function (): void {
    // In production the jobs run later on the queue: the story must stand without them (and it is twice as fast).
    Bus::fake();
    // Everything logged from the start: no story may log what it keeps private.
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message.' '.json_encode($log->context);
    });

    resolve(DemoWorkspace::class)->prepare();

    foreach (['foundation', 'nom035', 'engagement', 'leave', 'attendance', 'complaints', 'disclosure', 'seating', 'telework', 'compensation'] as $story) {
        foreach (require __DIR__."/Sections/{$story}.php" as $name => $group) {
            section("{$story}: {$name}", fn () => $group($logged));
        }
    }
});
