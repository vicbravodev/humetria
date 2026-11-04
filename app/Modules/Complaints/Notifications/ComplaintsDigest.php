<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email to a handler with what is new in their cases: counts, never a word of a case, a reference or a name. It goes in
 * the hourly sweep and not one per complaint: an email at the instant of a submission would tell its exact hour.
 */
final class ComplaintsDigest extends Notification implements ShouldQueue
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
        $mail = (new MailMessage)
            ->subject('Novedades en el canal de quejas')
            ->greeting($this->name === null ? 'Hola' : "Hola, {$this->name}")
            ->line("Esto espera tu atención en el canal de quejas de {$this->company}:");

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->action('Abrir los casos', $this->url);
    }
}
