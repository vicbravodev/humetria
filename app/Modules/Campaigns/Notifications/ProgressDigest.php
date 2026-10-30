<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The owner's Monday email about an open evaluation: answered against the target, the areas that lack the most and a
 * way to remind whoever is missing. Counts only.
 */
final class ProgressDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{name: string, completed: int, total: int}>  $areas
     * @param  array{name: string, pending: int}|null  $behind  the area with the most people still to answer.
     */
    public function __construct(
        private readonly string $workCenter,
        private readonly int $completed,
        private readonly int $target,
        private readonly int $daysLeft,
        private readonly array $areas,
        private readonly ?array $behind,
        private readonly int $toRemind,
        private readonly string $url,
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
        $headline = "{$this->workCenter} va en {$this->completed} de {$this->target}";
        $lastDay = $this->daysLeft <= 1;

        return (new MailMessage)
            ->subject($headline.' · '.($lastDay ? 'hoy es el último día' : "faltan {$this->daysLeft} días"))
            ->action($this->toRemind > 0 ? 'Recordar a '.trans_choice('{1} :count persona|[2,*] :count personas', $this->toRemind) : 'Ver avance', $this->url)
            ->markdown('mail.campaigns.digest', [
                'headline' => $headline,
                'summary' => ($lastDay ? 'Hoy es el último día.' : "Quedan {$this->daysLeft} días.").' '.($this->behind === null
                    ? 'Ya contestaron todas las personas.'
                    : "{$this->behind['name']} es el área que más falta: ".trans_choice('{1} :count persona|[2,*] :count personas', $this->behind['pending']).' sin contestar.'),
                'behind' => $this->behind,
                'completed' => $this->completed,
                'target' => $this->target,
                'percent' => $this->target === 0 ? 0 : (int) round(100 * min($this->completed, $this->target) / $this->target),
                'areas' => $this->areas,
                'remind' => $this->toRemind > 0,
            ]);
    }
}
