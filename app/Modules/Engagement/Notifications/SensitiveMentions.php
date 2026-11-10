<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Notifications;

use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells who manages the complaints channel that the comments of a survey had mentions of possible harassment or
 * violence: that there were, never how many, from whom or what they said.
 */
final class SensitiveMentions extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CompanySummary $company, private readonly string $survey, private readonly string $url)
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
        return (new MailMessage)
            ->subject('Hubo menciones de posible acoso o violencia en los comentarios')
            ->action('Abrir el canal de quejas', $this->url)
            ->markdown('mail.engagement.sensitive-mentions', ['company' => $this->company, 'survey' => $this->survey]);
    }
}
