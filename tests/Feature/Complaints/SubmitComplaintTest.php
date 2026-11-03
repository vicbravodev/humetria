<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\SubmitComplaint;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Notifications\DailyCapReached;
use App\Modules\Complaints\Support\ReferenceSource;
use App\Modules\Complaints\Support\TicketSigner;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

mutates(SubmitComplaint::class, TicketSigner::class);

const SECRET_STORY = 'El jefe de turno me grita todos los días frente a mis compañeros y me amenaza con despedirme';
const IP = '203.0.113.9';

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 03:30:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    workIn($this->tenant);
    $this->ticket = resolve(TicketSigner::class)->issue($this->channel->access_code);
    Carbon::setTestNow('2026-11-20 03:30:10');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function complaintForm(object $test, array $overrides = []): array
{
    return [
        'ticket' => $test->ticket,
        'type' => 'workplace_violence',
        'category' => 'mistreatment',
        'description' => SECRET_STORY,
        'respondent_kind' => 'direct_supervisor',
        'identity_mode' => 'anonymous',
        ...$overrides,
    ];
}

function submitComplaint(object $test, array $data, ?string $code = null): Illuminate\Testing\TestResponse
{
    return $test->withServerVariables(['REMOTE_ADDR' => IP])->withHeaders(['User-Agent' => 'MarkerBrowser/9.9'])->postJson('/c/'.($code ?? $test->channel->access_code), $data);
}

