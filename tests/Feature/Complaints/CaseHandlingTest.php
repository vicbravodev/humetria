<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\AcknowledgeComplaint;
use App\Modules\Complaints\Actions\AddInternalNote;
use App\Modules\Complaints\Actions\CloseCase;
use App\Modules\Complaints\Actions\RecordCaseExport;
use App\Modules\Complaints\Actions\RecuseFromCase;
use App\Modules\Complaints\Actions\ReopenCase;
use App\Modules\Complaints\Actions\SetLegalHold;
use App\Modules\Complaints\Actions\ShareCase;
use App\Modules\Complaints\Actions\StartReview;
use App\Modules\Complaints\Actions\WriteToReporter;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintEntry;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AcknowledgeComplaint::class, StartReview::class, WriteToReporter::class, AddInternalNote::class, ShareCase::class, RecuseFromCase::class, SetLegalHold::class, CloseCase::class, ReopenCase::class, RecordCaseExport::class);

const NOTE = 'Hablé con el jefe de turno y negó los hechos';
const REPLY = 'Gracias por avisarnos; revisaremos lo que cuenta';

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->outsider = memberOf($this->tenant, TenantRole::Complaints);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    foreach ([$this->owner, $this->hr, $this->outsider] as $person) {
        ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $person->id]);
    }
    $this->complaint = Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'respondent_kind' => RespondentKind::Coworker, 'received_on' => '2026-11-18']);
    ComplaintAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'user_id' => $this->owner->id]);
    workIn($this->tenant);
});

function caseUrl(object $test, string $name, ?Complaint $complaint = null): string
{
    return route("tenant.complaints.cases.{$name}", [$test->tenant, ($complaint ?? $test->complaint)->id]);
}

/** @return list<string> */
function entryKinds(object $test): array
{
    return ComplaintEntry::query()->where('complaint_id', $test->complaint->id)->orderBy('position')->get()->map(fn (ComplaintEntry $entry): string => $entry->kind)->all();
}

it('acknowledges a case: the day it was, an entry and a note to the person, with audit and log', function (): void {
    Log::spy();

    $this->actingAs($this->owner)->post(caseUrl($this, 'acknowledge'))->assertRedirect(caseUrl($this, 'show'));

    expect($this->complaint->refresh()->acknowledged_at?->toDateTimeString())->toBe('2026-11-20 18:00:00');
    expect(entryKinds($this))->toBe(['status_change', 'handler_message']);
    expect(AuditEntry::query()->where('event', 'complaint.acknowledged')->sole()->subject_id)->toBe($this->complaint->id);
    Log::shouldHaveReceived('info')->with('complaints.acknowledged', ['complaint_id' => $this->complaint->id])->once();
});

it('acknowledges only once', function (): void {
    $this->actingAs($this->owner)->post(caseUrl($this, 'acknowledge'));
    $this->actingAs($this->owner)->post(caseUrl($this, 'acknowledge'));

    expect(ComplaintEntry::query()->count())->toBe(2);
});

it('moves a case to review, acknowledging it if nobody did', function (): void {
    $this->actingAs($this->owner)->post(caseUrl($this, 'review'))->assertRedirect();

    $complaint = $this->complaint->refresh();
    expect($complaint->status->value)->toBe('in_review')->and($complaint->acknowledged_at)->not->toBeNull();
    expect(AuditEntry::query()->where('event', 'complaint.status_changed')->sole()->metadata)->toEqual(['from' => 'new', 'to' => 'in_review']);
    expect(ComplaintEntry::query()->where('kind', 'status_change')->get()->map(fn (ComplaintEntry $entry): ?array => $entry->meta)->all())->toContainEqual(['from' => 'new', 'to' => 'in_review']);
});

