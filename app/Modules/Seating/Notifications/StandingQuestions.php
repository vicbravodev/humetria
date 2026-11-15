<?php

declare(strict_types=1);

namespace App\Modules\Seating\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * «2 preguntas sobre su trabajo de pie» (board V3-Correos): asks a person, as "usted", for their footwear and discomfort
 * when their assessment starts, with their link (made as the job runs). Answering is voluntary.
 */
final class StandingQuestions extends Notification implements MailBag
{
    public function __construct(private readonly CompanySummary $company, private readonly string $name, private readonly string $url) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => true])
            ->subject('2 preguntas sobre su trabajo de pie')
            ->greeting("Hola, {$this->name}")
            ->line('Queremos saber cómo es su calzado y si ha sentido alguna molestia al trabajar de pie. Con eso decidimos qué asiento y qué descansos le corresponden.')
            ->action('Contestar ahora', $this->url)
            ->line('Contestar es voluntario y toma un minuto. No tiene ninguna consecuencia si prefiere no hacerlo.')
            ->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}
