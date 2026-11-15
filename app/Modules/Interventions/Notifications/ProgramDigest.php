<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The Monday email about the Programas de intervención, to the owner (everything that waits) and to each responsible member
 * (their own actions): counts in lines that appear only when they are not zero, in the panel voice ("tú"). Counts only, never
 * a title, a name or a text HR wrote: free text may carry data of a person.
 */
final class ProgramDigest extends Notification implements MailBag, ShouldQueue
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
        $mail = (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject('Resumen de tus Programas de intervención')->greeting($this->name === null ? 'Hola' : "Hola, {$this->name}")
            ->line("Esto espera tu atención en los Programas de intervención de {$this->company->tradeName}:");

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
