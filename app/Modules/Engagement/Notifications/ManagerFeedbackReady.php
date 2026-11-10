<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Notifications;

use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a manager, in usted, that the feedback of their team is ready in their portal. The link is all it carries: no
 * figure, no name of anyone and nothing a person wrote.
 */
final class ManagerFeedbackReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CompanySummary $company,
        private readonly string $name,
        private readonly string $survey,
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
        return (new MailMessage)
            ->subject('Ya puede ver la retroalimentación de su equipo')
            ->action('Ver la retroalimentación', $this->url)
            ->markdown('mail.engagement.feedback-ready', ['company' => $this->company, 'name' => $this->name, 'survey' => $this->survey]);
    }
}
