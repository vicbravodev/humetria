<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a Comisión member, as "tú", their company published documents they have to confirm they read, and sends them to the panel.
 * Employees get `PortalReminder` instead.
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
        $mail = (new MailMessage)->subject('Tienes documentos por confirmar')->greeting("Hola, {$this->name}");

        $mail->line("{$this->company} publicó documentos de teletrabajo que debes leer y confirmar en el panel:");

        foreach ($this->documents as $document) {
            $mail->line("- {$document}");
        }

        return $mail->action('Ir a los documentos', $this->url);
    }
}