it('writes to the person, in an entry only the thread shows, with the text encrypted', function (): void {
    Log::spy();

    $this->actingAs($this->owner)->post(caseUrl($this, 'messages.store'), ['body' => REPLY])->assertRedirect();

    $entry = ComplaintEntry::query()->sole();
    expect($entry->kind)->toBe('handler_message')->and($entry->body)->toBe(REPLY)->and($entry->author_id)->toBe($this->owner->id)
        ->and($entry->position)->toBe(1)->and($entry->occurred_at?->toDateTimeString())->toBe('2026-11-20 18:00:00')->and($entry->occurred_on)->toBeNull();
    expect(json_encode((array) DB::table('complaint_entries')->first()))->not->toContain('revisaremos');
    Log::shouldHaveReceived('info')->with('complaints.message_sent', ['complaint_id' => $this->complaint->id])->once();
    expect(AuditEntry::query()->where('event', 'complaint.message_sent')->sole()->metadata)->toBe([])
        ->and(json_encode(AuditEntry::query()->get()->toArray()))->not->toContain('revisaremos');
});

it('keeps an internal note out of what the person reads, and out of the logs', function (): void {
    Log::spy();

    $this->actingAs($this->owner)->post(caseUrl($this, 'notes.store'), ['body' => NOTE])->assertRedirect();

    $entry = ComplaintEntry::query()->sole();
    expect($entry->kind)->toBe('internal_note')->and($entry->body)->toBe(NOTE);
    Log::shouldHaveReceived('info')->with('complaints.note_added', ['complaint_id' => $this->complaint->id])->once();
    expect(json_encode(AuditEntry::query()->get()->toArray()))->not->toContain('jefe de turno');
});

it('numbers the entries of a case in order, one after the other', function (): void {
    foreach (['a', 'b', 'c'] as $text) {
        $this->actingAs($this->owner)->post(caseUrl($this, 'notes.store'), ['body' => "Nota {$text}"]);
    }
    $other = Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id]);
    ComplaintAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $other->id, 'user_id' => $this->owner->id]);
    $this->actingAs($this->owner)->post(caseUrl($this, 'notes.store', $other), ['body' => 'Otra']);

    expect(ComplaintEntry::query()->where('complaint_id', $this->complaint->id)->orderBy('position')->pluck('position')->all())->toBe([1, 2, 3])
        ->and(ComplaintEntry::query()->where('complaint_id', $other->id)->value('position'))->toBe(1);
});

it('checks the length of what is written at its exact limits', function (int $length, bool $valid): void {
    $response = $this->actingAs($this->owner)->post(caseUrl($this, 'messages.store'), ['body' => str_repeat('a', $length)]);

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('body');
})->with([[0, false], [1, true], [5000, true], [5001, false]]);

it('refuses to write or note on a closed case until it is reopened', function (): void {
    $this->complaint->forceFill(['status' => 'closed', 'resolution' => 'measures_taken', 'closed_at' => now(), 'closed_on' => '2026-11-19', 'acknowledged_at' => now()])->save();

    $this->actingAs($this->owner)->post(caseUrl($this, 'messages.store'), ['body' => REPLY])->assertSessionHasErrors('body');
    $this->actingAs($this->owner)->post(caseUrl($this, 'notes.store'), ['body' => NOTE])->assertSessionHasErrors('body');
    expect(ComplaintEntry::query()->count())->toBe(0);

    $this->actingAs($this->owner)->post(caseUrl($this, 'reopen'))->assertRedirect();
    $this->actingAs($this->owner)->post(caseUrl($this, 'messages.store'), ['body' => REPLY])->assertSessionHasNoErrors();
    expect($this->complaint->refresh())->status->value->toBe('in_review')->resolution->toBeNull()->closed_on->toBeNull()->closed_at->toBeNull();
});

it('closes a case with the resolution the handler chose, the day it was, and tells the person in general words', function (): void {
    Log::spy();

    $this->actingAs($this->owner)->post(caseUrl($this, 'close'), ['resolution' => 'measures_taken'])->assertRedirect();

    $complaint = $this->complaint->refresh();
    expect($complaint->status->value)->toBe('closed')->and($complaint->resolution?->value)->toBe('measures_taken')
        ->and($complaint->closed_on?->toDateString())->toBe('2026-11-20')->and($complaint->closed_at?->toDateTimeString())->toBe('2026-11-20 18:00:00')
        ->and($complaint->acknowledged_at)->not->toBeNull();
    expect(AuditEntry::query()->where('event', 'complaint.closed')->sole()->metadata)->toBe(['resolution' => 'measures_taken']);
    Log::shouldHaveReceived('info')->with('complaints.closed', ['complaint_id' => $this->complaint->id, 'resolution' => 'measures_taken'])->once();
    expect(entryKinds($this))->toContain('status_change');
});

