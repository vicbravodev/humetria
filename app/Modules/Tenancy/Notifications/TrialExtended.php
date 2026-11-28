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

/** Tells the owner of a company whose trial had ended that it was extended, and until which day it now runs. */
final class TrialExtended extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param  string  $endsOn  the last day of the trial as the owner reads it: "2 de diciembre". */
    public function __construct(private readonly CompanySummary $company, private readonly string $endsOn)
    {
        $this->afterCommit();
    }

    /** @return list<int> Seconds before the second and the third try. */
    public function backoff(): array
    {
        return [60, 300];
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
        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => false])
            ->subject("Tu prueba se extendió hasta el {$this->endsOn}")
            ->greeting($notifiable instanceof User ? "Hola, {$notifiable->name}" : 'Hola')
            ->line("Tu prueba de {$this->company->tradeName} se extendió hasta el {$this->endsOn}: ya puedes entrar y tu gente puede usar Humetría otra vez.")
            ->action('Abrir Humetría', route('login'));
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
