<?php

declare(strict_types=1);

namespace App\Modules\Demos\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the platform's superadmin that someone asked for a demo. It names who and from which company and opens the
 * console, where the contact is: the email itself carries no phone or address of the person.
 */
final class DemoRequested extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $company, private readonly string $name, private readonly string $requestId) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('notifications::email', ['company' => null, 'formal' => false])
            ->subject("Nueva solicitud de demo: {$this->company}")
            ->greeting('Hola')
            ->line("{$this->name} pidió una demo para {$this->company}.")
            ->line('Escríbele pronto: quien pide una demo y espera días se enfría.')
            ->action('Ver la solicitud', route('demo-requests.index', ['request' => $this->requestId]));
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
