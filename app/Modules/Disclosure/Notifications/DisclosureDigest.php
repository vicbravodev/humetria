<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The Monday email about the policy and its disclosure, to the owner: what waits, in lines that appear only when they are not
 * zero, in the panel voice ("tú"). Counts only: never a center, a title or a text the company wrote.
 */
final class DisclosureDigest extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lines
     * @param  array{confirmed: int, total: int, pending: int}  $confirmations  the people asked to confirm the policies in force
     */
    public function __construct(
        private readonly CompanySummary $company,
        private readonly array $lines,
        private readonly string $url,
        private readonly array $confirmations,
        private readonly string $remindUrl,
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
        $pending = $this->confirmations['pending'];

        if ($pending === 0) {
            $mail = (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject('Resumen de tu política y su difusión')->greeting('Hola')
                ->line("Esto espera tu atención en la política de prevención y su difusión de {$this->company->tradeName}:");

            return $this->lines($mail)->action('Abrir Humetría', $this->url);
        }

        // While people are missing (board V4-Correos-2): how many, and one button to remind them.
        $mail = (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'reason' => 'Te lo enviamos cada lunes mientras falten confirmaciones.'])
            ->subject(trans_choice('{1} Falta :count persona por confirmar la política|[2,*] Faltan :count personas por confirmar la política', $pending))
            ->greeting('Hola')
            ->line("{$this->confirmations['confirmed']} de {$this->confirmations['total']} personas confirmaron que leyeron la política de prevención. Puedes mandarles un recordatorio o imprimir la lista para que firmen en papel.");

        return $this->lines($mail)->action("Recordar a {$pending}", $this->remindUrl);
    }

    private function lines(MailMessage $mail): MailMessage
    {
        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail;
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}
