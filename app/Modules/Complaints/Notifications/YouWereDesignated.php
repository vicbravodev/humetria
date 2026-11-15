<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that the company designated them to handle its complaints (board V4-Correos-2, «Te designaron para atender
 * quejas»): for what, that they only see the cases that reach them, and where those are. Never anything about a case.
 */
final class YouWereDesignated extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CompanySummary $company,
        private readonly string $person,
        private readonly string $scope,
        private readonly string $url,
    ) {
        $this->afterCommit();
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
            ->subject('Te designaron para atender quejas')
            ->greeting("Hola, {$this->person}")
            ->line("{$this->company->tradeName} te designó para atender las quejas y denuncias {$this->scope}.")
            ->line('Entras con tu cuenta y solo ves los casos que te toquen. Nunca recibes una queja contra tu propio rol, y si un caso no te corresponde puedes excusarte.')
            ->action('Ver mis casos', $this->url)
            ->line('Si no esperabas este correo, avísale a quien administra la cuenta de la empresa.');
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}