it('receives an anonymous complaint and hands back a reference and a key, once', function (): void {
    $response = submitComplaint($this, complaintForm($this))->assertCreated();

    $reference = $response->json('reference');
    $key = $response->json('key');
    expect($reference)->toMatch('/^[0-9A-HJKMNP-TV-Z]{8}$/')
        ->and($key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{20}$/');

    $complaint = Complaint::query()->sole();
    expect($complaint->reference)->toBe($reference)
        ->and($complaint->access_key_hash)->toBe(hash('sha256', $key))
        ->and($complaint->type->value)->toBe('workplace_violence')
        ->and($complaint->category?->value)->toBe('mistreatment')
        ->and($complaint->respondent_kind->value)->toBe('direct_supervisor')
        ->and($complaint->identity_mode->value)->toBe('anonymous')
        ->and($complaint->status->value)->toBe('new')
        ->and($complaint->description)->toBe(SECRET_STORY)
        ->and($complaint->received_on->toDateString())->toBe('2026-11-19')
        ->and($complaint->on_hold)->toBeFalse()
        ->and($complaint->channel_id)->toBe($this->channel->id);
});

it('keeps the text encrypted and the key only as a hash', function (): void {
    $key = submitComplaint($this, complaintForm($this, ['respondent_text' => 'Se llama Don Remigio']))->assertCreated()->json('key');
    $raw = (array) DB::table('complaints')->first();

    expect(json_encode($raw))->not->toContain('Remigio')->not->toContain('amenaza')->not->toContain($key);
    expect($raw['description'])->not->toBe(SECRET_STORY)
        ->and(Complaint::query()->sole()->respondent_text)->toBe('Se llama Don Remigio');
});

it('stores no moment of the submission, only the day, with a random identifier', function (): void {
    submitComplaint($this, complaintForm($this))->assertCreated();

    expect(Schema::hasColumns('complaints', ['created_at', 'updated_at']))->toBeFalse()
        ->and(Schema::hasColumn('complaints', 'created_at'))->toBeFalse()
        ->and(Schema::hasColumn('complaints', 'updated_at'))->toBeFalse();
    expect(Complaint::query()->sole()->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('leaves no trace of the address, the browser or the story anywhere in the database', function (): void {
    config(['session.driver' => 'database', 'cache.default' => 'database']);

    $key = submitComplaint($this, complaintForm($this))->assertCreated()->json('key');

    foreach ([IP, 'MarkerBrowser', 'jefe de turno', $key] as $needle) {
        $found = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))
            ->filter(fn (object $table): bool => DB::selectOne('select count(*) as found from "'.$table->table_name.'" t where t::text like ?', ['%'.$needle.'%'])->found > 0)
            ->pluck('table_name')->all();

        expect($found)->toBe([], "«{$needle}» found in: ".implode(', ', $found));
    }
});

it('logs that a complaint arrived, with nothing that identifies it, and writes no audit entry', function (): void {
    Log::spy();

    $response = submitComplaint($this, complaintForm($this))->assertCreated();

    Log::shouldHaveReceived('info')->with('complaints.submitted', ['channel_id' => $this->channel->id, 'type' => 'workplace_violence', 'identity_mode' => 'anonymous'])->once();
    expect(AuditEntry::query()->count())->toBe(0);
    expect($response->headers->getCookies())->toBe([]);
});

it('checks the length of the story at its exact limits', function (int $length, bool $valid): void {
    $response = submitComplaint($this, complaintForm($this, ['description' => str_repeat('a', $length)]));

    $valid ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors('description');
})->with([[19, false], [20, true], [5000, true], [5001, false]]);

it('does not echo what was written when it refuses it', function (): void {
    $response = submitComplaint($this, complaintForm($this, ['description' => 'corta']))->assertUnprocessable();

    expect($response->getContent())->not->toContain('corta');
});

it('refuses what the form does not offer', function (array $override, string $field): void {
    submitComplaint($this, complaintForm($this, $override))->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Complaint::query()->count())->toBe(0);
})->with([
    'a type that does not exist' => [['type' => 'gossip'], 'type'],
    'a kind that does not exist' => [['respondent_kind' => 'the moon'], 'respondent_kind'],
    'no kind' => [['respondent_kind' => ''], 'respondent_kind'],
    'a category of another type' => [['category' => 'workload_or_hours'], 'category'],
    'a category for the traumatic notice' => [['type' => 'traumatic_event_notice', 'category' => 'harassment'], 'category'],
    'a free text too long' => [['respondent_text' => str_repeat('x', 1001)], 'respondent_text'],
    'a date in the future' => [['occurred_on' => '2026-11-21'], 'occurred_on'],
    'a date that is not one' => [['occurred_on' => 'ayer'], 'occurred_on'],
    'an identity that is not offered' => [['identity_mode' => 'everyone'], 'identity_mode'],
]);

it('accepts a category of its type, none at all, and the day it happened', function (array $override): void {
    submitComplaint($this, complaintForm($this, $override))->assertCreated();
})->with([
    'a practice with its category' => [['type' => 'unfavorable_practice', 'category' => 'discrimination']],
    'a violence without category' => [['category' => null]],
    'a day already gone' => [['occurred_on' => '2026-11-19']],
    'a free text of exactly the limit' => [['respondent_text' => str_repeat('x', 1000)]],
]);

it('accepts a work center of the company and refuses one that is not', function (): void {
    $center = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    $theirs = WorkCenter::factory()->create();
    $archived = WorkCenter::factory()->archived()->create(['tenant_id' => $this->tenant->id]);

    submitComplaint($this, complaintForm($this, ['work_center_id' => $center->id]))->assertCreated();
    expect(Complaint::query()->sole()->work_center_id)->toBe($center->id);

    foreach ([$theirs->id, $archived->id, 'not-a-uuid'] as $bad) {
        submitComplaint($this, complaintForm($this, ['work_center_id' => $bad]))->assertUnprocessable()->assertJsonValidationErrors('work_center_id');
    }
});

it('refuses a complaint against a role nobody independent of it would receive', function (): void {
    submitComplaint($this, complaintForm($this, ['respondent_kind' => 'management']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('respondent_kind');

    expect(Complaint::query()->count())->toBe(0);

    $hr = memberOf($this->tenant, TenantRole::Hr);
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $hr->id]);
    submitComplaint($this, complaintForm($this, ['respondent_kind' => 'management']))->assertCreated();
});

it('asks for the ticket the page gave, for this channel, at the right age', function (string $case, bool $valid): void {
    $signer = resolve(TicketSigner::class);
    $other = ComplaintChannel::factory()->forTenant(Tenant::factory()->create()->id)->active()->create();
    Carbon::setTestNow('2026-11-20 10:00:00');
    $issued = $signer->issue($this->channel->access_code);

    $ticket = match ($case) {
        'missing' => '',
        'garbage' => 'garbage',
        'tampered' => substr($issued, 0, -2).'zz',
        'another channel' => $signer->issue($other->access_code),
        'too soon (2 s)' => (function () use ($issued) {
            Carbon::setTestNow('2026-11-20 10:00:02');

            return $issued;
        })(),
        'exactly 3 s' => (function () use ($issued) {
            Carbon::setTestNow('2026-11-20 10:00:03');

            return $issued;
        })(),
        'exactly 2 h' => (function () use ($issued) {
            Carbon::setTestNow('2026-11-20 12:00:00');

            return $issued;
        })(),
        'past 2 h' => (function () use ($issued) {
            Carbon::setTestNow('2026-11-20 12:00:01');

            return $issued;
        })(),
    };
    $response = submitComplaint($this, complaintForm($this, ['ticket' => $ticket]));

    $valid ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors('ticket');
})->with([
    ['missing', false], ['garbage', false], ['tampered', false], ['another channel', false],
    ['too soon (2 s)', false], ['exactly 3 s', true], ['exactly 2 h', true], ['past 2 h', false],
]);

it('answers a filled honeypot as if it worked, and saves nothing', function (): void {
    Log::spy();

    $response = submitComplaint($this, complaintForm($this, ['website' => 'http://spam.example']))->assertCreated();

    expect($response->json('reference'))->toMatch('/^[0-9A-HJKMNP-TV-Z]{8}$/')
        ->and($response->json('key'))->toMatch('/^[0-9A-HJKMNP-TV-Z]{20}$/')
        ->and(Complaint::query()->count())->toBe(0);
    Log::shouldHaveReceived('info')->with('complaints.spam_blocked', ['channel_id' => $this->channel->id, 'reason' => 'honeypot'])->once();
});

it('says the channel is paused and takes nothing, and that an unknown code is nothing', function (): void {
    submitComplaint($this, complaintForm($this), 'ZZZZZZZZZZZZZZZZ')->assertNotFound();

    $this->channel->forceFill(['paused_at' => now()])->save();
    submitComplaint($this, complaintForm($this))->assertStatus(503);

    expect(Complaint::query()->count())->toBe(0);
});

it('refuses a submission that comes from another site', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => IP])->withHeaders(['Sec-Fetch-Site' => 'cross-site'])
        ->postJson('/c/'.$this->channel->access_code, complaintForm($this))->assertForbidden();

    expect(Complaint::query()->count())->toBe(0);
});

