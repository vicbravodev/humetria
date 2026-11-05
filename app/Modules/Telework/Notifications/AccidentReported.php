<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells RH, as "tú", that a person reported an accident (rediseño v2, board V3-Correos), at once, or again when three days
 * passed without anyone recording what was done. Who, where and when it happened, never what the person wrote: that is
 * read in Humetría.
 */
final class AccidentReported extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CompanySummary $company,
        private readonly string $person,
        private readonly string $workCenter,
        private readonly string $occurredOn,
        private readonly string $url,
        private readonly bool $reminder = false,
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
        $mail = $this->reminder
            ? (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject("Sigue sin respuesta el aviso de {$this->person}")->greeting('Un aviso de accidente espera')
                ->line('Pasaron 3 días y nadie ha registrado qué se hizo.')
            : (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject("{$this->person} avisó un accidente de trabajo")->greeting('Llegó un aviso de accidente');

        $mail->line("{$this->person} · {$this->workCenter} · ocurrió el {$this->occurredOn}");

        if (! $this->reminder) {
            $mail->line('Revísalo y registra qué se hizo. Si pasan 3 días sin respuesta, te lo volvemos a recordar.');
        }

        return $mail->action('Revisar aviso', $this->url);
    }
}
