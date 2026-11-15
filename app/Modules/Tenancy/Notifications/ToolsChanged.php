<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Notifications;

use App\Models\User;
use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;

/**
 * Tells the owner which tools their company has gained and which it loses, and from when: one email for the whole change,
 * however many tools a plan moves. It never names an amount.
 */
final class ToolsChanged extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $added  names of the tools now on.
     * @param  list<string>  $removed  names of the tools that stop.
     * @param  string|null  $removedOn  the day they stop, as the panel writes it ("1 dic 2026").
     * @param  bool  $removedNow  they stop today or already stopped: the notice says "desde hoy", not a day that is not in the future.
     */
    public function __construct(
        private readonly CompanySummary $company,
        private readonly array $added,
        private readonly array $removed,
        private readonly ?string $removedOn,
        private readonly bool $removedNow,
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
        $mail = (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => false])
            ->subject("Cambiaron las herramientas de {$this->company->tradeName}")
            ->greeting($notifiable instanceof User ? "Hola, {$notifiable->name}" : 'Hola');

        if ($this->added !== []) {
            $mail->line('Ya tienes disponible: '.Arr::join($this->added, ', ', ' y ').'.');
        }

        if ($this->removed !== []) {
            $mail->line('Dejan de estar disponibles '.($this->removedNow ? 'desde hoy' : 'el '.($this->removedOn ?? 'día que indique tu plan')).': '.Arr::join($this->removed, ', ', ' y ').'. Lo que ya capturaste se queda guardado y vuelve contigo si las recuperas.');
        }

        return $mail
            ->line($this->supportEmail === null
                ? 'Si quieres ampliar tu plan o tienes dudas, contesta este correo.'
                : "Si quieres ampliar tu plan o tienes dudas, escríbenos a {$this->supportEmail}.")
            ->action('Abrir Humetría', route('login'));
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
