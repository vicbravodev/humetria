<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The only thing the system ever writes to the address a person left: that there is news in their case, with a link to the
 * page where they follow it up. No reference, no word of the case, no company's name. It is deliberately not queued: a queued
 * notice would leave the address in the queue's payload.
 */
final class NewsOnYourCase extends Notification
{
    public function __construct(private readonly string $url) {}

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
            ->subject('Hay novedades en su caso')
            ->greeting('Hola')
            ->line('Hay novedades en el caso que usted presentó en el canal de quejas. Para leerlas necesitará la referencia y la clave que recibió al presentarlo.')
            ->line('Este aviso no dice nada del caso. Si no esperaba este correo, no necesita hacer nada.')
            ->action('Ver mi caso', $this->url);
    }
}
