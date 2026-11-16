<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The code a person without a key asked for to confirm it is them before something delicate, from their company and in
 * «usted». Sent as it is made (SendProofCode), never queued: the code must not reach the queue.
 */
final class ProofCode extends Notification implements MailBag
{
    public function __construct(public readonly string $code, private readonly string $name, private readonly CompanySummary $company) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => true])
            ->subject('Su código para confirmar que es usted')
            ->greeting("Hola, {$this->name}")
            ->line("Para continuar en su portal de {$this->company->tradeName}, escriba este código:")
            ->line("**{$this->code}**")
            ->line('Vence en '.config()->integer('worker_access.proof.code_minutes').' minutos. Es solo suyo: no lo comparta con nadie. Si usted no lo pidió, ignore este correo.')
            // The code is written in the page that asked for it; the button only leads back to the person's start.
            ->action('Ir a mi portal', rtrim(config()->string('app.url'), '/')."/me/{$this->company->slug}");
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
