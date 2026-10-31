<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person, in "usted", that their company published documents they have to confirm they read. The link is made as
 * the job runs, so the notification is sent at once and never queued: the plain token must not reach the queue.
 */
final class DocumentsToConfirm extends Notification
{
    public function __construct(private readonly string $url, private readonly string $name, private readonly string $company) {}

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
            ->subject('Tiene documentos por confirmar')
            ->greeting("Hola, {$this->name}")
            ->line("{$this->company} publicó documentos de teletrabajo que debe leer y confirmar.")
            ->action('Ver los documentos', $this->url)
            ->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }
}
