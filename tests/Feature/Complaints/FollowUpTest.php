<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\OpenFollowUp;
use App\Modules\Complaints\Actions\SendReporterMessage;
use App\Modules\Complaints\Actions\WithdrawComplaint;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintEntry;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(OpenFollowUp::class, SendReporterMessage::class, WithdrawComplaint::class);

const REFERENCE = 'ABCD2345';
const KEY = 'KEYK1234KEYK5678KEYK';

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme Industrial']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    $this->complaint = Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'reference' => REFERENCE, 'access_key_hash' => hash('sha256', KEY), 'description' => 'Mi jefe me grita.']);
    ComplaintAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'user_id' => $this->owner->id]);
    workIn($this->tenant);
});

/** @param array<string, mixed> $data */
function followUp(object $test, string $route, array $data = [], ?string $code = null): Illuminate\Testing\TestResponse
{
    $data = ['reference' => REFERENCE, 'key' => KEY, ...$data];

    return $test->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->postJson('/c/'.($code ?? $test->channel->access_code).'/follow-up'.$route, $data);
}

it('shows the form that asks for the reference and the key, without a session or cookie', function (): void {
    config(['session.driver' => 'database']);

    $response = $this->get('/c/'.$this->channel->access_code.'/follow-up')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('complaints-public/follow-up')->where('company', 'Acme Industrial')->where('code', $this->channel->access_code));

    expect($response->headers->getCookies())->toBe([])->and(DB::table('sessions')->count())->toBe(0);
});

it('shows that form even while the channel is paused, so the people with a case can still follow it', function (): void {
    $this->channel->forceFill(['paused_at' => now()])->save();

    $this->get('/c/'.$this->channel->access_code.'/follow-up')->assertOk()->assertInertia(fn (Assert $page) => $page->component('complaints-public/follow-up'));
});

it('answers a code that serves nobody with the same bare 404 page', function (): void {
    $this->get('/c/ZZZZZZZZZZZZZZZZ/follow-up')->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('complaints-public/unavailable')->where('reason', 'invalid'));
    followUp($this, '', [], 'ZZZZZZZZZZZZZZZZ')->assertNotFound();
});

it('opens a case for who has its reference and its key, telling them only what they may read', function (): void {
    ComplaintEntry::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'position' => 1, 'kind' => 'status_change', 'meta' => ['acknowledged' => true], 'author_id' => $this->owner->id]);
    ComplaintEntry::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'position' => 2, 'kind' => 'handler_message', 'body' => 'Revisamos su caso.', 'author_id' => $this->owner->id]);
    ComplaintEntry::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'position' => 3, 'kind' => 'internal_note', 'body' => 'Nota interna secreta', 'author_id' => $this->owner->id]);
    ComplaintEntry::factory()->fromReporter('2026-11-20')->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'position' => 4, 'body' => 'Sigue pasando.']);

    $response = followUp($this, '')->assertOk();

    expect($response->json())->toMatchArray(['status' => 'received', 'statusLabel' => 'Recibida', 'resolution' => null, 'handlers' => 1, 'canReply' => true, 'canWithdraw' => true])
        ->and($response->json('entries.data'))->toBe([
            ['from' => 'reporter', 'text' => 'Sigue pasando.', 'day' => '2026-11-20'],
            ['from' => 'handlers', 'text' => 'Revisamos su caso.', 'day' => '2026-11-20'],
        ])
        ->and($response->headers->getCookies())->toBe([]);
    expect($response->getContent())->not->toContain('secreta')->not->toContain($this->owner->name)->not->toContain('access_key_hash')->not->toContain('Mi jefe');
});

