<?php

declare(strict_types=1);

namespace App\Modules\Seating\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The daily email to the owner or a Comisión member: counts of what waits for them in Ley Silla, in the panel voice ("tú").
 * Counts only, never a name, a note or an answer.
 */
final class SeatingDigest extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        private readonly CompanySummary $company,
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
        $mail = (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject('Resumen de Ley Silla')->greeting($this->name === null ? 'Hola' : "Hola, {$this->name}")
            ->line("Esto espera tu atención en {$this->company->tradeName}:");

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->action('Abrir Humetría', $this->url);
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}
