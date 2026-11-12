<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that carries a person's enrollment link, from their company and in «usted». Sent as the link is issued, never
 * queued: the plain token must not reach the queue.
 */
final class EnrollmentInvitation extends Notification implements MailBag
{
    public function __construct(private readonly string $url, private readonly string $name, private readonly CompanySummary $company) {}

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
            ->subject('Vincule su teléfono para registrar su jornada')
            ->greeting("Hola, {$this->name}")
            ->line("{$this->company->tradeName} registra la jornada de forma electrónica. Abra este enlace en su teléfono: ahí leerá el acuerdo del registro y creará una llave de acceso con su huella, su rostro o su PIN. No necesita contraseña.")
            ->action('Vincular mi teléfono', $this->url)
            ->line('El enlace sirve una sola vez y vence en '.config()->integer('attendance.enrollment.link_hours').' horas. Es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
