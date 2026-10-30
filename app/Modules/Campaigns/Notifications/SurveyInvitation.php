<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that carries a person's survey link. Written as the survey speaks ("usted"). The plain token lives only
 * here and in the link.
 */
final class SurveyInvitation extends Notification
{
    public function __construct(
        private readonly string $tradeName,
        private readonly string $token,
        private readonly string $lastDay,
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
            // Formal, as the survey: the fine print under the button says "usted" too.
            ->markdown('notifications::email', ['formal' => true])
            ->subject("{$this->tradeName} le invita a contestar una evaluación confidencial")
            ->greeting('Hola')
            ->line("{$this->tradeName} aplica una evaluación de factores de riesgo psicosocial en el trabajo, como lo pide la NOM-035-STPS-2018. Toma unos minutos y la puede contestar desde su celular.")
            ->line("Puede contestar hasta el {$this->lastDay}.")
            ->action('Contestar evaluación', route('survey.show', $this->token))
            ->line('Sus respuestas sobre su trabajo son anónimas: nadie de su empresa puede ver lo que contesta. La parte sobre acontecimientos traumáticos sí lleva su nombre, para que la empresa pueda ofrecerle atención si la necesita.')
            ->line('Este enlace es personal: no lo comparta.');
    }
}
