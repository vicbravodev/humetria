<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Actions\SendInvitationEmail;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Notifications\InvitationEmail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

mutates(InvitationEmail::class, SendInvitationEmail::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-30 18:00 UTC');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo']);
    $this->olga = User::factory()->create(['name' => 'Olga Owner']);
});

/** The email SendInvitationEmail sends for an invitation to `$email` with `$role`. */
function invitationEmail(Tenant $tenant, ?User $by, TenantRole $role = TenantRole::Hr, string $email = 'lucia@autopartes.mx'): MailMessage
{
    $invitation = TenantInvitation::factory()->for($tenant)->create([
        'role' => $role, 'email' => $email, 'invited_by' => $by?->id, 'expires_at' => now()->addDays(7),
    ]);
    app(SendInvitationEmail::class)->handle($invitation, $tenant->trade_name, 'tok');

    $mail = null;
    Notification::assertSentOnDemand(InvitationEmail::class, function (InvitationEmail $notification, array $channels, AnonymousNotifiable $notifiable) use (&$mail): bool {
        $mail = $notification->toMail($notifiable);

        return true;
    });

    return $mail ?? throw new RuntimeException('No invitation email.');
}

it('says who invited the person, to which company and as what', function (): void {
    $mail = invitationEmail($this->tenant, $this->olga);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Olga te invitó a Autopartes Demo en Humetría')
        ->and($mail->actionText)->toBe('Aceptar invitación')
        ->and($mail->actionUrl)->toBe(url('admin/invitations/tok'))
        ->and($html)
        ->toContain('Entrarás como RH. La invitación vence en 7 días.')
        ->toMatch('#<h1[^>]*>Olga te invitó a Autopartes Demo en Humetría</h1>#')
        ->toContain('Entrarás como <strong')
        ->toContain('RH</strong>: podrás dar de alta centros de trabajo y empleados, lanzar evaluaciones y ver sus resultados.')
        ->toContain('La invitación vence el 6 de noviembre y solo funciona con lucia@autopartes.mx.')
        ->toContain('Si el botón no funciona, copia este enlace en tu navegador:')
        ->toContain('Recibes este correo porque Olga Owner te invitó. Si no esperabas esta invitación, ignórala: nadie entrará con tu correo.')
        ->toContain('alt="Humetría"')
        ->not->toContain('Confidencial');
});

it('writes what each role will do', function (TenantRole $role, string $line): void {
    expect((string) invitationEmail($this->tenant, $this->olga, $role)->render())->toContain($line);
})->with([
    'owner' => [TenantRole::Owner, 'Dueño</strong>: administrarás la cuenta, sus miembros y todo lo que RH lleva: NOM-035, NOM-037 y Ley Silla.'],
    'hr' => [TenantRole::Hr, 'RH</strong>: podrás dar de alta centros de trabajo y empleados, lanzar evaluaciones y ver sus resultados.'],
    'viewer' => [TenantRole::Viewer, 'Consulta</strong>: podrás ver sus centros de trabajo y, cuando haya evaluaciones, sus resultados.'],
]);

it('shows the company with its initials, its active work centers and its people', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    WorkCenter::factory()->for($this->tenant)->create();
    WorkCenter::factory()->for($this->tenant)->archived()->create();
    staffOf($this->tenant, $plant, 3);
    Employee::query()->withoutGlobalScopes()->firstOrFail()->update(['deactivated_at' => now()]);
    $other = Tenant::factory()->create();
    staffOf($other, WorkCenter::factory()->for($other)->create(), 4);

    expect((string) invitationEmail($this->tenant, $this->olga)->render())
        ->toContain('>AD</td>')
        ->toContain('2 centros de trabajo · 2 personas');
});

it('says one work center and one person in the singular, and a new company has none yet', function (): void {
    expect((string) invitationEmail($this->tenant, $this->olga)->render())->toContain('Aún sin centros de trabajo');

    staffOf($this->tenant, WorkCenter::factory()->for($this->tenant)->create(), 1);

    expect((string) invitationEmail($this->tenant, $this->olga, email: 'otra@autopartes.mx')->render())->toContain('1 centro de trabajo · 1 persona');
});

it('says only that the person was invited when whoever invited them is gone', function (): void {
    $mail = invitationEmail($this->tenant, null);

    expect($mail->subject)->toBe('Te invitaron a Autopartes Demo en Humetría')
        ->and((string) $mail->render())->toContain('Recibes este correo porque te invitaron a Autopartes Demo.');
});
