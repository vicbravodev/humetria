<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * «Se acerca tu próxima evaluación» (rediseño v2, docs/02 «Correos»): 60 days before the NOM-035 evaluation of one or more
 * centers turns 2 years old (num. 7.9), to the owner, with the way to schedule the next one.
 */
final class NextEvaluationDue extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $centers  the names of the centers, by name
     * @param  string  $dueOn  the day they turn 2 years old, "4 ene 2027"
     */
    public function __construct(
        private readonly CompanySummary $company,
        private readonly array $centers,
        private readonly string $dueOn,
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
        $line = count($this->centers) === 1
            ? "La evaluación de NOM-035 de {$this->centers[0]} cumple 2 años el {$this->dueOn}. La norma pide evaluar al menos cada 2 años: prográmala con tiempo."
            : 'Las evaluaciones de NOM-035 de '.implode(', ', array_slice($this->centers, 0, -1)).' y '.$this->centers[count($this->centers) - 1]." cumplen 2 años el {$this->dueOn}. La norma pide evaluar al menos cada 2 años: prográmalas con tiempo.";

        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company])
            ->subject('Se acerca tu próxima evaluación')
            ->greeting('Hola')
            ->line($line)
            ->action('Programar', $this->url);
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}
