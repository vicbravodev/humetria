<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\PurgeComplaints;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Notifications\NewsOnYourCase;
use App\Modules\Complaints\Support\ReporterNotifier;
use App\Modules\Complaints\Support\TicketSigner;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ReporterNotifier::class);

const WRITE_ONLY = 'rosa.privada@ejemplo.mx';

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

/** @param array<string, mixed> $overrides */
function withEmail(object $test, array $overrides = []): Illuminate\Testing\TestResponse
{
    return $test->postJson('/c/'.$test->channel->access_code, [
        'ticket' => $test->ticket, 'type' => 'workplace_violence', 'description' => 'Mi jefe me grita todos los días frente a mis compañeros.',
        'respondent_kind' => 'direct_supervisor', 'identity_mode' => 'anonymous', 'notify_email' => WRITE_ONLY, ...$overrides,
    ]);
}

/** A case that holds the address, assigned to the owner. */
function caseWithEmail(object $test): Complaint
{
    $complaint = Complaint::factory()->create(['tenant_id' => $test->tenant->id, 'channel_id' => $test->channel->id, 'reporter_email' => WRITE_ONLY, 'reference' => 'ABCD2345']);
    ComplaintAssignment::factory()->create(['tenant_id' => $test->tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $test->owner->id]);

    return $complaint;
}

it('keeps the address the person left for the notice, encrypted, on an anonymous complaint too', function (string $mode): void {
    $extra = $mode === 'confidential' ? ['identity_mode' => 'confidential', 'reporter_name' => 'Rosa Aguilar'] : [];

    withEmail($this, $extra)->assertCreated();

    $complaint = Complaint::query()->sole();
    expect($complaint->reporter_email)->toBe(WRITE_ONLY)->and($complaint->identity_mode->value)->toBe($mode);
    expect(json_encode((array) DB::table('complaints')->first()))->not->toContain('rosa.privada');
})->with(['anonymous', 'confidential']);

it('takes no address at all, and refuses one that is not an address or too long', function (string $email, bool $valid): void {
    $response = withEmail($this, ['notify_email' => $email]);

    $valid ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors('notify_email');
    if ($email === '') {
        expect(Complaint::query()->sole()->reporter_email)->toBeNull();
    }
})->with([
    'none' => ['', true],
    'an address' => ['ana@ejemplo.mx', true],
    'not an address' => ['ana', false],
    'too long' => [str_repeat('a', 190).'@ejemplo.mx', false],
]);

it('never gives the address to anybody: not in the case, not in the inbox, not in the logs or the audit trail', function (): void {
    withEmail($this)->assertCreated();
    $complaint = Complaint::query()->sole();

    $case = $this->actingAs($this->owner)->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))->assertOk();
    $inbox = $this->actingAs($this->owner)->get(route('tenant.complaints.index', $this->tenant))->assertOk();

    expect(json_encode($case->viewData('page')['props']))->not->toContain('rosa.privada')->not->toContain('reporterEmail')->not->toContain('reporter_email');
    expect(json_encode($inbox->viewData('page')['props']))->not->toContain('rosa.privada');
    expect(json_encode(AuditEntry::query()->get()->toArray()))->not->toContain('rosa.privada');
});

it('tells the person there is news, when a handler writes to them, with a link to follow up and nothing of the case', function (): void {
    Notification::fake();
    $complaint = caseWithEmail($this);

    $this->actingAs($this->owner)->post(route('tenant.complaints.cases.messages.store', [$this->tenant, $complaint->id]), ['body' => 'Revisamos lo que cuenta, gracias.'])->assertRedirect();

    Notification::assertSentOnDemandTimes(NewsOnYourCase::class, 1);
    Notification::assertSentOnDemand(NewsOnYourCase::class, function (NewsOnYourCase $notification, array $channels, object $notifiable): bool {
        $mail = $notification->toMail($notifiable);
        $text = json_encode([$mail->subject, $mail->introLines, $mail->outroLines, $mail->greeting, $mail->actionUrl, $mail->actionText]);

        return $notifiable->routes['mail'] === WRITE_ONLY
            && $mail->actionUrl === route('complaints.follow-up.show', $this->channel->access_code)
            && $mail->subject === 'Hay novedades en su caso'
            && ! str_contains((string) $text, 'ABCD2345') && ! str_contains((string) $text, 'Revisamos') && ! str_contains((string) $text, 'grita');
    });
});

