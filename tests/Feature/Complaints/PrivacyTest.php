<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\PurgeComplaints;
use App\Modules\Complaints\Actions\SendComplaintDigests;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\TicketSigner;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/*
 * The central privacy test of the complaints channel (criterio C-46): it runs a whole life of complaints, from a person's
 * submission to the purge, with markers in every free field, and then looks for the markers everywhere the platform writes:
 * every log line (before redaction, so it is the code that is checked and not only the redactor), the audit trail, every
 * email, the payloads of the jobs that are queued and every table of the database.
 */

const M_IP = '203.0.113.77';
const M_AGENT = 'MarkerBrowser/9.9 (queja-privada)';
const M_DESCRIPTION = 'MARK-DESC-7731';
const M_RESPONDENT = 'MARK-RESP-8842';
const M_NAME = 'MARK-NAME-9953';
const M_CONTACT = 'MARK-CONTACT-1064';
const M_EMAIL = 'marca.correo.2275@ejemplo.mx';
const M_INVOLVED = 'MARK-INV-3386';
const M_FROM_PERSON = 'MARK-MSG-5597';
const M_TO_PERSON = 'MARK-REPLY-6608';
const M_NOTE = 'MARK-NOTE-7719';

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 03:30:00');
    config(['session.driver' => 'database', 'cache.default' => 'database']);
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['retention_months' => 12]);
    foreach ([$this->owner, $this->hr] as $person) {
        ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $person->id]);
    }
    workIn($this->tenant);
});

/** Every marker the exercise writes, and what must never appear anywhere: the key, the reference and the address. */
function markers(array $receipt = []): array
{
    return array_values(array_filter([M_IP, 'MarkerBrowser', M_DESCRIPTION, M_RESPONDENT, M_NAME, M_CONTACT, M_EMAIL, M_INVOLVED, M_FROM_PERSON, M_TO_PERSON, M_NOTE, ...$receipt]));
}

/**
 * The whole life of two complaints. Returns the receipts of the person (reference and key) of both.
 *
 * @return list<array{reference: string, key: string}>
 */
function lifeOfComplaints(object $test): array
{
    $receipts = [];
    $submit = function (array $data) use ($test): array {
        $ticket = resolve(TicketSigner::class)->issue($test->channel->access_code);
        Carbon::setTestNow(now()->addSeconds(10));

        $response = $test->withServerVariables(['REMOTE_ADDR' => M_IP])->withHeaders(['User-Agent' => M_AGENT, 'Referer' => 'https://portal.example/portal/token-secreto'])
            ->postJson('/c/'.$test->channel->access_code, ['ticket' => $ticket, 'respondent_kind' => 'direct_supervisor', ...$data])->assertCreated()->json();
        // The address and the browser were the person's, for this one request: the handlers that follow come from elsewhere.
        $test->withServerVariables([])->flushHeaders();

        return $response;
    };
    $receipts[] = $submit(['type' => 'workplace_violence', 'description' => M_DESCRIPTION.' el jefe me grita', 'respondent_text' => M_RESPONDENT, 'identity_mode' => 'confidential', 'reporter_name' => M_NAME, 'reporter_contact' => M_CONTACT, 'notify_email' => M_EMAIL]);
    $receipts[] = $submit(['type' => 'traumatic_event_notice', 'description' => M_DESCRIPTION.' un accidente en la prensa', 'occurred_on' => '2026-11-10', 'involved_text' => M_INVOLVED, 'identity_mode' => 'confidential', 'reporter_name' => M_NAME, 'respondent_kind' => 'unknown']);

    // The person comes back: reads their case, writes, and the second case is withdrawn.
    foreach ($receipts as $receipt) {
        $test->postJson('/c/'.$test->channel->access_code.'/follow-up', ['reference' => $receipt['reference'], 'key' => $receipt['key']])->assertOk();
    }
    $test->postJson('/c/'.$test->channel->access_code.'/follow-up/messages', ['reference' => $receipts[0]['reference'], 'key' => $receipts[0]['key'], 'body' => M_FROM_PERSON])->assertCreated();
    $test->postJson('/c/'.$test->channel->access_code.'/follow-up/withdraw', ['reference' => $receipts[1]['reference'], 'key' => $receipts[1]['key']])->assertOk();

    // A handler works the first case.
    $case = Complaint::query()->where('type', 'workplace_violence')->sole();
    $url = fn (string $name): string => route("tenant.complaints.cases.{$name}", [$test->tenant, $case->id]);
    $test->actingAs($test->owner);
    $test->get($url('show'))->assertOk();
    $test->post($url('acknowledge'));
    $test->post($url('review'));
    $test->post($url('messages.store'), ['body' => M_TO_PERSON]);
    $test->post($url('notes.store'), ['body' => M_NOTE]);
    $test->post($url('share'), ['user_id' => $test->hr->id]);
    $test->post($url('hold'), ['on_hold' => '1']);
    $test->post($url('hold'), ['on_hold' => '0']);
    $test->post($url('close'), ['resolution' => 'measures_taken']);
    $test->post($url('reopen'));
    $test->post($url('close'), ['resolution' => 'no_action_needed']);
    $test->post($url('export'));
    $test->get(route('tenant.complaints.index', $test->tenant))->assertOk();
    $test->get(route('tenant.complaints.channel.show', $test->tenant))->assertOk();
    $test->get(route('tenant.complaints.evidence', $test->tenant))->assertOk();

    // The sweeps: the digests and, a year later, the purge.
    resolve(SendComplaintDigests::class)->handle();
    Carbon::setTestNow('2028-01-01 09:00:00');
    resolve(PurgeComplaints::class)->handle();

    return $receipts;
}

