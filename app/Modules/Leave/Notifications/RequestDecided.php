<?php

declare(strict_types=1);

namespace App\Modules\Leave\Notifications;

use App\Modules\Leave\Support\RequestSummary;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person, as "usted", that their request was approved or rejected, with the reason of a rejection and the link of
 * their own portal. It never says which leave it was (criterio C-22) and never an amount.
 */
final class RequestDecided extends Notification
{
    public function __construct(
        private readonly string $url,
        private readonly CompanySummary $company,
        private readonly string $name,
        private readonly RequestSummary $request,
        private readonly bool $approved,
        private readonly ?string $reason,
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
        $answer = $this->approved ? 'aprobada' : 'rechazada';
        $mail = (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject("Su solicitud fue {$answer}")->greeting("Hola, {$this->name}")
            ->line("Su solicitud de {$this->request->what()} {$this->request->span()} fue {$answer}.");

        if (! $this->approved && $this->reason !== null) {
            $mail->line("Motivo: {$this->reason}");
        }

        return $mail->action('Ver mis vacaciones', $this->url);
    }
}
