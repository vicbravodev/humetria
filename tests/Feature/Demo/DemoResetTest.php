<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use Database\Factories\Attendance\AttendanceMarkFactory;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Resetting wipes the demo company and preparing tells its story again, on the same company: whatever the console printed
 * or shared (the slug, the complaint QR, each person's portal link) still works afterwards. The door's 40 people are the
 * exception: they get new ids on every prepare, so a link an earlier audience kept stops working with the reset.
 */

/** @return array<string, int> rows of the demo in each table with a tenant_id (the audit trail aside: it is kept) */
function demoRows(string $tenantId): array
{
    $rows = [];

    foreach (DB::table('information_schema.columns')->where('table_schema', 'public')->where('column_name', 'tenant_id')->orderBy('table_name')->pluck('table_name') as $table) {
        if ($table !== 'audit_entries') {
            $rows[$table] = DB::table($table)->where('tenant_id', $tenantId)->count();
        }
    }

    return $rows;
}

/** @return list<string> the demo's files on every module's disk */
function demoFiles(string $tenantId): array
{
    $files = [];

    foreach (config()->array('billing.storage_disks') as $key) {
        $disk = Storage::disk(config()->string("filesystems.{$key}"));

        foreach ($disk->allFiles() as $file) {
            if (str_contains($file, "/{$tenantId}/")) {
                $files[] = "{$key}:{$file}";
            }
        }
    }

    return $files;
}

/** @return array<string, mixed> what was printed or shared and must survive a reset */
function demoKeepsakes(): array
{
    $tenant = DB::table('tenants')->where('is_demo', true)->sole();
    resolve(Companies::class)->enter($tenant->id);
    // Everyone but the door's people, whose ids are drawn anew on every prepare (see below).
    $kept = array_values(array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => ! $person->reservedForDoor));
    $numbers = array_map(fn (DemoPerson $person): string => $person->employeeNumber, $kept);

    return [
        'tenant' => [$tenant->id, $tenant->slug, $tenant->rfc],
        'members' => DB::table('users')->whereIn('email', [DemoRoster::person(DemoRoster::OWNER)->email, DemoRoster::person(DemoRoster::HR)->email])->orderBy('id')->pluck('id')->all(),
        'employees' => DB::table('employees')->where('tenant_id', $tenant->id)->whereIn('employee_number', $numbers)->orderBy('id')->pluck('id')->all(),
        'portal links' => array_map(fn (DemoPerson $person): string => resolve(PortalAccess::class)->token(DemoRoster::employeeId($person->key)), array_slice($kept, 20, 3)),
        'complaint code' => DB::table('complaint_channels')->where('tenant_id', $tenant->id)->value('access_code'),
        'kiosk' => DB::table('attendance_kiosk_devices')->where('tenant_id', $tenant->id)->count(),
    ];
}

it('prepares the same story over itself on the same company, resets it to nothing, and sends no email', function (): void {
    // Committed as in production: inside the test's transaction every action is a savepoint, and Postgres slows to a
    // crawl checking the foreign keys of rows written by thousands of them (the reset alone took minutes, not 2 s).
    // Laravel migrates the database afresh for the next test when this one ends outside a transaction.
    DB::rollBack();
    // Thousands of commits: none waits for the disk (a test's data is thrown away anyway).
    DB::statement('SET synchronous_commit TO OFF');
    // BlockDemoMail cancels a message before any later MessageSending listener sees it: the notifications are counted.
    $sending = 0;
    $blocked = 0;
    Event::listen(NotificationSending::class, function (NotificationSending $event) use (&$sending): void {
        $sending += $event->channel === 'mail' ? 1 : 0;
    });
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$blocked): void {
        $blocked += $log->message === 'demo.mail_blocked' ? 1 : 0;
    });
    // Another company, with an insert-only row and a file: the reset never touches it.
    $other = AttendanceMarkFactory::new()->createOne()->tenant_id;
    $otherFile = "documents/{$other}/firmado.pdf";
    Storage::disk(config()->string('filesystems.telework_files'))->put($otherFile, 'PDF');
    $otherRows = demoRows($other);
    $workspace = resolve(DemoWorkspace::class);

    $workspace->prepare();

    $tenantId = DB::table('tenants')->where('is_demo', true)->value('id');
    $before = demoKeepsakes();
    $rows = demoRows($tenantId);
    // The queue runs in sync, as the workers would: invitations, reminders and digests all reach the real mailer. One
    // with any real address would go through BlockDemoMail and be delivered: none was. Nor is any of it counted.
    expect($sending)->toBeGreaterThan(0)
        ->and($blocked)->toBe($sending)
        ->and(count(app('mailer')->getSymfonyTransport()->messages()))->toBe(0)
        ->and(DB::table('usage_events')->where('meter', 'email_campaign')->exists())->toBeFalse()
        ->and(demoFiles($tenantId))->not->toBe([]);

    // A link of the door handed out to this audience, and one of somebody else.
    $doorKeys = array_values(array_map(fn (DemoPerson $person): string => $person->key, array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->reservedForDoor)));
    $doorIds = array_map(DemoRoster::employeeId(...), $doorKeys);
    $doorLink = route('portal.show', resolve(PortalAccess::class)->token($doorIds[0]));
    $keptLink = route('portal.show', resolve(PortalAccess::class)->token(DemoRoster::employeeId(DemoRoster::HR)));

    // Preparing a demo that exists resets it first: the same company and story, never a second one nor a policy version 2.
    $workspace->prepare();

    // The next audience's door people are new: the link kept from this session finds nobody, everyone else's still works.
    $newDoorIds = array_map(DemoRoster::employeeId(...), $doorKeys);
    expect(array_intersect($newDoorIds, $doorIds))->toBe([])
        ->and(DB::table('employees')->whereIn('id', $newDoorIds)->count())->toBe(40)
        ->and(DB::table('employees')->whereIn('id', $doorIds)->exists())->toBeFalse();
    // The portal answers an unknown link with its «no disponible» page, the same as any other dead link: no name, no data.
    $this->get($doorLink)->assertOk()->assertInertia(fn (Assert $page) => $page->where('unavailable', true)->where('name', null)->where('sections', []));
    $this->get($keptLink)->assertOk()->assertInertia(fn (Assert $page) => $page->where('unavailable', false)->where('name', DemoRoster::person(DemoRoster::HR)->name));
    resolve(Companies::class)->enter($tenantId);

    expect(demoKeepsakes())->toBe($before)
        ->and(demoRows($tenantId))->toBe($rows)
        ->and(DB::table('tenants')->where('is_demo', true)->count())->toBe(1)
        ->and(DB::table('disclosure_documents')->where('tenant_id', $tenantId)->max('version'))->toBe(1);

    $workspace->reset();

    expect(array_filter(demoRows($tenantId)))->toBe([])
        ->and(demoFiles($tenantId))->toBe([])
        ->and(DB::table('tenants')->where('is_demo', true)->value('id'))->toBe($tenantId)
        ->and(DB::table('audit_entries')->where('tenant_id', $tenantId)->exists())->toBeTrue()
        ->and(DB::table('users')->whereIn('id', $before['members'])->count())->toBe(2)
        ->and(demoRows($other))->toBe($otherRows)
        ->and($otherRows['attendance_marks'])->toBe(1)
        ->and(Storage::disk(config()->string('filesystems.telework_files'))->exists($otherFile))->toBeTrue();
});
