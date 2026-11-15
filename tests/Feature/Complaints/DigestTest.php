<?php

declare(strict_types=1);

use App\Modules\Complaints\Actions\SendComplaintDigests;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintEntry;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Notifications\ComplaintsDigest;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(SendComplaintDigests::class);

const WORDS = 'El jefe de turno me humilla frente a todos';

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['acknowledge_within_days' => 3, 'resolve_within_days' => 30]);
    foreach ([$this->owner, $this->hr] as $person) {
        ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $person->id]);
    }
    workIn($this->tenant);
});

/**
 * A complaint given to the people.
 *
 * @param  array<string, mixed>  $attributes
 */
function given(object $test, array $people, array $attributes = []): Complaint
{
    $complaint = Complaint::factory()->create(['tenant_id' => $test->tenant->id, 'channel_id' => $test->channel->id, 'description' => WORDS, 'received_on' => '2026-11-19', ...$attributes]);

    foreach ($people as $person) {
        ComplaintAssignment::factory()->create(['tenant_id' => $test->tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $person->id, 'notified_position' => null]);
    }

    return $complaint;
}

function digest(): int
{
    return resolve(SendComplaintDigests::class)->handle();
}

/** @return list<string> */
function digestLinesOf(ComplaintsDigest $notification): array
{
    return $notification->toMail((object) [])->introLines;
}

it('tells each handler how many new cases they have, with counts only, and the link to the inbox', function (): void {
    given($this, [$this->owner, $this->hr]);
    given($this, [$this->owner]);

    expect(digest())->toBe(2);

    Notification::assertSentOnDemand(ComplaintsDigest::class, function (ComplaintsDigest $notification, array $channels, object $notifiable): bool {
        if ($notifiable->routes['mail'] !== $this->owner->email) {
            return false;
        }
        $mail = $notification->toMail($notifiable);

        // Board V4-Correos-2: how many cases wait, the counts, why it says no more, and one button to the cases.
        return in_array('Tienes 2 casos nuevos.', digestLinesOf($notification), true)
            && in_array('Por privacidad, este correo no incluye lo que dicen.', digestLinesOf($notification), true)
            && $mail->actionText === 'Ver mis casos'
            && $mail->actionUrl === route('tenant.complaints.index', 'acme')
            && $mail->subject === 'Tienes 2 casos por atender'
            && str_contains((string) $mail->render(), 'Lo recibes porque te designaron para atender quejas en Acme.');
    });
    Notification::assertSentOnDemand(ComplaintsDigest::class, fn (ComplaintsDigest $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === $this->hr->email
        && in_array('Tienes 1 caso nuevo.', digestLinesOf($notification), true)
        && $notification->toMail($notifiable)->subject === 'Tienes 1 caso por atender');
});

it('says nothing of what a case is: no reference, no words, no name, no type', function (): void {
    given($this, [$this->owner], ['reference' => 'SECRET99', 'type' => 'traumatic_event_notice', 'reporter_name' => 'Rosa Aguilar', 'identity_mode' => 'confidential', 'occurred_on' => '2026-11-01']);

    digest();

    Notification::assertSentOnDemand(ComplaintsDigest::class, function (ComplaintsDigest $notification): bool {
        $mail = $notification->toMail((object) []);
        $text = json_encode([digestLinesOf($notification), $mail->subject, $mail->actionUrl]);

        foreach (['SECRET99', 'humilla', 'Rosa', 'acontecimiento', 'traumático'] as $secret) {
            if (str_contains((string) $text, $secret)) {
                return false;
            }
        }

        return true;
    });
});

it('does not tell the same news twice', function (): void {
    given($this, [$this->owner]);

    expect(digest())->toBe(1);
    expect(digest())->toBe(0);
    Notification::assertSentOnDemandTimes(ComplaintsDigest::class, 1);
});

it('tells of the reply of the person once, not before the case was announced nor again', function (): void {
    $complaint = given($this, [$this->owner]);
    digest();
    ComplaintEntry::factory()->fromReporter('2026-11-20')->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $complaint->id, 'position' => 1, 'body' => 'Sigue pasando']);

    expect(digest())->toBe(1);
    Notification::assertSentOnDemand(ComplaintsDigest::class, fn (ComplaintsDigest $notification): bool => in_array('Tienes 1 caso con una respuesta de la persona.', digestLinesOf($notification), true));
    expect(digest())->toBe(0);

    ComplaintEntry::factory()->fromReporter('2026-11-20')->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $complaint->id, 'position' => 2, 'body' => 'Otra vez']);
    expect(digest())->toBe(1);
});

it('does not count the answers of the handlers themselves as news', function (): void {
    $complaint = given($this, [$this->owner]);
    digest();
    ComplaintEntry::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $complaint->id, 'position' => 1, 'kind' => 'handler_message', 'author_id' => $this->hr->id, 'body' => 'Revisamos']);

    expect(digest())->toBe(0);
});

