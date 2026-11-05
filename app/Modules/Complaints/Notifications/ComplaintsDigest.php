<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Notifications;

use App\Modules\Tenancy\Data\CompanySummary;
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
     * @param  int  $open  the open cases assigned to the person, what the subject counts
     * @param  list<string>  $lines
     */
    public function __construct(
        private readonly CompanySummary $company,
        private readonly ?string $name,
        private readonly int $open,
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
        $mail = (new MailMessage)->markdown('notifications::email', [
            'company' => $this->company,
            'reason' => "Lo recibes porque te designaron para atender quejas en {$this->company->tradeName}.",
        ])
            ->subject(trans_choice('{0} Novedades en el canal de quejas|{1} Tienes :count caso por atender|[2,*] Tienes :count casos por atender', $this->open))
            ->greeting($this->name === null ? 'Hola' : "Hola, {$this->name}");

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->line('Por privacidad, este correo no incluye lo que dicen.')->action('Ver mis casos', $this->url);
    }
}