it('stops the sixth submission of an hour from one address, and nobody else\'s', function (): void {
    foreach (range(1, 5) as $ignored) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->postJson('/c/'.$this->channel->access_code, complaintForm($this))->assertCreated();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->postJson('/c/'.$this->channel->access_code, complaintForm($this))->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])->postJson('/c/'.$this->channel->access_code, complaintForm($this))->assertCreated();

    expect(Complaint::query()->count())->toBe(6);
});

it('takes no more than the daily cap of the channel, and tells who manages it once', function (): void {
    Notification::fake();
    config(['complaints.rate_limits.daily_cap' => 2]);
    memberOf($this->tenant, TenantRole::Hr);
    Complaint::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'received_on' => '2026-11-19']);

    submitComplaint($this, complaintForm($this))->assertStatus(429)->assertJsonPath('message', 'El canal recibió muchas quejas hoy. Inténtelo de nuevo mañana.');
    submitComplaint($this, complaintForm($this))->assertStatus(429);

    expect(Complaint::query()->count())->toBe(2);
    Notification::assertSentOnDemandTimes(DailyCapReached::class, 2);
});

it('counts yesterday\'s complaints out of today\'s cap', function (): void {
    config(['complaints.rate_limits.daily_cap' => 2]);
    Complaint::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'received_on' => '2026-11-18']);

    submitComplaint($this, complaintForm($this))->assertCreated();
});

it('draws another reference when the first one is taken in the company', function (): void {
    Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'reference' => 'AAAAAAAA']);
    $this->app->instance(ReferenceSource::class, new class implements ReferenceSource
    {
        private int $calls = 0;

        public function next(): string
        {
            return ['AAAAAAAA', 'BBBBBBBB'][$this->calls++] ?? 'CCCCCCCC';
        }
    });

    $response = submitComplaint($this, complaintForm($this))->assertCreated();

    expect($response->json('reference'))->toBe('BBBBBBBB');
});

it('lets two companies use the same reference', function (): void {
    $other = Tenant::factory()->create();
    $channel = ComplaintChannel::factory()->forTenant($other->id)->active()->create();
    Complaint::factory()->create(['tenant_id' => $other->id, 'channel_id' => $channel->id, 'reference' => 'AAAAAAAA']);
    $this->app->instance(ReferenceSource::class, new class implements ReferenceSource
    {
        public function next(): string
        {
            return 'AAAAAAAA';
        }
    });

    submitComplaint($this, complaintForm($this))->assertCreated()->assertJsonPath('reference', 'AAAAAAAA');
});

it('keeps one company\'s complaints out of another\'s', function (): void {
    $other = Tenant::factory()->create();
    $theirs = Complaint::factory()->create(['tenant_id' => $other->id, 'channel_id' => ComplaintChannel::factory()->forTenant($other->id)->active()->create()->id]);
    submitComplaint($this, complaintForm($this))->assertCreated();

    expect(Complaint::query()->count())->toBe(1)
        ->and(Complaint::query()->find($theirs->id))->toBeNull();
});

it('keeps no key from a complaint to an employee, an invitation, a portal link, a campaign or an answer', function (): void {
    $targets = DB::select(<<<'SQL'
        select tc.table_name as owner, ccu.table_name as target
        from information_schema.table_constraints tc
        join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name and ccu.constraint_schema = tc.constraint_schema
        where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = 'public' and tc.table_name like 'complaint%'
        SQL);

    $reached = collect($targets)->pluck('target')->unique()->sort()->values()->all();

    expect($reached)->not->toContain('employees', 'participants', 'participant_instruments', 'portal_links', 'anonymous_responses', 'campaigns', 'trauma_screenings')
        ->and($reached)->toBe(['complaint_channels', 'complaints', 'tenants', 'users', 'work_centers']);
});

it('searches the company\'s work centers for the form, as options, and no other company\'s', function (): void {
    WorkCenter::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Planta Querétaro']);
    WorkCenter::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Planta Monterrey']);
    WorkCenter::factory()->create(['name' => 'Planta Querétaro Ajena']);

    $response = $this->getJson('/c/'.$this->channel->access_code.'/centers?q=quer')->assertOk();

    expect($response->json())->toBe([['value' => WorkCenter::query()->where('name', 'Planta Querétaro')->value('id'), 'label' => 'Planta Querétaro']])
        ->and($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('does not search work centers for a code that serves nobody or a paused channel', function (): void {
    $this->getJson('/c/ZZZZZZZZZZZZZZZZ/centers?q=a')->assertNotFound();

    $this->channel->forceFill(['paused_at' => now()])->save();
    $this->getJson('/c/'.$this->channel->access_code.'/centers?q=a')->assertStatus(503);
});
