<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person, as "usted", that their company answered their accident notice, with their personal link (made as the job
 * runs, so it is sent at once and never queued: the plain token must not reach the queue). What was done is read in the
 * portal, never here.
 */
final class NoticeAnswered extends Notification
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
        return (new MailMessage)->subject('Su empresa respondió su aviso')->greeting("Hola, {$this->name}")
            ->line("{$this->company} leyó el aviso de accidente que envió y registró lo que se hizo. Puede verlo en su portal.")
            ->action('Ver mi aviso', $this->url)
            ->line('Este enlace es solo suyo: no lo comparta.');
    }
}
