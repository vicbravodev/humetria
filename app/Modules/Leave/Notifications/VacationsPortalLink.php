<?php

declare(strict_types=1);

namespace App\Modules\Leave\Notifications;

use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Gives a person, as "usted", the link of «Mis vacaciones» in their own portal. No days and no amounts: those are read
 * behind the link.
 */
final class VacationsPortalLink extends Notification
{
    public function __construct(
        private readonly string $url,
        private readonly CompanySummary $company,
        private readonly string $name,
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
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject('Consulte sus vacaciones')->greeting("Hola, {$this->name}")
            ->line('Su empresa lleva sus vacaciones en Humetría. En su enlace personal puede ver los días que tiene, pedirlos y seguir sus solicitudes.')
            ->action('Ver mis vacaciones', $this->url)
            ->line('Este enlace es solo suyo: no lo comparta.');
    }
}
