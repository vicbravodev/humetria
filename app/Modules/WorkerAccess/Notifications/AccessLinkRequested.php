<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The link a person asked for to enter their portal, from their company and in «usted». Sent as the link is made (in its job),
 * never queued on its own: the plain token must not reach the queue.
 */
final class AccessLinkRequested extends Notification implements MailBag
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
            ->subject("Su enlace para entrar a su portal de {$this->company->tradeName}")
            ->greeting("Hola, {$this->name}")
            ->line('Pidió un enlace para entrar a su portal. Ábralo en el dispositivo donde quiere entrar.')
            ->action('Entrar a mi portal', $this->url)
            ->line('El enlace sirve una sola vez y vence en '.config()->integer('worker_access.links.requested_minutes').' minutos. Es solo suyo: no lo comparta. Si usted no lo pidió, ignore este correo.');
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
