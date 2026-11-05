<?php

declare(strict_types=1);

namespace App\Modules\Results\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person, as "usted", that their company left them a confidential message in their personal page (board
 * V4-N035-Canalizacion, «Aviso en su portal»). Nothing of what it is about: the message is read only with their link. The link
 * is theirs (made as the job runs, so the notification is sent at once and never queued: the plain token must not reach the
 * queue).
 */
final class SupportWaiting extends Notification
{
    public function __construct(
        private readonly string $url,
        private readonly string $name,
        private readonly string $company,
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
        return (new MailMessage)->subject("Tiene un mensaje de {$this->company}")->greeting("Hola, {$this->name}")
            ->line("{$this->company} le dejó un mensaje confidencial en su página personal. Solo usted puede leerlo.")
            ->action('Leer el mensaje', $this->url)
            ->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }
}
