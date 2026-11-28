<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoWorkspace;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/*
 * Preparing the demo takes most of a minute inside a test's transaction, so every story is checked on one prepared company:
 * each file of Sections/ returns its story's groups (its former tests), run here in this order. The order matters, because
 * some groups act as a visitor would and leave rows behind:
 * - leave asks for and approves one more vacation, after its own checks;
 * - attendance records one more mark today, after leave checked that nobody marked on a day off;
 * - complaints files one more case, disclosure, seating and telework confirm from a door person's portal: each touches
 *   only its own module, checked before;
 * - coherence checks the modules against each other once every story stands, and opens every page of the panel;
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

    // The planner sees the prepared company. Without this, an autovacuum ANALYZE from outside would see the tables empty
    // (it does not count rows this test has not committed) and the pages of the story would plan for empty tables: a run of
    // minutes became one of half an hour on CI. Inside this transaction, ANALYZE counts them and keeps autovacuum out.
    DB::statement('ANALYZE');
    expect(DB::scalar("select reltuples from pg_class where relname = 'attendance_marks'"))->toBeGreaterThan(0);

    foreach (['foundation', 'nom035', 'engagement', 'leave', 'attendance', 'complaints', 'disclosure', 'seating', 'telework', 'coherence', 'compensation'] as $story) {
        foreach (require __DIR__."/Sections/{$story}.php" as $name => $group) {
            // By reference: a group that scans the logs sees everything logged until then, its own visitor actions too.
            section("{$story}: {$name}", function () use ($group, &$logged): void {
                $group($logged);
            });
        }
    }
});

it('keeps every file a module writes for the demo on a fake disk', function (): void {
    $disks = config()->array('billing.storage_disks');

    expect($disks)->toEqualCanonicalizing(['telework_files', 'seating_files', 'intervention_files', 'disclosure_files']);

    foreach ($disks as $key) {
        expect(Storage::disk(config()->string("filesystems.{$key}"))->path(''))->toContain('framework/testing/disks');
    }
});
