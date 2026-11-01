<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that carries a person's portal link, from their company and written as the portal speaks ("usted"). Sent as the job that made the
 * link runs, never queued: the plain token must not reach the queue.
 */
final class PortalInvitation extends Notification
{
    public function __construct(private readonly string $token, private readonly string $name, private readonly string $company) {}

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
            ->subject("{$this->company} le abrió su portal de teletrabajo")
            ->greeting("Hola, {$this->name}")
            ->line("{$this->company} le registró para teletrabajar. En su portal verá lo que le piden, como contestar la lista de verificación de su lugar de trabajo.")
            ->action('Abrir mi portal', route('portal.show', $this->token))
            ->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }
}