it('does not close a case with a resolution that is not a handler\'s to give', function (string $resolution): void {
    $this->actingAs($this->owner)->post(caseUrl($this, 'close'), ['resolution' => $resolution])->assertSessionHasErrors('resolution');

    expect($this->complaint->refresh()->status->value)->toBe('new');
})->with(['withdrawn', 'nonsense', '']);

it('shares a case with another handler the conflict leaves, who then opens it', function (): void {
    Log::spy();

    $this->actingAs($this->owner)->post(caseUrl($this, 'share'), ['user_id' => $this->hr->id])->assertRedirect();

    $assignment = ComplaintAssignment::query()->where('complaint_id', $this->complaint->id)->where('user_id', $this->hr->id)->sole();
    expect($assignment->assigned_by)->toBe($this->owner->id)->and($assignment->ended_on)->toBeNull();
    expect(AuditEntry::query()->where('event', 'complaint.assigned')->sole()->metadata)->toBe(['user_id' => $this->hr->id]);
    expect(entryKinds($this))->toBe(['assignment_change']);
    $this->actingAs($this->hr)->get(caseUrl($this, 'show'))->assertOk();
    Log::shouldHaveReceived('info')->with('complaints.assignment_changed', ['complaint_id' => $this->complaint->id, 'reason' => 'shared'])->once();
});

it('does not share a case with someone the conflict leaves out, who is not a handler, or already has it', function (string $who, string $kind): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->complaint->forceFill(['respondent_kind' => $kind])->save();
    $user = match ($who) {
        'hr' => $this->hr,
        'viewer' => $viewer,
        'owner' => $this->owner,
        'nobody' => (object) ['id' => 0],
    };

    $this->actingAs($this->owner)->post(caseUrl($this, 'share'), ['user_id' => $user->id])->assertSessionHasErrors('user_id');

    expect(ComplaintAssignment::query()->where('complaint_id', $this->complaint->id)->count())->toBe(1);
})->with([
    'HR on a case against HR' => ['hr', 'hr'],
    'a viewer' => ['viewer', 'coworker'],
    'the one who already has it' => ['owner', 'coworker'],
    'nobody' => ['nobody', 'coworker'],
]);

it('lets a handler step aside, which ends their assignment and leaves the case with the others', function (): void {
    Log::spy();
    ComplaintAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'user_id' => $this->hr->id]);

    $this->actingAs($this->owner)->post(caseUrl($this, 'recuse'))->assertRedirect(route('tenant.complaints.index', $this->tenant));

    $ended = ComplaintAssignment::query()->where('complaint_id', $this->complaint->id)->where('user_id', $this->owner->id)->sole();
    expect($ended->end_reason)->toBe('recused')->and($ended->ended_on?->toDateString())->toBe('2026-11-20');
    expect(entryKinds($this))->toBe(['recusal']);
    $this->actingAs($this->owner)->get(caseUrl($this, 'show'))->assertNotFound();
    $this->actingAs($this->hr)->get(caseUrl($this, 'show'))->assertOk();
    expect(AuditEntry::query()->where('event', 'complaint.recused')->sole()->actor_id)->toBe($this->owner->id);
    Log::shouldHaveReceived('info')->with('complaints.recused', ['complaint_id' => $this->complaint->id])->once();
});