it('tells the state in general words: received, in review or closed with its resolution', function (string $status, string $label, ?string $resolution, ?string $words): void {
    $this->complaint->forceFill(['status' => $status, 'resolution' => $resolution, 'acknowledged_at' => $status === 'new' ? null : now(), 'closed_on' => $status === 'closed' ? '2026-11-20' : null, 'closed_at' => $status === 'closed' ? now() : null])->save();

    $json = followUp($this, '')->assertOk()->json();

    expect($json['status'])->toBe($status === 'new' ? 'received' : $status)->and($json['statusLabel'])->toBe($label)->and($json['resolution'])->toBe($words)->and($json['canReply'])->toBe($status !== 'closed');
})->with([
    'new' => ['new', 'Recibida', null, null],
    'in review' => ['in_review', 'En revisión', null, null],
    'closed with measures' => ['closed', 'Cerrada', 'measures_taken', 'La empresa tomó medidas.'],
    'closed without action' => ['closed', 'Cerrada', 'no_action_needed', 'La empresa revisó el caso y no encontró que requiriera medidas.'],
]);

it('answers the same, in the same words, to a wrong key and to a reference that does not exist', function (): void {
    $wrongKey = followUp($this, '', ['key' => 'WRONGWRONGWRONGWRONG']);
    $wrongReference = followUp($this, '', ['reference' => 'ZZZZZZZZ']);
    $otherCompany = (function () {
        $other = Tenant::factory()->create();
        $channel = ComplaintChannel::factory()->forTenant($other->id)->active()->create();
        Complaint::factory()->create(['tenant_id' => $other->id, 'channel_id' => $channel->id, 'reference' => 'OTHER234', 'access_key_hash' => hash('sha256', 'OTHERKEY')]);

        return followUp($this, '', ['reference' => 'OTHER234', 'key' => 'OTHERKEY']);
    })();

    foreach ([$wrongKey, $wrongReference, $otherCompany] as $response) {
        $response->assertUnprocessable();
    }
    expect($wrongKey->getContent())->toBe($wrongReference->getContent())->toBe($otherCompany->getContent());
    expect($wrongKey->json('message'))->toBe('La referencia o la clave no coinciden.')
        ->and($wrongKey->headers->getCookies())->toBe([]);
});

it('forgives the case, the spaces and the dashes of what was typed', function (): void {
    followUp($this, '', ['reference' => ' abcd-2345 ', 'key' => 'keyk 1234 keyk 5678 keyk'])->assertOk();
});

it('opens with a paused channel but not with an unknown code or a blocked company', function (): void {
    $this->channel->forceFill(['paused_at' => now()])->save();
    followUp($this, '')->assertOk();

    $blocked = Tenant::factory()->deactivated()->create();
    $channel = ComplaintChannel::factory()->forTenant($blocked->id)->active()->create();
    followUp($this, '', [], $channel->access_code)->assertNotFound();
});

it('takes a message of the person with only the day, encrypted, with a log line that names no case', function (): void {
    Log::spy();

    followUp($this, '/messages', ['body' => 'Sigue pasando esta semana, y ahora lo hizo delante de todos.'])->assertCreated();

    $entry = ComplaintEntry::query()->sole();
    expect($entry->kind)->toBe('reporter_message')->and($entry->author_id)->toBeNull()->and($entry->occurred_at)->toBeNull()
        ->and($entry->occurred_on?->toDateString())->toBe('2026-11-20')->and($entry->position)->toBe(1)
        ->and($entry->body)->toBe('Sigue pasando esta semana, y ahora lo hizo delante de todos.');
    expect(json_encode((array) DB::table('complaint_entries')->first()))->not->toContain('delante de todos');
    Log::shouldHaveReceived('info')->with('complaints.reporter_message_sent', ['channel_id' => $this->channel->id])->once();
    expect(AuditEntry::query()->count())->toBe(0);
});

it('checks the length of the message at its exact limits', function (int $length, bool $valid): void {
    $response = followUp($this, '/messages', ['body' => str_repeat('a', $length)]);

    $valid ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors('body');
})->with([[0, false], [1, true], [5000, true], [5001, false]]);

