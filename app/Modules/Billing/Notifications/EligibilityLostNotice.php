<?php

declare(strict_types=1);

namespace App\Modules\Billing\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a consultant that at the next cut it will have fewer Integral companies than the discount of the channel asks for. */
final class EligibilityLostNotice extends Notification implements MailBag
{
    /** @param  list<string>  $leaving  the companies whose plan ends at the cut. */
    public function __construct(private readonly string $accountName, private readonly array $leaving, private readonly int $minimum, private readonly string $period) {}

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Su descuento de canal termina con el corte')
            ->greeting("Hola, {$this->accountName}")
            ->line("El descuento de canal pide al menos {$this->minimum} empresas con Integral pagado. Con el corte del 1 de {$this->period} terminan estas:");

        foreach ($this->leaving as $company) {
            $mail->line($company);
        }

        return $mail->line('Desde esa renovación se cobra la tarifa directa, sin periodo de gracia. Si quiere conservar el descuento, escríbanos antes del corte.');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
