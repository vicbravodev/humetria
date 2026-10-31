<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person their company published documents they have to confirm they read, listing everything pending. An
 * employee is spoken to as "usted" and gets their personal portal link (made as the job runs, so the notification is
 * sent at once and never queued: the plain token must not reach the queue); a Comisión member is told "tú" and goes to the panel.
 */
final class DocumentsToConfirm extends Notification
{
    /**
     * @param  list<string>  $documents
     */
    public function __construct(
        private readonly string $url,
        private readonly string $name,
        private readonly string $company,
        private readonly array $documents,
        private readonly bool $personal,
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
        $mail = (new MailMessage)->subject($this->personal ? 'Tiene documentos por confirmar' : 'Tienes documentos por confirmar')->greeting("Hola, {$this->name}");

        $mail->line($this->personal
            ? "{$this->company} publicó documentos de teletrabajo que debe leer y confirmar:"
            : "{$this->company} publicó documentos de teletrabajo que debes leer y confirmar en el panel:");

        foreach ($this->documents as $document) {
            $mail->line("- {$document}");
        }

        $mail->action($this->personal ? 'Ver los documentos' : 'Ir a los documentos', $this->url);

        return $this->personal
            ? $mail->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.')
            : $mail;
    }
}
