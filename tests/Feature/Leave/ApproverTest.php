<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Leave\Actions\RequestLeave;
use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Notifications\RequestEscalated;
use App\Modules\Leave\Notifications\RequestToDecide;
use App\Modules\Leave\Support\Approver;
use App\Modules\Leave\Support\RequestNotifier;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(Approver::class, RequestNotifier::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo', 'slug' => 'autopartes']));
    $this->owner = ownerOf($this->tenant);
    $this->travelTo('2026-11-02 18:00:00');
    Notification::fake();
    $this->director = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dora Díaz', 'email' => 'dora@autopartes.test']);
    $this->boss = Employee::factory()->reportingTo($this->director)->create(['name' => 'Beto Ruiz', 'email' => 'beto@autopartes.test']);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Ana Solís']);
});

function requestBy(Employee $person, ?string $typeId = null, ?int $recordedBy = null, string $from = '2026-11-09', string $to = '2026-11-20'): LeaveRequest
{
    return resolve(RequestLeave::class)->handle($person->id, $typeId, CarbonImmutable::parse($from), CarbonImmutable::parse($to), 'Nota privada', $recordedBy);
}

/**
 * The emails of one kind sent so far, as they would be rendered.
 *
 * @param  class-string<Illuminate\Notifications\Notification>  $class
 * @return list<Illuminate\Notifications\Messages\MailMessage>
 */
function leaveMails(string $class): array
{
    $mails = [];
    Notification::assertSentOnDemand($class, function (object $notification, array $channels, AnonymousNotifiable $notifiable) use (&$mails): bool {
        $mails[] = $notification->toMail($notifiable);

        return true;
    });

    return $mails;
}

function personLeaves(Employee $person): void
{
    $person->update(['deactivated_at' => now()]);
}

it('gives the request to the direct manager while they are active', function (): void {
    expect(resolve(Approver::class)->for($this->ana->id))->toBe($this->boss->id)
        ->and(resolve(Approver::class)->for($this->boss->id))->toBe($this->director->id);
});

it('goes up to the first active manager when the direct one left', function (): void {
    personLeaves($this->boss);

    expect(resolve(Approver::class)->for($this->ana->id))->toBe($this->director->id);
});

it('has nobody when no manager above is active, or there is none', function (): void {
    expect(resolve(Approver::class)->for($this->director->id))->toBeNull();

    personLeaves($this->boss);
    personLeaves($this->director);
    expect(resolve(Approver::class)->for($this->ana->id))->toBeNull();
});

it('never gives the request to a person of another company', function (): void {
    expect(resolve(Approver::class)->for(Employee::factory()->reportingTo(Employee::factory()->create())->create()->id))->toBeNull();
});

it('assigns the request to the manager and emails them the link of their own portal', function (): void {
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'statutory_days' => 14]);

    $request = requestBy($this->ana);

    expect($request->refresh()->only(['approver_employee_id', 'escalated_at', 'escalation_reason']))->toBe(['approver_employee_id' => $this->boss->id, 'escalated_at' => null, 'escalation_reason' => null]);
    Notification::assertSentOnDemandTimes(RequestToDecide::class, 1);
    Notification::assertSentOnDemand(RequestToDecide::class, function (RequestToDecide $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $notification->toMail($notifiable);
        $text = implode("\n", [...$mail->introLines, ...$mail->outroLines]);

        expect($channels)->toBe(['mail'])
            ->and($notifiable->routes['mail'])->toBe('beto@autopartes.test')
            ->and($mail->subject)->toBe('Ana Solís pidió vacaciones')
            ->and($mail->greeting)->toBe('Hola, Beto Ruiz')
            ->and($mail->actionText)->toBe('Revisar solicitud')
            ->and($mail->actionUrl)->toBe(route('portal.vacations.team.show', resolve(PortalAccess::class)->token($this->boss->id)))
            ->and($mail->actionUrl)->not->toContain(resolve(PortalAccess::class)->token($this->ana->id))
            ->and($text)->toContain('Ana Solís pidió vacaciones del 9 de noviembre de 2026 al 20 de noviembre de 2026: 9 días laborables.')
            ->toContain('Tiene días disponibles.')
            ->not->toContain('Nota privada');

        return true;
    });
    Notification::assertSentOnDemandTimes(RequestEscalated::class, 0);
});

it('tells the manager when the person does not have the days, and never names the type of a leave', function (): void {
    requestBy($this->ana, from: '2026-11-10', to: '2026-11-10');
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    requestBy($this->ana, $type->id, from: '2026-12-01', to: '2026-12-02');

    $mails = leaveMails(RequestToDecide::class);
    $text = fn (int $index): string => implode("\n", [...$mails[$index]->introLines, ...$mails[$index]->outroLines]);

    expect($mails[0]->subject)->toBe('Ana Solís pidió vacaciones')
        ->and($text(0))->toContain('del 10 de noviembre de 2026 al 10 de noviembre de 2026: 1 día laborable.')->toContain('No tiene días disponibles.')
        ->and($mails[1]->subject)->toBe('Ana Solís pidió un permiso')
        ->and($text(1))->toContain('Ana Solís pidió un permiso del 1 de diciembre de 2026 al 2 de diciembre de 2026: 2 días laborables.')
        ->not->toContain('disponibles')->not->toContain('Maternidad');
});

