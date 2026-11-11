<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Leave\Actions\SendPortalLinks;
use App\Modules\Leave\Notifications\VacationsPortalLink;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(SendPortalLinks::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    Notification::fake();
    $this->rh = User::factory()->create();
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Solís', 'email' => 'ana@autopartes.test']);
    $this->beto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz', 'email' => 'beto@autopartes.test']);
});

/**
 * @return array<string, string> the link sent, by address
 */
function leaveLinksSent(): array
{
    $links = [];
    Notification::assertSentOnDemand(VacationsPortalLink::class, function (VacationsPortalLink $notification, array $channels, AnonymousNotifiable $notifiable) use (&$links): bool {
        $links[$notifiable->routes['mail']] = (string) $notification->toMail($notifiable)->actionUrl;

        return true;
    });

    return $links;
}

it('sends each person the link of their own «Mis vacaciones», and says how many went out', function (): void {
    $sent = resolve(SendPortalLinks::class)->handle([$this->ana->id, $this->beto->id], $this->rh->id);

    expect($sent)->toBe(2)
        ->and(leaveLinksSent())->toBe([
            'ana@autopartes.test' => route('portal.vacations.show', resolve(PortalAccess::class)->token($this->ana->id)),
            'beto@autopartes.test' => route('portal.vacations.show', resolve(PortalAccess::class)->token($this->beto->id)),
        ]);
});

it('writes to the person as usted, with one button and no amounts', function (): void {
    resolve(SendPortalLinks::class)->handle([$this->ana->id], $this->rh->id);

    Notification::assertSentOnDemand(VacationsPortalLink::class, function (VacationsPortalLink $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $notification->toMail($notifiable);

        expect($channels)->toBe(['mail'])
            ->and($mail->subject)->toBe('Consulte sus vacaciones')
            ->and($mail->greeting)->toBe('Hola, Ana Solís')
            ->and($mail->actionText)->toBe('Ver mis vacaciones')
            ->and(implode("\n", [...$mail->introLines, ...$mail->outroLines]))->toBe("Su empresa lleva sus vacaciones en Humetría. En su enlace personal puede ver los días que tiene, pedirlos y seguir sus solicitudes.\nEste enlace es solo suyo: no lo comparta.");

        return true;
    });
});

it('skips whoever has no email, whoever left and whoever is of another company', function (): void {
    $noMail = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => null]);
    $gone = Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'email' => 'gone@autopartes.test']);
    $stranger = Employee::factory()->create(['email' => 'stranger@otra.test']);

    $sent = resolve(SendPortalLinks::class)->handle([$noMail->id, $gone->id, $stranger->id, $this->ana->id], $this->rh->id);

    expect($sent)->toBe(1)
        ->and(array_keys(leaveLinksSent()))->toBe(['ana@autopartes.test']);
});

it('sends nothing to nobody', function (): void {
    expect(resolve(SendPortalLinks::class)->handle([], $this->rh->id))->toBe(0);

    Notification::assertNothingSent();
});

it('logs how many went out and who asked, never a link', function (): void {
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR);
    });
    $noMail = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => null]);

    resolve(SendPortalLinks::class)->handle([$this->ana->id, $noMail->id], $this->rh->id);

    // Issuing a link the first time writes Telework's own line.
    expect(array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'leave.'))))->toBe(['leave.portal_links_sent '.json_encode(['sent' => 1, 'asked' => 2, 'user_id' => $this->rh->id], JSON_THROW_ON_ERROR)])
        ->and(implode("\n", $lines))->not->toContain(resolve(PortalAccess::class)->token($this->ana->id));
});
