<?php

declare(strict_types=1);

namespace App\Modules\Billing\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells an account, a few days before the cut, what each of its companies will be charged next month. Billing mail is operational: it never counts against a campaign quota. */
final class NextAmountNotice extends Notification implements MailBag
{
    /** @param  list<array{company: string, amount: string}>  $lines */
    public function __construct(private readonly string $accountName, private readonly array $lines, private readonly string $period) {}

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject("Lo que se cobrará en {$this->period}")
            ->greeting("Hola, {$this->accountName}")
            ->line("Con el corte del 1 de {$this->period}, esto es lo que se cobrará por cada empresa (IVA incluido):");

        foreach ($this->lines as $line) {
            $mail->line("{$line['company']}: {$line['amount']}");
        }

        return $mail->line('Si algo no coincide con lo que contrató, escríbanos antes del corte.');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