it('writes the words and the person of a complaint to no log line, audit entry or email in its whole life', function (): void {
    Notification::fake();
    $lines = [];
    Log::listen(function (MessageLogged $log) use (&$lines): void {
        $lines[] = json_encode([$log->level, $log->message, $log->context]);
    });

    $receipts = lifeOfComplaints($this);
    $secrets = markers([...array_column($receipts, 'key'), ...array_column($receipts, 'reference')]);

    expect($lines)->not->toBeEmpty();
    foreach ($secrets as $secret) {
        foreach ($lines as $line) {
            expect($line)->not->toContain($secret);
        }
        expect(json_encode(AuditEntry::query()->get()->map(fn (AuditEntry $entry): array => $entry->toArray())->all()))->not->toContain($secret);
    }

    $sent = [];
    $everything = Notification::sentNotifications();
    array_walk_recursive($everything, function (mixed $value, string|int $key) use (&$sent): void {
        if ($key === 'notification') {
            $sent[] = ['notification' => $value];
        }
    });
    expect($sent)->not->toBeEmpty();
    foreach ($sent as $row) {
        $mail = $row['notification']->toMail(new AnonymousNotifiable);
        $body = $mail->render();

        foreach ($secrets as $secret) {
            expect((string) $body)->not->toContain($secret);
        }
    }
});

it('leaves none of it in clear in any table of the database', function (): void {
    Notification::fake();

    $receipts = lifeOfComplaints($this);

    foreach (markers([...array_column($receipts, 'key')]) as $secret) {
        $found = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))
            ->filter(fn (object $table): bool => DB::selectOne('select count(*) as found from "'.$table->table_name.'" t where t::text like ?', ['%'.$secret.'%'])->found > 0)
            ->pluck('table_name')->all();

        expect($found)->toBe([], "«{$secret}» is in clear in: ".implode(', ', $found));
    }
    // The references of the person are gone with the purge; the keys never were anywhere but as a hash.
    foreach ($receipts as $receipt) {
        expect(DB::table('complaints')->where('reference', $receipt['reference'])->count())->toBe(0, json_encode(DB::table('complaints')->get(['reference', 'status', 'on_hold', 'closed_on', 'purged_on'])));
    }
});