it('refuses a message of someone who does not have the key, and one on a closed case', function (): void {
    followUp($this, '/messages', ['body' => 'Hola', 'key' => 'WRONGWRONGWRONGWRONG'])->assertUnprocessable();
    expect(ComplaintEntry::query()->count())->toBe(0);

    $this->complaint->forceFill(['status' => 'closed', 'resolution' => 'measures_taken', 'closed_on' => '2026-11-20', 'closed_at' => now(), 'acknowledged_at' => now()])->save();
    followUp($this, '/messages', ['body' => 'Hola'])->assertUnprocessable()->assertJsonValidationErrors('body');
    expect(ComplaintEntry::query()->count())->toBe(0);
});

it('withdraws the case without keeping the hour, and the handlers read that the person did', function (): void {
    Log::spy();

    followUp($this, '/withdraw')->assertOk()->assertJsonPath('status', 'closed')->assertJsonPath('resolution', 'Usted retiró el caso.');

    $complaint = $this->complaint->refresh();
    expect($complaint->status->value)->toBe('closed')->and($complaint->resolution?->value)->toBe('withdrawn')
        ->and($complaint->closed_on?->toDateString())->toBe('2026-11-20')->and($complaint->closed_at)->toBeNull();
    $entry = ComplaintEntry::query()->sole();
    expect($entry->kind)->toBe('reporter_message')->and($entry->body)->toBe('La persona retiró el caso.')->and($entry->occurred_at)->toBeNull();
    Log::shouldHaveReceived('info')->with('complaints.withdrawn', ['channel_id' => $this->channel->id])->once();
    expect(AuditEntry::query()->count())->toBe(0);
    followUp($this, '/messages', ['body' => 'Hola'])->assertUnprocessable();
});

it('lets a handler open a withdrawn case again', function (): void {
    followUp($this, '/withdraw')->assertOk();

    $this->actingAs($this->owner)->post(route('tenant.complaints.cases.reopen', [$this->tenant, $this->complaint->id]))->assertRedirect();

    expect($this->complaint->refresh())->status->value->toBe('in_review')->resolution->toBeNull()->closed_on->toBeNull();
});

it('does not withdraw a case already closed', function (): void {
    $this->complaint->forceFill(['status' => 'closed', 'resolution' => 'measures_taken', 'closed_on' => '2026-11-20', 'closed_at' => now(), 'acknowledged_at' => now()])->save();

    followUp($this, '/withdraw')->assertUnprocessable();

    expect($this->complaint->refresh()->resolution?->value)->toBe('measures_taken');
});

it('pages the thread of the person in the server, the newest first', function (): void {
    foreach (range(1, 21) as $n) {
        ComplaintEntry::factory()->fromReporter('2026-11-20')->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'position' => $n, 'body' => "Mensaje {$n}"]);
    }

    $first = followUp($this, '')->json('entries');
    $second = followUp($this, '', ['page' => 2])->json('entries');

    expect($first['data'])->toHaveCount(20)->and($first['data'][0]['text'])->toBe('Mensaje 21')->and($first['total'])->toBe(21)->and($first['last_page'])->toBe(2)
        ->and($second['data'])->toHaveCount(1)->and($second['data'][0]['text'])->toBe('Mensaje 1');
});

it('stops the thirty-first consultation of an hour from one address, and nobody else\'s', function (): void {
    foreach (range(1, 30) as $ignored) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->postJson('/c/'.$this->channel->access_code.'/follow-up', ['reference' => REFERENCE, 'key' => KEY])->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->postJson('/c/'.$this->channel->access_code.'/follow-up', ['reference' => REFERENCE, 'key' => KEY])->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])->postJson('/c/'.$this->channel->access_code.'/follow-up', ['reference' => REFERENCE, 'key' => KEY])->assertOk();
});

it('refuses a request from another site', function (): void {
    $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])->postJson('/c/'.$this->channel->access_code.'/follow-up', ['reference' => REFERENCE, 'key' => KEY])->assertForbidden();
});

it('keeps the reference and the key out of every address and every database table', function (): void {
    config(['session.driver' => 'database', 'cache.default' => 'database']);

    followUp($this, '')->assertOk();

    expect(DB::table('sessions')->count())->toBe(0)
        ->and(DB::table('cache')->get()->contains(fn (object $row): bool => str_contains(json_encode($row), KEY)))->toBeFalse();
});
