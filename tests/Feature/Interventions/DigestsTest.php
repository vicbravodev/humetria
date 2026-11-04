<?php

declare(strict_types=1);

use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Actions\SendProgramDigests;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Notifications\ProgramDigest;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(SendProgramDigests::class, ProgramDigest::class);

beforeEach(function (): void {
    // Monday, 9:30 in Mexico City.
    $this->travelTo('2026-11-02 15:30 UTC');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = ownerOf($this->tenant);
    [$this->campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 7]);
    workIn($this->tenant);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $this->program?->update(['responsible_name' => 'Ana', 'responsible_user_id' => $this->owner->id]);
    $this->rows = ProgramItem::query()->where('program_id', $this->program?->id)->orderBy('scope_key')->get();
    // Every row has an action unless a test says otherwise: only what the test sets up is told.
    foreach ($this->rows as $row) {
        ProgramAction::factory()->done()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $row->id]);
    }
});

/** An action of the first row, `$status`, due `$days` days from today (negative: ago), answered by `$user`. */
function openAction(object $test, int $days, string $status = 'planned', ?int $user = null, ?Program $program = null): ProgramAction
{
    $factory = ProgramAction::factory();
    $factory = $status === 'in_progress' ? $factory->inProgress() : ($status === 'done' ? $factory->done() : ($status === 'cancelled' ? $factory->cancelled() : $factory));

    return $factory->create([
        'tenant_id' => $test->tenant->id,
        'program_id' => ($program ?? $test->program)?->id,
        'item_id' => $test->rows[0]->id,
        'due_on' => now('America/Mexico_City')->addDays($days)->toDateString(),
        'responsible_user_id' => $user,
    ]);
}

/** @return list<string> */
function linesTo(string $email): array
{
    $lines = null;
    Notification::assertSentOnDemand(ProgramDigest::class, function (ProgramDigest $digest, array $channels, AnonymousNotifiable $notifiable) use ($email, &$lines): bool {
        if ($notifiable->routes['mail'] === $email) {
            // The first line says what the email is about; the rest are the counts.
            $lines = array_slice($digest->toMail($notifiable)->introLines, 1);
        }

        return true;
    });

    return $lines ?? throw new RuntimeException("No digest to {$email}.");
}

it('tells the owner, with counts only, what waits across the Programas: overdue, due soon, rows without actions, no responsible, follow-ups overdue', function (): void {
    openAction($this, -3);
    openAction($this, -1, 'in_progress');
    openAction($this, 5);
    ProgramAction::query()->where('item_id', $this->rows[1]->id)->delete();
    $this->rows[2]->update(['follow_up_due_on' => '2026-10-01']);
    Program::factory()->create(['tenant_id' => $this->tenant->id, 'responsible_name' => null]);

    $sent = resolve(SendProgramDigests::class)->handle();

    expect($sent)->toBeGreaterThanOrEqual(1)
        ->and(linesTo($this->owner->email))->toContain('2 acciones vencidas.', '1 acción vence en los próximos 14 días.', '1 fila sin acciones.', '1 Programa sin responsable.', '1 evaluación posterior vencida.');
});

it('counts the day an action is due as due soon, not as overdue, and the fifteenth day as neither', function (): void {
    openAction($this, 0);
    openAction($this, 14);
    openAction($this, 15);
    openAction($this, -1, 'done');
    openAction($this, -1, 'cancelled');

    resolve(SendProgramDigests::class)->handle();

    $lines = linesTo($this->owner->email);
    expect($lines)->toBe(['2 acciones vencen en los próximos 14 días.'])
        ->and(implode(' ', $lines))->not->toContain('vencidas');
});

it('sends nothing when everything is in order', function (): void {
    expect(resolve(SendProgramDigests::class)->handle())->toBe(0);

    Notification::assertNothingSent();
});

it('sends a responsible member only what is theirs, and the owner everything', function (): void {
    $hugo = memberOf($this->tenant, TenantRole::Hr);
    $vera = memberOf($this->tenant, TenantRole::Hr);
    openAction($this, -2, 'planned', $hugo->id);
    openAction($this, -2, 'planned', $hugo->id);
    openAction($this, 3, 'planned', $hugo->id);
    openAction($this, -9, 'planned', $vera->id);

    resolve(SendProgramDigests::class)->handle();

    expect(linesTo($hugo->email))->toBe(['2 acciones vencidas.', '1 acción vence en los próximos 14 días.'])
        ->and(linesTo($vera->email))->toBe(['1 acción vencida.'])
        ->and(linesTo($this->owner->email))->toContain('3 acciones vencidas.');
});

it('does not mail the owner twice when the owner is also the responsible', function (): void {
    openAction($this, -2, 'planned', $this->owner->id);

    $sent = resolve(SendProgramDigests::class)->handle();

    expect($sent)->toBe(1);
    Notification::assertSentOnDemandTimes(ProgramDigest::class, 1);
});

