<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the person who stops owning a company that they no longer run it, so a change they did not expect does not go
 * unnoticed. Queued: it carries no secret, and a mail server that fails must not fail the acceptance.
 */
final class OwnerReplaced extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CompanySummary $company, private readonly string $name) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => false])
            ->subject("Ya no administras {$this->company->tradeName} en Humetría")
            ->greeting("Hola, {$this->name}")
            ->line("Ya no administras {$this->company->tradeName} en Humetría. Si crees que es un error, contesta este correo.")
            ->action('Abrir Humetría', route('login'));
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
