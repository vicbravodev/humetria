<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the owner that the company is back: whoever was told it had stopped hears that it works again. */
final class CompanyReactivated extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CompanySummary $company, private readonly ?string $supportEmail)
    {
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
        $name = $this->company->tradeName;

        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => false])
            ->subject("{$name} volvió a funcionar")
            ->greeting('Hola')
            ->line("{$name} volvió a funcionar: ya puedes entrar y tu gente puede usar Humetría como antes.")
            ->line($this->supportEmail === null
                ? 'Si algo no se ve como lo dejaste, contesta este correo.'
                : "Si algo no se ve como lo dejaste, escríbenos a {$this->supportEmail}.")
            ->action('Abrir Humetría', route('login'));
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