it('counts neither closed cases nor ended assignments, nor a handler of another company', function (): void {
    given($this, [$this->owner], ['status' => 'closed', 'resolution' => 'measures_taken', 'closed_on' => '2026-11-19', 'closed_at' => now(), 'acknowledged_at' => now()]);
    $held = given($this, [$this->hr]);
    ComplaintAssignment::query()->where('complaint_id', $held->id)->update(['ended_on' => '2026-11-19', 'end_reason' => 'recused']);
    $other = Tenant::factory()->create();
    $otherChannel = ComplaintChannel::factory()->forTenant($other->id)->active()->create();
    $theirs = Complaint::factory()->create(['tenant_id' => $other->id, 'channel_id' => $otherChannel->id]);
    ComplaintAssignment::factory()->create(['tenant_id' => $other->id, 'complaint_id' => $theirs->id, 'notified_position' => null]);
    ComplaintHandler::factory()->company($other->id)->create(['user_id' => ComplaintAssignment::query()->withoutGlobalScopes()->where('complaint_id', $theirs->id)->value('user_id')]);
    $this->app->make(App\Modules\Tenancy\Support\CompanyMembers::class)->forget();

    expect(digest())->toBe(0);
});

it('does not write to a company that may not be used', function (): void {
    given($this, [$this->owner]);
    $this->tenant->forceFill(suspension())->save();

    expect(digest())->toBe(0);
});

it('reminds once a day of the acknowledgements and responses past their deadline, by count', function (): void {
    given($this, [$this->owner], ['received_on' => '2026-11-10']);
    given($this, [$this->owner], ['received_on' => '2026-10-01', 'status' => 'in_review', 'acknowledged_at' => now()]);
    digest();
    Notification::fake();
    // Nothing new since: only the daily reminder, once.

    Carbon::setTestNow('2026-11-20 20:00:00');
    expect(digest())->toBe(0);
    Notification::assertNothingSent();

    Carbon::setTestNow('2026-11-21 18:00:00');
    expect(digest())->toBe(1);
    Notification::assertSentOnDemand(ComplaintsDigest::class, function (ComplaintsDigest $notification): bool {
        $lines = digestLinesOf($notification);

        return in_array('Tienes 1 caso sin acusar recibo pasado su plazo.', $lines, true) && in_array('Tienes 1 caso sin responder pasado su plazo.', $lines, true);
    });
    Carbon::setTestNow('2026-11-21 19:00:00');
    expect(digest())->toBe(0);
});

it('measures the deadlines in whole days from the company\'s own settings', function (string $received, bool $remind): void {
    $this->channel->forceFill(['acknowledge_within_days' => 3])->save();
    $complaint = given($this, [$this->owner], ['received_on' => $received]);
    ComplaintAssignment::query()->where('complaint_id', $complaint->id)->update(['notified_position' => 0]);

    expect(digest())->toBe($remind ? 1 : 0);
})->with([
    'received 3 days ago (the limit)' => ['2026-11-17', false],
    'received 4 days ago' => ['2026-11-16', true],
]);

it('logs how many digests went, as a count', function (): void {
    Log::spy();
    given($this, [$this->owner]);

    digest();

    Log::shouldHaveReceived('info')->with('complaints.digest_sent', ['count' => 1])->once();
});

it('runs from the command and reports how many went', function (): void {
    given($this, [$this->owner]);

    $this->artisan('complaints:send-digests')->expectsOutput('Resúmenes enviados: 1')->assertSuccessful();
});

it('reminds, once a day, of the open cases with no movement for ninety days', function (string $received, bool $remind): void {
    $complaint = given($this, [$this->owner], ['received_on' => $received, 'status' => 'in_review', 'acknowledged_at' => now()]);
    ComplaintAssignment::query()->where('complaint_id', $complaint->id)->update(['notified_position' => 0]);
    $this->channel->forceFill(['resolve_within_days' => 180])->save();

    expect(digest())->toBe($remind ? 1 : 0);
    if ($remind) {
        Notification::assertSentOnDemand(ComplaintsDigest::class, fn (ComplaintsDigest $notification): bool => in_array('Tienes 1 caso sin movimiento desde hace más de 90 días.', digestLinesOf($notification), true));
    }
})->with([
    'received 90 days ago (the limit)' => ['2026-08-22', false],
    'received 91 days ago' => ['2026-08-21', true],
    'received yesterday' => ['2026-11-19', false],
]);

it('counts the last entry of a case as its movement', function (): void {
    $complaint = given($this, [$this->owner], ['received_on' => '2026-06-01', 'status' => 'in_review', 'acknowledged_at' => now()]);
    $this->channel->forceFill(['resolve_within_days' => 180])->save();
    ComplaintEntry::factory()->fromReporter('2026-11-01')->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $complaint->id, 'position' => 1, 'body' => 'Sigue']);
    ComplaintAssignment::query()->where('complaint_id', $complaint->id)->update(['notified_position' => 1]);

    expect(digest())->toBe(0);
});
