<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Notifications;

use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person, as "usted", that their company published its policy of prevention and that they can read it and confirm
 * they did (criterio C-53). The link is theirs (made as the job runs, so the notification is sent at once and never queued:
 * the plain token must not reach the queue).
 */
final class PolicyToConfirm extends Notification
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
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject('La política de su centro de trabajo')->greeting("Hola, {$this->name}")
            ->line("{$this->company->tradeName} publicó su política para prevenir riesgos psicosociales y la violencia laboral. Léala y confirme que la leyó; toma 3 minutos.")
            ->action('Leer y confirmar', $this->url)
            ->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }
}
