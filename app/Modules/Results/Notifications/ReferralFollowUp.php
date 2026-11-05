<?php

declare(strict_types=1);

namespace App\Modules\Results\Notifications;

use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminds whoever channeled a person, as «tú», to ask how they are 30 days later (board V4-N035-Canalizacion). No name, no
 * destination and no note: an email can leave the company, so who it is about is read in Humetría.
 */
final class ReferralFollowUp extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CompanySummary $company, private readonly string $url)
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
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company])->subject('Pregunta cómo sigue una persona que canalizaste')->greeting('Pasaron 30 días')
            ->line('Te pediste recordar que preguntaras cómo sigue una persona que canalizaste. Si ya la atendieron, regístralo.')
            ->line('Por confidencialidad, este correo no dice de quién se trata.')
            ->action('Ver la canalización', $this->url);
    }
}
