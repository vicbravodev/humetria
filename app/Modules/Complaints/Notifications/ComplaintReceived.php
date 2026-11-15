<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The confirmation that a complaint came (decisión A10), to the address the person left: when it will be acknowledged and
 * answered, and the link to follow it up. No reference, no word of the case, no company's name. Not queued: a queued
 * notice would leave the address in the queue's payload.
 */
final class ComplaintReceived extends Notification implements MailBag
{
    public function __construct(
        private readonly string $url,
        private readonly int $acknowledgeWithinDays,
        private readonly int $resolveWithinDays,
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
            ->subject('Recibimos su queja')
            ->greeting('Hola')
            ->line("Recibimos la queja que presentó en el canal. Le confirmaremos que alguien la leyó en {$this->acknowledgeWithinDays} días y le responderemos en {$this->resolveWithinDays} días.")
            ->line('Para ver cómo va necesitará la referencia y la clave que recibió al presentarla. Este correo no dice nada del caso.')
            ->action('Ver cómo va', $this->url)
            ->line('Si no esperaba este correo, no necesita hacer nada.');
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}
