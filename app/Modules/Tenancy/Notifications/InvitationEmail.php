<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Notifications;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Support\DisplayDate;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that carries the one-time link to join a company with a role. The plain token lives only here and in the link
 * (and in the queued job, encrypted, so neither Redis nor the Horizon dashboard shows it).
 */
final class InvitationEmail extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tradeName,
        private readonly string $name,
        private readonly string $token,
        private readonly CarbonImmutable $expiresAt,
        private readonly TenantRole $role = TenantRole::Owner,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $line] = match ($this->role) {
            TenantRole::Owner => [
                "Te invitaron a Humetría como owner de {$this->tradeName}",
                'Vas a administrar su cuenta, desde donde RH lleva la NOM-035, la NOM-037 y la Ley Silla de su personal.',
            ],
            TenantRole::Hr => [
                "Te invitaron a Humetría como RH de {$this->tradeName}",
                'Vas a llevar sus centros de trabajo y su personal, y sus evaluaciones de NOM-035, NOM-037 y Ley Silla.',
            ],
            TenantRole::Viewer => [
                "Te invitaron a consultar {$this->tradeName} en Humetría",
                'Vas a poder ver sus centros de trabajo y, cuando haya evaluaciones, sus resultados.',
            ],
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hola, {$this->name}")
            ->line($line)
            ->action('Aceptar invitación', route('invitations.show', $this->token))
            ->line('El enlace vence el '.DisplayDate::day($this->expiresAt).'.')
            ->line('Si no esperabas este correo, ignóralo.');
    }
}
