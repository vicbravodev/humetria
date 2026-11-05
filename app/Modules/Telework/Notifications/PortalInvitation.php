<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that carries a person's portal link, from their company and written as the portal speaks ("usted"). Sent as the job that made the
 * link runs, never queued: the plain token must not reach the queue.
 */
final class PortalInvitation extends Notification
{
    public function __construct(private readonly string $token, private readonly string $name, private readonly CompanySummary $company) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company])
            ->subject('Su espacio de trabajo en casa: una lista de 10 minutos')
            ->greeting("Hola, {$this->name}")
            ->line('Para que pueda trabajar desde casa, necesitamos saber cómo es su espacio: luz, silla, instalación eléctrica. Son unas preguntas y, si usted quiere, unas fotos.')
            ->action('Contestar la lista', route('portal.show', $this->token))
            ->line('Solo lo ven Recursos Humanos y la Comisión de Seguridad e Higiene. Puede contestar desde su celular.')
            ->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }
}
