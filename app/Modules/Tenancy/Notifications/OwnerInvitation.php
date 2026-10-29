<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Notifications;

use App\Modules\Tenancy\Support\DisplayDate;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that carries the one-time link for a company's owner. The plain token lives only here and in the link.
 */
final class OwnerInvitation extends Notification
{
    public function __construct(
        private readonly string $tradeName,
        private readonly string $name,
        private readonly string $token,
        private readonly CarbonImmutable $expiresAt,
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
        return (new MailMessage)
            ->subject("Te invitaron a Humetría como owner de {$this->tradeName}")
            ->greeting("Hola, {$this->name}")
            ->line('Vas a administrar su cuenta, desde donde RH lleva la NOM-035, la NOM-037 y la Ley Silla de su personal.')
            ->action('Aceptar invitación', route('invitations.show', $this->token))
            ->line('El enlace vence el '.DisplayDate::day($this->expiresAt).'.')
            ->line('Si no esperabas este correo, ignóralo.');
    }
}