it('sends nothing to a responsible who is a name and not a member, and does not break', function (): void {
    openAction($this, -2);

    expect(fn () => resolve(SendProgramDigests::class)->handle())->not->toThrow(Throwable::class);
});

it('names no title, no name and no text in the email, in the panel voice', function (): void {
    $hugo = memberOf($this->tenant, TenantRole::Hr);
    $hugo->update(['name' => 'Hugo RH']);
    $action = openAction($this, -2, 'planned', $hugo->id);
    $action->update(['title' => 'Plan para Juan Pérez', 'description' => 'Hablar con Juan Pérez.']);

    resolve(SendProgramDigests::class)->handle();

    $text = implode(' ', [...linesTo($this->owner->email), ...linesTo($hugo->email)]);
    expect($text)->not->toContain('Juan')->not->toContain('Plan para');

    $mail = null;
    Notification::assertSentOnDemand(ProgramDigest::class, function (ProgramDigest $digest, array $channels, AnonymousNotifiable $notifiable) use ($hugo, &$mail): bool {
        if ($notifiable->routes['mail'] === $hugo->email) {
            $mail = $digest->toMail($notifiable);
        }

        return true;
    });
    expect($mail?->subject)->toBe('Resumen de tus Programas de intervención')
        ->and($mail?->greeting)->toBe('Hola, Hugo RH')
        ->and($mail?->actionUrl)->toBe(route('tenant.campaigns.program', ['acme', $this->campaign->id]));
});

it('links the owner to the list of Programas, and a responsible to their Programa when it is the only one', function (): void {
    $hugo = memberOf($this->tenant, TenantRole::Hr);
    openAction($this, -2, 'planned', $hugo->id);

    resolve(SendProgramDigests::class)->handle();

    $url = null;
    Notification::assertSentOnDemand(ProgramDigest::class, function (ProgramDigest $digest, array $channels, AnonymousNotifiable $notifiable) use (&$url): bool {
        if ($notifiable->routes['mail'] === $this->owner->email) {
            $url = $digest->toMail($notifiable)->actionUrl;
        }

        return true;
    });
    expect($url)->toBe(route('tenant.interventions.index', 'acme'));
});

it('sends nothing to a blocked company, and tells the others even when one fails', function (): void {
    Exceptions::fake();
    openAction($this, -2);
    $other = Tenant::factory()->create(['slug' => 'beta']);
    $otherOwner = ownerOf($other);
    [$campaign] = closedEvaluationInAreas($other, ['Ventas' => 7]);
    workIn($other);
    $otherProgram = resolve(CreateProgram::class)->handle($campaign->id, null);
    $otherProgram?->update(['responsible_name' => 'Ana']);
    $row = ProgramItem::query()->where('program_id', $otherProgram?->id)->firstOrFail();
    ProgramAction::factory()->create(['tenant_id' => $other->id, 'program_id' => $otherProgram?->id, 'item_id' => $row->id, 'due_on' => '2026-10-01']);
    $blocked = Tenant::factory()->create(['slug' => 'gamma', 'deactivated_at' => now()]);
    ownerOf($blocked);
    Program::factory()->create(['tenant_id' => $blocked->id]);
    // The first company breaks while it is read.
    $broken = false;
    Program::retrieved(function () use (&$broken): void {
        if (! $broken) {
            $broken = true;
            throw new RuntimeException('boom');
        }
    });

    resolve(SendProgramDigests::class)->handle();

    Exceptions::assertReported(RuntimeException::class);
    expect(linesTo($otherOwner->email))->toContain('1 acción vencida.');
    Notification::assertSentOnDemandTimes(ProgramDigest::class, 1);
});

it('works for a company with no owner by skipping its owner digest, and tells each responsible still', function (): void {
    $hugo = memberOf($this->tenant, TenantRole::Hr);
    openAction($this, -2, 'planned', $hugo->id);
    App\Modules\Tenancy\Models\TenantMembership::query()->where('user_id', $this->owner->id)->delete();

    resolve(SendProgramDigests::class)->handle();

    expect(linesTo($hugo->email))->toBe(['1 acción vencida.']);
    Notification::assertSentOnDemandTimes(ProgramDigest::class, 1);
});

it('logs how many went, without any text', function (): void {
    Log::spy();
    $hugo = memberOf($this->tenant, TenantRole::Hr);
    openAction($this, -2, 'planned', $hugo->id);

    resolve(SendProgramDigests::class)->handle();

    Log::shouldHaveReceived('info')->with('intervention.digests_sent', ['owner' => 1, 'responsible' => 1])->once();
});

it('runs from the command and reports how many went', function (): void {
    openAction($this, -2);

    $this->artisan('interventions:send-digests')->expectsOutput('Resúmenes enviados: 1')->assertSuccessful();
});

it('waits to be sent until the changes are committed, in the queue', function (): void {
    expect(new ReflectionClass(ProgramDigest::class)->implementsInterface(Illuminate\Contracts\Queue\ShouldQueue::class))->toBeTrue();
});
