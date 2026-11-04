<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The Monday email about the policy and its disclosure, to the owner: what waits, in lines that appear only when they are not
 * zero, in the panel voice ("tú"). Counts only: never a center, a title or a text the company wrote.
 */
final class DisclosureDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        private readonly string $company,
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
        $mail = (new MailMessage)->subject('Resumen de tu política y su difusión')->greeting('Hola')
            ->line("Esto espera tu atención en la política de prevención y su difusión de {$this->company}:");

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->action('Abrir Humetría', $this->url);
    }
}
