<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Notifications;

use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person, as «usted», that their company published what it will do about the climate survey, with their own link
 * to read it (the link is made as the job runs, so the plain token never reaches the queue). It says nothing of what
 * anyone answered.
 */
final class FeedbackPublished extends Notification
{
    public function __construct(
        private readonly string $url,
        private readonly string $name,
        private readonly CompanySummary $company,
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
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject('Lo que dijeron en la encuesta y lo que haremos')->greeting("Hola, {$this->name}")
            ->line("{$this->company->tradeName} compartió lo que escuchó en la encuesta de clima y lo que hará al respecto: qué dijeron los equipos, quién responde y para cuándo.")
            ->action('Ver lo que haremos', $this->url)
            ->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }
}
