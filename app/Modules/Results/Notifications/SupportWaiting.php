<?php

declare(strict_types=1);

namespace App\Modules\Results\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person, as "usted", that their company left them a confidential message in their personal page (board
 * V4-N035-Canalizacion, «Aviso en su portal»). Nothing of what it is about: the message is read only in their session. The
 * address has no credential: they enter first.
 */
final class SupportWaiting extends Notification implements MailBag
{
    public function __construct(
        private readonly string $url,
        private readonly string $name,
        private readonly CompanySummary $company,
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
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => true])->subject("Tiene un mensaje de {$this->company->tradeName}")->greeting("Hola, {$this->name}")
            ->line("{$this->company->tradeName} le dejó un mensaje confidencial en su página personal. Solo usted puede leerlo.")
            ->action('Leer el mensaje', $this->url)
            ->line('Este enlace es solo suyo: no lo comparta.');
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}