it('leaves the request in the portal of a manager with no email, sending nothing', function (): void {
    $this->boss->update(['email' => null]);

    $request = requestBy($this->ana);

    expect($request->refresh()->only(['approver_employee_id', 'escalated_at']))->toBe(['approver_employee_id' => $this->boss->id, 'escalated_at' => null]);
    Notification::assertNothingSent();
});

it('is born with RH when nobody above is active, and tells the owner', function (): void {
    personLeaves($this->boss);
    personLeaves($this->director);

    $request = requestBy($this->ana)->refresh();

    expect($request->only(['approver_employee_id', 'escalation_reason']))->toBe(['approver_employee_id' => null, 'escalation_reason' => EscalationReason::NoManager])
        ->and($request->escalated_at?->toDateTimeString())->toBe('2026-11-02 18:00:00');
    Notification::assertSentOnDemandTimes(RequestToDecide::class, 0);
    Notification::assertSentOnDemandTimes(RequestEscalated::class, 1);
    Notification::assertSentOnDemand(RequestEscalated::class, function (RequestEscalated $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $notification->toMail($notifiable);
        $text = implode("\n", [...$mail->introLines, ...$mail->outroLines]);

        expect($channels)->toBe(['mail'])
            ->and($notifiable->routes['mail'])->toBe($this->owner->email)
            ->and($mail->subject)->toBe('Una solicitud de Ana Solís espera tu respuesta')
            ->and($mail->actionText)->toBe('Revisar solicitud')
            ->and($mail->actionUrl)->toBe(route('tenant.vacations.requests.index', $this->tenant))
            ->and($text)->toContain('Ana Solís no tiene un jefe activo que le responda.')
            ->toContain('Pidió vacaciones del 9 de noviembre de 2026 al 20 de noviembre de 2026: 9 días laborables.')
            ->not->toContain('Nota privada');

        return true;
    });
});

it('tells the owner why a request waited too long, in the days the company gives a manager', function (): void {
    config(['leave.escalation_business_days' => 4]);
    $request = LeaveRequest::factory()->ofType(LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']))->escalated()->create(['employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id]);

    resolve(RequestNotifier::class)->escalated($request);

    [$mail] = leaveMails(RequestEscalated::class);
    expect(implode("\n", $mail->introLines))->toContain('Su jefe no respondió en 4 días hábiles.')->toContain('Pidió un permiso del 9 de noviembre de 2026 al 20 de noviembre de 2026: 9 días laborables.')->not->toContain('Maternidad');
});

it('sends nothing to a company without an owner, and does not fail', function (): void {
    workIn($orphan = Tenant::factory()->create());
    $person = Employee::factory()->create(['tenant_id' => $orphan->id]);

    expect(requestBy($person)->refresh()->escalation_reason)->toBe(EscalationReason::NoManager);
    Notification::assertNothingSent();
});

it('emails nobody about what RH records for a person, but still says whose it is to decide', function (): void {
    $rh = User::factory()->create();

    $withBoss = requestBy($this->ana, recordedBy: $rh->id);
    $withoutBoss = requestBy($this->director, recordedBy: $rh->id);

    expect($withBoss->refresh()->approver_employee_id)->toBe($this->boss->id)
        ->and($withoutBoss->refresh()->escalation_reason)->toBe(EscalationReason::NoManager);
    Notification::assertNothingSent();
});

it('keeps the request when the email cannot be sent', function (): void {
    Illuminate\Support\Facades\Exceptions::fake();
    $this->mock(PortalAccess::class)->shouldReceive('token')->andThrow(new RuntimeException('no link'));

    expect(requestBy($this->ana)->exists)->toBeTrue();
    Illuminate\Support\Facades\Exceptions::assertReported(RuntimeException::class);
});

it('logs who was born with RH, and never a portal token', function (): void {
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    });

    $assigned = requestBy($this->ana);
    $escalated = requestBy($this->director);

    expect(array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'leave.'))))->toBe([
        'leave.request_created '.json_encode(['request_id' => $assigned->id, 'employee_id' => $this->ana->id, 'working_days' => 9, 'had_balance' => false], JSON_THROW_ON_ERROR),
        'leave.request_created '.json_encode(['request_id' => $escalated->id, 'employee_id' => $this->director->id, 'working_days' => 9, 'had_balance' => false], JSON_THROW_ON_ERROR),
        'leave.request_escalated '.json_encode(['request_id' => $escalated->id, 'reason' => 'no_manager'], JSON_THROW_ON_ERROR),
    ])->and(implode("\n", $lines))->not->toContain(resolve(PortalAccess::class)->token($this->boss->id))->not->toContain(resolve(PortalAccess::class)->token($this->ana->id));
});
