<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\RemindNextEvaluations;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Notifications\NextEvaluationDue;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/*
 * «Se acerca tu próxima evaluación» (rediseño v2, docs/02 «Correos» and «Ciclo de vida»): 60 days before a center's NOM-035
 * evaluation turns 2 years old (num. 7.9), its owner is told once, unless the next one is already drafted or running.
 */

mutates(RemindNextEvaluations::class, NextEvaluationDue::class);

beforeEach(function (): void {
    // 08:15 in Mexico City.
    $this->travelTo('2026-11-05 14:15:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    workIn($this->tenant);
});

/** A NOM-035 evaluation of the center closed at `$closedAt` (UTC). */
function closedAt(WorkCenter $center, string $closedAt): Campaign
{
    return Campaign::factory()->closed()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'closed_at' => $closedAt]);
}

it('tells the owner 60 days before a center\'s evaluation turns 2 years old, with the way to schedule the next', function (): void {
    // Two years after it closed is 4 Jan 2027, 60 days from today.
    closedAt($this->plant, '2025-01-04 18:00:00');
    Log::spy();

    expect(resolve(RemindNextEvaluations::class)->handle())->toBe(1);

    Notification::assertSentOnDemand(NextEvaluationDue::class, function (NextEvaluationDue $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === $this->owner->email
            && $channels === ['mail']
            && $mail->subject === 'Se acerca tu próxima evaluación'
            && $mail->introLines === ['La evaluación de NOM-035 de Planta Norte cumple 2 años el 4 ene 2027. La norma pide evaluar al menos cada 2 años: prográmala con tiempo.']
            && $mail->actionText === 'Programar'
            && $mail->actionUrl === route('tenant.nom035.route', ['acme', $this->plant->id])
            && str_contains((string) $mail->render(), 'Acme</span>');
    });
    Log::shouldHaveReceived('info')->with('campaign.next_evaluations_reminded', ['sent' => 1]);
});

it('names every center whose evaluation turns 2 years old that day, and leads to the evaluations', function (): void {
    $south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    closedAt($this->plant, '2025-01-04 18:00:00');
    closedAt($south, '2025-01-04 20:00:00');

    resolve(RemindNextEvaluations::class)->handle();

    Notification::assertSentOnDemand(NextEvaluationDue::class, fn (NextEvaluationDue $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notification->toMail($notifiable)->introLines === ['Las evaluaciones de NOM-035 de Planta Norte y Planta Sur cumplen 2 años el 4 ene 2027. La norma pide evaluar al menos cada 2 años: prográmalas con tiempo.']
        && $notification->toMail($notifiable)->actionUrl === route('tenant.campaigns.index', 'acme'));
});

it('says nothing on any other day, when a newer evaluation exists, or to a company that may not be used or has no owner', function (Closure $arrange): void {
    closedAt($this->plant, '2025-01-04 18:00:00');
    $arrange($this);

    expect(resolve(RemindNextEvaluations::class)->handle())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'a day before' => [fn ($test) => $test->travel(-1)->days()],
    'a day after' => [fn ($test) => $test->travel(1)->days()],
    'a newer one drafted' => [fn ($test) => Campaign::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->plant->id])],
    'a newer one closed' => [fn ($test) => closedAt($test->plant, '2026-06-01 18:00:00')],
    'blocked' => [fn ($test) => Tenant::query()->whereKey($test->tenant->id)->update(suspension())],
    'no owner' => [fn ($test) => App\Modules\Tenancy\Models\TenantMembership::query()->delete()],
    'archived center' => [fn ($test) => $test->plant->forceFill(['archived_at' => now()])->save()],
]);

it('runs from its command and reports how many it sent', function (): void {
    closedAt($this->plant, '2025-01-04 18:00:00');

    $this->artisan('campaigns:remind-next-evaluations')->expectsOutput('Avisos de la próxima evaluación: 1')->assertSuccessful();
});