it('leaves a case nobody holds in the count when its last handler steps aside', function (): void {
    $this->actingAs($this->owner)->post(caseUrl($this, 'recuse'))->assertRedirect();

    $this->actingAs($this->hr)->get(route('tenant.complaints.channel.show', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('unassigned', 1));
});

it('sets and lifts a legal hold, each audited', function (): void {
    $this->actingAs($this->owner)->post(caseUrl($this, 'hold'), ['on_hold' => '1'])->assertRedirect();
    expect($this->complaint->refresh()->on_hold)->toBeTrue()
        ->and(AuditEntry::query()->where('event', 'complaint.hold_set')->count())->toBe(1);

    $this->actingAs($this->owner)->post(caseUrl($this, 'hold'), ['on_hold' => '0'])->assertRedirect();
    expect($this->complaint->refresh()->on_hold)->toBeFalse()
        ->and(AuditEntry::query()->where('event', 'complaint.hold_lifted')->count())->toBe(1);
});

it('records that a case was printed', function (): void {
    Log::spy();

    $this->actingAs($this->owner)->post(caseUrl($this, 'export'))->assertRedirect();

    expect(AuditEntry::query()->where('event', 'complaint.exported')->sole()->subject_id)->toBe($this->complaint->id);
    Log::shouldHaveReceived('info')->with('complaints.case_exported', ['complaint_id' => $this->complaint->id])->once();
});

it('answers 404 to every action on a case to someone who does not have it, and 403 to a viewer', function (string $name, array $data): void {
    $stranger = memberOf($this->tenant, TenantRole::Hr);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($stranger)->post(caseUrl($this, $name), $data)->assertNotFound();
    $this->actingAs($viewer)->post(caseUrl($this, $name), $data)->assertForbidden();
    expect(ComplaintEntry::query()->count())->toBe(0);
})->with([
    'acknowledge' => ['acknowledge', []],
    'review' => ['review', []],
    'a message' => ['messages.store', ['body' => REPLY]],
    'a note' => ['notes.store', ['body' => NOTE]],
    'share' => ['share', ['user_id' => 1]],
    'recuse' => ['recuse', []],
    'hold' => ['hold', ['on_hold' => '1']],
    'close' => ['close', ['resolution' => 'measures_taken']],
    'reopen' => ['reopen', []],
    'export' => ['export', []],
]);

it('shows the thread, the actions and the people who could be given the case', function (): void {
    ComplaintEntry::factory()->fromReporter('2026-11-19')->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'position' => 1, 'body' => 'Sigue pasando']);
    $this->actingAs($this->owner)->post(caseUrl($this, 'messages.store'), ['body' => REPLY]);
    $this->actingAs($this->owner)->post(caseUrl($this, 'notes.store'), ['body' => NOTE]);

    $this->actingAs($this->owner)->get(caseUrl($this, 'show'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 3)
            ->where('entries.data.0.kind', 'internal_note')
            ->where('entries.data.0.author', $this->owner->name)
            ->where('entries.data.0.text', NOTE)
            ->where('entries.data.1.kind', 'handler_message')
            ->where('entries.data.2.kind', 'reporter_message')
            ->where('entries.data.2.author', 'La persona')
            ->where('entries.data.2.when', '2026-11-19')
            ->where('can', ['acknowledge' => true, 'review' => true, 'write' => true, 'close' => true, 'reopen' => false])
            ->where('candidates', collect([$this->hr, $this->outsider])->map(fn ($user): array => ['value' => (string) $user->id, 'label' => $user->name])->sortBy('label')->values()->all()));
});

it('pages the thread in the server, the newest entry first', function (): void {
    foreach (range(1, 21) as $n) {
        ComplaintEntry::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'position' => $n, 'kind' => 'internal_note', 'body' => "Nota {$n}", 'author_id' => $this->owner->id]);
    }

    $this->actingAs($this->owner)->get(caseUrl($this, 'show'))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 20)->where('entries.total', 21)->where('entries.data.0.text', 'Nota 21'));
});

it('keeps a company\'s entries out of another\'s', function (): void {
    $theirs = Tenant::factory()->create();
    $channel = ComplaintChannel::factory()->forTenant($theirs->id)->active()->create();
    $complaint = Complaint::factory()->create(['tenant_id' => $theirs->id, 'channel_id' => $channel->id]);
    $entry = ComplaintEntry::factory()->create(['tenant_id' => $theirs->id, 'complaint_id' => $complaint->id]);

    expect(ComplaintEntry::query()->find($entry->id))->toBeNull();
});

it('keeps a note of the person with a day and never an hour', function (): void {
    expect(fn () => ComplaintEntry::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'kind' => 'reporter_message', 'occurred_at' => now(), 'occurred_on' => '2026-11-19']))
        ->toThrow(Illuminate\Database\QueryException::class);
    expect(fn () => ComplaintEntry::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $this->complaint->id, 'kind' => 'internal_note', 'occurred_at' => null, 'occurred_on' => '2026-11-19']))
        ->toThrow(Illuminate\Database\QueryException::class);
});
