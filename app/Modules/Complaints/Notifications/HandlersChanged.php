<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Notifications;

use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the other handlers that the list of who handles the company's complaints changed. The company can always remove a
 * handler; the notice is what keeps that visible. It says who and what, never anything about a case.
 */
final class HandlersChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CompanySummary $company,
        private readonly string $person,
        private readonly bool $designated,
        private readonly string $url,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $change = $this->designated ? "{$this->person} fue designada o designado para atender" : "{$this->person} dejó de atender";

        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company])
            ->subject('Cambió quién atiende el canal de quejas')
            ->greeting('Hola')
            ->line("En {$this->company->tradeName}, {$change} las quejas y denuncias del canal.")
            ->line('Recibes este aviso porque también las atiendes. Si no esperabas el cambio, avísale a quien administra la cuenta.')
            ->action('Ver quién atiende', $this->url);
    }
}
