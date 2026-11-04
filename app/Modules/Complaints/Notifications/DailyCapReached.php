<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells who manages the channel that it hit its daily cap: a count, never a word of a complaint. */
final class DailyCapReached extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $company, private readonly int $cap, private readonly string $url)
    {
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
        return (new MailMessage)
            ->subject('El canal de quejas llegó a su tope del día')
            ->greeting('Hola')
            ->line("El canal de quejas de {$this->company} recibió hoy {$this->cap} quejas, su tope diario, y no recibirá más hasta mañana.")
            ->line('Puede ser un uso normal o un intento de saturarlo. Revisa el canal y a quién llega cada queja.')
            ->action('Abrir el canal', $this->url);
    }
}
