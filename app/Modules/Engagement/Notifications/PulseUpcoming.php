<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells owner and hr, in the panel voice, that the scheduled pulse goes out in three days: the date, what it looks at, how
 * many questions and how many commitments of the last survey are still open, so they review before it is sent. Dates and
 * counts only: no result, no name, no answer.
 */
final class PulseUpcoming extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CompanySummary $company,
        private readonly CarbonImmutable $runsOn,
        private readonly string $modeLabel,
        private readonly int $items,
        private readonly int $openCommitments,
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
        $day = $this->runsOn->isoFormat('D [de] MMMM');
        $mail = (new MailMessage)->markdown('notifications::email', ['company' => $this->company])
            ->subject("Tu próximo pulso sale el {$day}")
            ->greeting('Hola')
            ->line("El pulso de {$this->company->tradeName} sale el {$day}: modalidad {$this->modeLabel}, {$this->items} preguntas.");

        if ($this->openCommitments > 0) {
            $mail->line(trans_choice('{1} Sigue :count compromiso abierto|[2,*] Siguen :count compromisos abiertos', $this->openCommitments, ['count' => $this->openCommitments]).' de la encuesta anterior. Conviene que la gente vea avance antes de volver a preguntarle.');
        }

        return $mail->line('Todavía puedes cambiar la modalidad, pausarlo o ajustar el día.')->action('Revisar antes de enviar', $this->url);
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}