it('puts no word of a complaint in the payload of any job it queues', function (): void {
    Queue::fake();
    config(['queue.default' => 'database']);
    $case = Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'description' => M_DESCRIPTION, 'respondent_text' => M_RESPONDENT, 'reporter_name' => M_NAME, 'identity_mode' => 'confidential', 'reference' => 'JOB12345']);
    App\Modules\Complaints\Models\ComplaintAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $case->id, 'user_id' => $this->owner->id]);

    resolve(SendComplaintDigests::class)->handle();
    $this->actingAs($this->owner)->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => memberOf($this->tenant, TenantRole::Complaints)->id, 'work_center_id' => '']);

    $jobs = Queue::pushed(SendQueuedNotifications::class);
    expect($jobs)->not->toBeEmpty();
    foreach ($jobs as $job) {
        $payload = serialize($job);
        foreach ([M_DESCRIPTION, M_RESPONDENT, M_NAME, 'JOB12345', M_IP] as $secret) {
            expect($payload)->not->toContain($secret);
        }
    }
});

it('stores no moment and no key to a person in the tables of a complaint', function (): void {
    $timestamps = fn (string $table): array => DB::table('information_schema.columns')->where('table_schema', 'public')->where('table_name', $table)->where('data_type', 'timestamp with time zone')->orderBy('column_name')->pluck('column_name')->all();
    $columns = fn (string $table): array => Schema::getColumnListing($table);

    expect($timestamps('complaints'))->toBe(['acknowledged_at', 'closed_at'])
        ->and($timestamps('complaint_entries'))->toBe(['occurred_at'])
        ->and($timestamps('complaint_assignments'))->toBe([]);
    foreach (['complaints', 'complaint_entries', 'complaint_assignments'] as $table) {
        expect($columns($table))->not->toContain('created_at')->not->toContain('updated_at')->not->toContain('ip_address')->not->toContain('user_agent')->not->toContain('ip');
    }
    expect(DB::table('information_schema.columns')->where('table_schema', 'public')->where('table_name', 'complaints')->whereIn('column_name', ['description', 'respondent_text', 'involved_text', 'reporter_name', 'reporter_contact', 'reporter_email'])->pluck('data_type')->unique()->all())->toBe(['text']);
});

it('never reads from the request what would tie it to a person, except the address the limits are keyed by', function (): void {
    $forbidden = ['userAgent(', 'HTTP_USER_AGENT', 'user-agent', "'Referer'", '"Referer"', 'HTTP_REFERER', '->referer', 'cookie(', '->cookies', 'session(', '->session()', 'Session::', 'getClientIp', 'X-Forwarded'];
    $files = collect(File::allFiles(app_path('Modules/Complaints')))->map(fn (SplFileInfo $file): string => $file->getPathname());

    foreach ($files as $path) {
        $source = (string) file_get_contents($path);

        foreach ($forbidden as $pattern) {
            expect($source)->not->toContain($pattern, "{$path} reads «{$pattern}»");
        }

        if (! str_ends_with($path, 'AddressKey.php')) {
            expect($source)->not->toContain('->ip()', "{$path} reads the address");
        }
    }
});

it('has no tool that watches the requests of the channel installed, and no tool of Humi that reads a complaint', function (): void {
    $composer = (string) file_get_contents(base_path('composer.json'));

    foreach (['laravel/telescope', 'laravel/pulse', 'laravel/nightwatch', 'sentry/sentry', 'bugsnag', 'rollbar'] as $watcher) {
        expect($composer)->not->toContain($watcher, "{$watcher} would record the requests of /c/*: the channel's privacy must be revisited first");
    }
    foreach (File::allFiles(app_path('Modules/Assistant')) as $file) {
        expect(strtolower((string) file_get_contents($file->getPathname())))->not->toContain('complaint');
    }
});

it('searches nothing of a complaint from the global search: the palette lists pages, never cases', function (): void {
    $source = (string) file_get_contents(base_path('resources/js/lib/commands.ts'));

    expect($source)->not->toContain('complaint');
});
