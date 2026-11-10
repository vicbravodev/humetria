<?php

declare(strict_types=1);

namespace App\Modules\Leave\Notifications;

use App\Modules\Leave\Support\RequestSummary;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a manager, as "usted", that someone of their team asked for vacations or for a leave and that it is theirs to
 * answer, from their own portal. It never says which leave (criterio C-22) nor the person's note.
 */
final class RequestToDecide extends Notification
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
        $mail = (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject("{$this->requester} pidió {$this->request->what()}")->greeting("Hola, {$this->name}")
            ->line("{$this->requester} pidió {$this->request->what()} {$this->request->span()}.");

        if ($this->request->hadBalance !== null) {
            $mail->line($this->request->hadBalance ? 'Tiene días disponibles.' : 'No tiene días disponibles.');
        }

        return $mail->action('Revisar solicitud', $this->url)->line('Le toca a usted responder. Este enlace es solo suyo: no lo comparta.');
    }
}
