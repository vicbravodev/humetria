<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The daily email to the owner or a Comisión member: counts of what is waiting for them, in the panel voice ("tú"). Counts
 * only, never a name or a note. The Comisión's says in its subject how many things wait, and opens its queue (rediseño
 * v2, boards V3-Correos and V2-Comision).
 */
final class TeleworkDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        private readonly string $company,
        private readonly ?string $name,
        private readonly array $lines,
        private readonly string $url,
        private readonly ?int $pending = null,
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
        $subject = $this->pending === null ? 'Resumen de teletrabajo' : "Tienes {$this->pending} pendientes como integrante de la Comisión";
        $mail = (new MailMessage)->subject($subject)->greeting($this->name === null ? 'Hola' : "Hola, {$this->name}")
            ->line("Esto espera tu atención en {$this->company}:");

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->action($this->pending === null ? 'Abrir Humetría' : 'Ver mis pendientes', $this->url);
    }
}
