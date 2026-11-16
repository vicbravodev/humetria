<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * RH's invitation to a person's portal, with the link to create their key, from their company and in «usted». Sent as the link
 * is issued, never queued: the plain token must not reach the queue.
 */
final class WorkerInvitation extends Notification implements MailBag
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
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => true])
            ->subject("Entre a su portal de {$this->company->tradeName}")
            ->greeting("Hola, {$this->name}")
            ->line("{$this->company->tradeName} le da acceso a su portal. Abra este enlace en su teléfono: ahí creará una llave de acceso con su huella, su rostro o su PIN. No necesita contraseña.")
            ->action('Crear mi llave', $this->url)
            ->line('El enlace sirve una sola vez y vence en '.config()->integer('worker_access.links.issued_hours').' horas. Es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
