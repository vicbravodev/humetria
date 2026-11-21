<?php

declare(strict_types=1);

namespace App\Modules\Leave\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Leave\Support\RequestSummary;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a manager, as "usted", that someone of their team cancelled what the manager had approved, so the dates are free
 * again. It never says which leave (criterio C-22) nor the person's note.
 */
final class RequestCancelled extends Notification implements MailBag
{
    public function __construct(
        private readonly string $url,
        private readonly CompanySummary $company,
        private readonly string $name,
        private readonly string $requester,
        private readonly RequestSummary $request,
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
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => true])->subject("{$this->requester} canceló lo que usted aprobó")->greeting("Hola, {$this->name}")
            ->line("{$this->requester} canceló su solicitud de {$this->request->what()} {$this->request->span()}.")
            ->action('Ver mi equipo', $this->url)->line('Este enlace es solo suyo: no lo comparta.');
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