it('does not queue the notice: the address would sit in the queue', function (): void {
    expect(NewsOnYourCase::class)->not->toImplement(Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('tells the person when the case is acknowledged and when it is closed, but not for a note', function (): void {
    Notification::fake();
    $complaint = caseWithEmail($this);

    $this->actingAs($this->owner)->post(route('tenant.complaints.cases.notes.store', [$this->tenant, $complaint->id]), ['body' => 'Una nota interna']);
    Notification::assertNothingSent();

    $this->actingAs($this->owner)->post(route('tenant.complaints.cases.acknowledge', [$this->tenant, $complaint->id]));
    Notification::assertSentOnDemandTimes(NewsOnYourCase::class, 1);

    Carbon::setTestNow('2026-11-21 18:00:00');
    $this->actingAs($this->owner)->post(route('tenant.complaints.cases.close', [$this->tenant, $complaint->id]), ['resolution' => 'measures_taken']);
    Notification::assertSentOnDemandTimes(NewsOnYourCase::class, 2);
});

it('tells the person at most once a day', function (): void {
    Notification::fake();
    $complaint = caseWithEmail($this);

    foreach (['uno', 'dos', 'tres'] as $text) {
        $this->actingAs($this->owner)->post(route('tenant.complaints.cases.messages.store', [$this->tenant, $complaint->id]), ['body' => "Mensaje {$text}"]);
    }

    Notification::assertSentOnDemandTimes(NewsOnYourCase::class, 1);
});

it('sends nothing for a case with no address', function (): void {
    $without = Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id]);
    ComplaintAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $without->id, 'user_id' => $this->owner->id]);
    Notification::fake();

    $this->actingAs($this->owner)->post(route('tenant.complaints.cases.messages.store', [$this->tenant, $without->id]), ['body' => 'Hola'])->assertRedirect();

    Notification::assertNothingSent();
});

it('deletes the address thirty days after the case closes, and not a day before', function (string $closedOn, bool $cleared): void {
    $complaint = Complaint::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'reporter_email' => WRITE_ONLY, 'closed_on' => $closedOn, 'closed_at' => $closedOn.' 12:00:00']);

    resolve(PurgeComplaints::class)->handle();

    expect($complaint->refresh()->reporter_email)->toBe($cleared ? null : WRITE_ONLY);
})->with([
    'closed 30 days ago' => ['2026-10-20', true],
    'closed 29 days ago' => ['2026-10-21', false],
    'closed a year ago' => ['2025-11-01', true],
]);

it('keeps the address of a case still open, however old, and clears it even under legal hold', function (): void {
    $open = Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'reporter_email' => WRITE_ONLY, 'received_on' => '2020-01-01']);
    $held = Complaint::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'reporter_email' => WRITE_ONLY, 'on_hold' => true, 'closed_on' => '2026-01-01', 'closed_at' => '2026-01-01 12:00:00']);

    resolve(PurgeComplaints::class)->handle();

    expect($open->refresh()->reporter_email)->toBe(WRITE_ONLY)->and($held->refresh()->reporter_email)->toBeNull()->and($held->description)->not->toBeNull();
});

it('is cleared with everything else when the case is purged', function (): void {
    $complaint = Complaint::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'reporter_email' => WRITE_ONLY, 'closed_on' => '2020-01-01', 'closed_at' => '2020-01-01 12:00:00']);

    resolve(PurgeComplaints::class)->handle();

    expect($complaint->refresh()->reporter_email)->toBeNull()->and($complaint->purged_on)->not->toBeNull();
});

it('shows the public form no address of anybody, only the question', function (): void {
    $this->get('/c/'.$this->channel->access_code)->assertInertia(fn (Assert $page) => $page->component('complaints-public/form')->missing('notifyEmail'));
});
