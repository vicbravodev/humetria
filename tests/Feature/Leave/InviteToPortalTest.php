<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Leave\Actions\InviteToPortal;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Notifications\WorkerInvitation;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(InviteToPortal::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'autopartes']));
    Notification::fake();
    $this->rh = User::factory()->create();
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Solís', 'email' => 'ana@autopartes.test']);
    $this->beto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz', 'email' => 'beto@autopartes.test']);
});

/**
 * @return array<string, string> the invitation sent, by address
 */
function leaveInvitationsSent(): array
{
    $links = [];
    Notification::assertSentOnDemand(WorkerInvitation::class, function (WorkerInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use (&$links): bool {
        $links[$notifiable->routes['mail']] = (string) $notification->toMail($notifiable)->actionUrl;

        return true;
    });

    return $links;
}

it('invites each person to their portal (WorkerAccess), issued by RH, and says how many went out', function (): void {
    $sent = resolve(InviteToPortal::class)->handle([$this->ana->id, $this->beto->id], $this->rh->id);

    $links = leaveInvitationsSent();

    expect($sent)->toBe(2)
        ->and(array_keys($links))->toBe(['ana@autopartes.test', 'beto@autopartes.test'])
        ->and($links['ana@autopartes.test'])->toStartWith(url('/me/autopartes/link/'))
        ->and(WorkerAccessLink::query()->where('employee_id', $this->ana->id)->sole()->issued_by)->toBe($this->rh->id);
});

it('skips whoever has no email, whoever left and whoever is of another company, and issues them nothing', function (): void {
    $noMail = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => null]);
    $gone = Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'email' => 'gone@autopartes.test']);
    $stranger = Employee::factory()->create(['email' => 'stranger@otra.test']);

    $sent = resolve(InviteToPortal::class)->handle([$noMail->id, $gone->id, $stranger->id, $this->ana->id], $this->rh->id);

    expect($sent)->toBe(1)
        ->and(array_keys(leaveInvitationsSent()))->toBe(['ana@autopartes.test'])
        ->and(WorkerAccessLink::query()->withoutGlobalScopes()->pluck('employee_id')->all())->toBe([$this->ana->id]);
});

it('sends nothing to nobody', function (): void {
    expect(resolve(InviteToPortal::class)->handle([], $this->rh->id))->toBe(0);

    Notification::assertNothingSent();
});

it('logs how many went out and who asked, never a link', function (): void {
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR);
    });
    $noMail = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => null]);

    resolve(InviteToPortal::class)->handle([$this->ana->id, $noMail->id], $this->rh->id);

    // Issuing the invitation writes WorkerAccess's own line.
    expect(array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'leave.'))))->toBe(['leave.portal_invitations_sent '.json_encode(['sent' => 1, 'asked' => 2, 'user_id' => $this->rh->id], JSON_THROW_ON_ERROR)])
        ->and(implode("\n", $lines))->not->toContain(basename(leaveInvitationsSent()['ana@autopartes.test']));
});
