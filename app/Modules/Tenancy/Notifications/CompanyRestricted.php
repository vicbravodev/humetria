<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Enums\TenantRestriction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner that their company is now read-only, suspended or archived, why, and where to write. A person who can no
 * longer sign in would never see an in-app notice, so it goes by email, after the change is committed. It names no amount.
 */
final class CompanyRestricted extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CompanySummary $company,
        private readonly TenantRestriction $restriction,
        private readonly string $reason,
        private readonly ?string $supportEmail,
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
        $name = $this->company->tradeName;
        [$subject, $what] = match ($this->restriction) {
            TenantRestriction::ReadOnly => ["{$name} quedó en solo lectura", "{$name} quedó en solo lectura: tu gente puede consultar y descargar lo que ya hay, pero no se puede cambiar nada ni mandar nada nuevo."],
            TenantRestriction::Suspended => ["{$name} quedó suspendida", "{$name} quedó suspendida: por ahora nadie puede entrar, y las encuestas, los enlaces y los dispositivos dejan de funcionar. Tus datos siguen guardados."],
            TenantRestriction::Archived => ["{$name} quedó archivada", "{$name} quedó archivada: ya no se usa, pero sus datos siguen guardados."],
        };

        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => false])
            ->subject($subject)
            ->greeting('Hola')
            ->line($what)
            ->line("Motivo: {$this->reason}")
            ->line($this->supportEmail === null
                ? 'Si quieres volver a usarla o tienes dudas, contesta este correo.'
                : "Si quieres volver a usarla o tienes dudas, escríbenos a {$this->supportEmail}.")
            ->action('Abrir Humetría', route('login'));
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
