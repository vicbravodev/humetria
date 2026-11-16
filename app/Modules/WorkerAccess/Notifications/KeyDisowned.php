<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the company's contact that a person said a key registered to them was not theirs: the key was revoked and their
 * sessions ended, and the person needs a new link from RH. In the panel's voice. Queued: it carries no secret.
 */
final class KeyDisowned extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly string $person, private readonly string $label, private readonly CompanySummary $company) {}

    /** @return list<int> Seconds before the second and the third try. */
    public function backoff(): array
    {
        return [60, 300];
    }

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
            ->subject("{$this->person} anuló una llave de acceso que no era suya")
            ->line("{$this->person} dijo que no registró la llave «{$this->label}». La anulamos y cerramos sus sesiones.")
            ->line('Para que vuelva a entrar, envíale un enlace nuevo y confirma con la persona quién tuvo acceso a su correo.')
            ->action('Abrir el panel', rtrim(config()->string('app.url'), '/')."/admin/{$this->company->slug}");
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
