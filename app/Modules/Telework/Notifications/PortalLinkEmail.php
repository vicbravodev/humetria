<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The link of a person's portal, sent again by RH: neutral, because the portal holds whatever tools the company uses.
 * Written as the portal speaks ("usted"). Sent as it is built, never queued: the plain token must not reach the queue.
 */
final class PortalLinkEmail extends Notification implements MailBag
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
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => true])
            ->subject('Su enlace personal')
            ->greeting("Hola, {$this->name}")
            ->line('Recursos Humanos de su empresa le envía de nuevo su enlace personal. Ahí encuentra lo que su empresa tiene para usted: vacaciones, avisos y lo que le pidan.')
            ->action('Abrir mi portal', route('portal.show', $this->token))
            ->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
