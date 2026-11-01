<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner that someone in the telework roster changed work center, so their period ended there. Carries a name
 * and two work center names, no secret, so it can be queued.
 */
final class TeleworkMoved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $person,
        private readonly string $from,
        private readonly string $to,
        private readonly string $url,
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
            ->subject("{$this->person} cambió de centro de trabajo: terminó su teletrabajo")
            ->greeting('Hola')
            ->line("{$this->person} pasó de {$this->from} a {$this->to}. El padrón de teletrabajo y la Comisión son de cada centro, así que su teletrabajo en {$this->from} terminó con el motivo «cambio de centro».")
            ->line("Si va a seguir teletrabajando, registra a la persona como aspirante en {$this->to}: su lugar se valida con la lista de ese centro.")
            ->action('Ver el periodo que terminó', $this->url);
    }
}
