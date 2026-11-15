<?php

declare(strict_types=1);

use App\Modules\Engagement\Actions\AnnounceUpcomingPulses;
use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\PulseUpcoming;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

mutates(AnnounceUpcomingPulses::class, PulseUpcoming::class);

beforeEach(function (): void {
    // 12:00 in Mexico City on the 15th.
    $this->travelTo('2026-11-15 18:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo', 'slug' => 'autopartes']);
    workIn($this->tenant);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->schedule = PulseSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'next_run_on' => '2026-11-18']);
});

function pulseAnnounceRun(): int
{
    return resolve(AnnounceUpcomingPulses::class)->handle();
}

/** @return list<string> the addresses that got the heads-up */
function pulseAnnouncedTo(): array
{
    $sent = [];

    Notification::assertSentOnDemand(PulseUpcoming::class, function (PulseUpcoming $notification, array $channels, AnonymousNotifiable $notifiable) use (&$sent): bool {
        $sent[] = (string) $notifiable->routeNotificationFor('mail');

        return true;
    });

    return $sent;
}

it('tells the owner and hr three days before, once', function (): void {
    expect(pulseAnnounceRun())->toBe(2)->and(pulseAnnounceRun())->toBe(0);

    expect(pulseAnnouncedTo())->toEqualCanonicalizing([$this->owner->email, $this->hr->email]);
    Notification::assertSentOnDemandTimes(PulseUpcoming::class, 2);
});

it('does not tell a viewer', function (): void {
    pulseAnnounceRun();

    expect(pulseAnnouncedTo())->not->toContain($this->viewer->email);
});

it('stays quiet four days before, two days before and on the day', function (string $date): void {
    $this->schedule->update(['next_run_on' => $date]);

    expect(pulseAnnounceRun())->toBe(0);
    Notification::assertNothingSent();
})->with(['2026-11-19', '2026-11-17', '2026-11-15']);

it('counts the three days by the display time zone, not by UTC', function (): void {
    // 2026-11-16 03:00 UTC is still the 15th in Mexico City: the 18th is three days away.
    $this->travelTo('2026-11-16 03:00:00');

    expect(pulseAnnounceRun())->toBe(2);
});

it('does not tell about a paused schedule', function (): void {
    $this->schedule->update(['active' => false]);

    expect(pulseAnnounceRun())->toBe(0);
});

it('does not tell about a date it already told', function (): void {
    $this->schedule->update(['announced_for' => '2026-11-18']);

    expect(pulseAnnounceRun())->toBe(0);
});

it('tells again when the date moved after the heads-up', function (): void {
    $this->schedule->update(['announced_for' => '2026-11-10']);

    expect(pulseAnnounceRun())->toBe(2)->and($this->schedule->fresh()?->announced_for?->toDateString())->toBe('2026-11-18');
});

it('stays quiet in a suspended company', function (): void {
    Illuminate\Support\Facades\DB::table('tenants')->where('id', $this->tenant->id)->update(['restriction' => 'suspended', 'restriction_reason' => 'Prueba', 'restricted_at' => now()]);

    expect(pulseAnnounceRun())->toBe(0);
});

it('says how many commitments are still open, only when there are', function (int $open, ?string $line): void {
    $climate = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
    Commitment::factory()->count($open)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $climate->id]);
    Commitment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $climate->id, 'status' => CommitmentStatus::Done]);

    pulseAnnounceRun();

    $html = '';
    Notification::assertSentOnDemand(PulseUpcoming::class, function (PulseUpcoming $notification) use (&$html): bool {
        $html = (string) $notification->toMail(new AnonymousNotifiable)->render();

        return true;
    });
    $line === null ? expect($html)->not->toContain('compromiso') : expect($html)->toContain($line);
})->with([
    'two' => [2, '2 compromisos abiertos'],
    'one' => [1, '1 compromiso abierto'],
    'none' => [0, null],
]);

it('writes the heads-up with the date, the mode and the questions, and one button to review', function (): void {
    pulseAnnounceRun();

    $mail = null;
    Notification::assertSentOnDemand(PulseUpcoming::class, function (PulseUpcoming $notification) use (&$mail): bool {
        $mail = $notification->toMail(new AnonymousNotifiable);

        return true;
    });
    $html = (string) $mail?->render();

    expect($mail?->subject)->toBe('Tu próximo pulso sale el 18 de noviembre')
        ->and($mail?->actionText)->toBe('Revisar antes de enviar')
        ->and($mail?->actionUrl)->toBe(route('tenant.pulse.index', 'autopartes'))
        ->and($html)->toContain('Recorrido')->toContain('9 preguntas')->toContain('Autopartes Demo')
        ->not->toMatch('/eNPS|Net Promoter/iu');
});

it('carries no result and no person, only the date, the mode and counts', function (): void {
    $this->travelTo('2026-11-15 18:00:00');
    pulseAnnounceRun();

    $html = '';
    Notification::assertSentOnDemand(PulseUpcoming::class, function (PulseUpcoming $notification) use (&$html): bool {
        $html = (string) $notification->toMail(new AnonymousNotifiable)->render();

        return true;
    });

    expect($html)->not->toContain($this->owner->name)->not->toContain($this->hr->name)->not->toContain($this->owner->email);
});

it('runs from the command and reports how many it sent', function (): void {
    $this->artisan('engagement:announce-pulses')->expectsOutput('Avisos enviados: 2')->assertSuccessful();
});
