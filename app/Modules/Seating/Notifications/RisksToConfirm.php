<?php

declare(strict_types=1);

namespace App\Modules\Seating\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person, as "usted", that their company assessed their work standing and that they can read the result and confirm
 * it was explained to them. The link is theirs (made as the job runs, so the plain token never reaches the queue).
 */
final class RisksToConfirm extends Notification implements MailBag
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
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject('Conozca el resultado de la evaluación de su trabajo de pie')->greeting("Hola, {$this->name}")
            ->line("{$this->company->tradeName} evaluó los riesgos de su trabajo de pie y definió medidas para cuidarle.")
            ->line('Puede ver el resultado y confirmar que se lo explicaron desde su página personal.')
            ->action('Ver y confirmar', $this->url)
            ->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}
