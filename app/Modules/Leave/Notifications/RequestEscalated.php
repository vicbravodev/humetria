<?php

declare(strict_types=1);

namespace App\Modules\Leave\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Support\RequestSummary;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner a request reached RH (criterio C-18): nobody active above the person, or their manager did not answer
 * in time. It never says which leave (criterio C-22) nor the person's note.
 */
final class RequestEscalated extends Notification implements MailBag
{
    public function __construct(
        private readonly string $url,
        private readonly CompanySummary $company,
        private readonly string $requester,
        private readonly EscalationReason $reason,
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
        $days = config()->integer('leave.escalation_business_days');

        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject("Una solicitud de {$this->requester} espera tu respuesta")
            ->line($this->reason === EscalationReason::NoManager ? "{$this->requester} no tiene un jefe activo que le responda." : "{$this->requester} sigue sin respuesta. Su jefe no respondió en {$days} días hábiles.")
            ->line('Pidió '.$this->request->what()." {$this->request->span()}.")
            ->action('Revisar solicitud', $this->url);
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
